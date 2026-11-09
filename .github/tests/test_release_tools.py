"""Regression coverage for the distribution and guarded draft entry points."""

import hashlib
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from zipfile import ZipFile


SCRIPTS = Path(__file__).resolve().parents[1] / "scripts"


class ReleaseToolsTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.source = self.root / "source"
        self.source.mkdir()
        self.artifacts = self.root / "artifacts"
        self.manifest = {
            "name": "refactored_group/automatic-ffl-magento",
            "version": "1.2.3",
            "autoload": {"files": ["registration.php"]},
        }
        self.write("registration.php", "<?php // registration\n")
        for name in (".gitignore", "tests/dev.php", ".github/ci.php", "vendor/lib.php", "outputs/build.zip"):
            self.write(name, "development only")
        self.write("core/.env.local", "test-only")
        self.write("core/Model.php", "<?php // runtime\n")
        subprocess.run(["git", "init", "--quiet"], cwd=self.source, check=True)

    def write(self, name, value):
        path = self.source / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(value)

    def commit(self):
        self.write("composer.json", json.dumps(self.manifest))
        subprocess.run(["git", "add", "--force", "."], cwd=self.source, check=True)
        subprocess.run(
            ["git", "-c", "user.name=Release Test", "-c", "user.email=release@example.com",
             "commit", "--quiet", "-m", "fixture"], cwd=self.source, check=True
        )

    def build(self):
        return subprocess.run(
            ["python3", str(SCRIPTS / "build-marketplace-package.py"), "--output-dir", str(self.artifacts)],
            cwd=self.source, capture_output=True, text=True
        )

    def test_distribution_contents_provenance_and_overwrite_refusal(self):
        self.commit()
        self.write("uncommitted.php", "must not ship")
        result = self.build()
        self.assertEqual(result.returncode, 0, result.stderr)
        metadata = json.loads(result.stdout)
        archive = self.artifacts / metadata["archive"]
        with ZipFile(archive) as package:
            names = {name.split("/", 1)[1] for name in package.namelist()}
            self.assertEqual(names, {"composer.json", "registration.php", "core/Model.php"})
        self.assertEqual(hashlib.sha256(archive.read_bytes()).hexdigest(), metadata["sha256"])
        self.assertEqual(self.build().returncode, 1)

    def test_missing_autoload_file_is_rejected(self):
        self.manifest["autoload"]["files"].append("missing.php")
        self.commit()
        result = self.build()
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("Missing autoload file", result.stderr)

    def test_invalid_version_is_rejected(self):
        self.manifest["version"] = "1.2.3/invalid"
        self.commit()
        self.assertNotEqual(self.build().returncode, 0)

    def prepare_fake_gh(self):
        self.commit()
        self.assertEqual(self.build().returncode, 0)
        command = self.root / "gh"
        command.write_text('''#!/usr/bin/env python3
import json, os, sys
args = sys.argv[1:]
with open(os.environ['CALL_LOG'], 'a') as log:
    log.write(json.dumps(args) + '\\n')
if args[:2] == ['api', '--paginate']:
    print('1.2.3' if os.environ.get('DRAFT_EXISTS') else '')
elif args[0] == 'api':
    if os.environ.get('TAG_EXISTS'):
        print('{}')
    else:
        print('gh: error (HTTP 401)' if os.environ.get('API_ERROR') else 'gh: Not Found (HTTP 404)', file=sys.stderr)
        sys.exit(1)
elif args[:2] == ['release', 'create']:
    sys.exit(1 if os.environ.get('UPLOAD_FAIL') else 0)
elif args[:2] == ['release', 'view']:
    print(json.dumps({'isDraft': True, 'url': 'https://example.com/draft'}))
else:
    sys.exit(99)
''')
        command.chmod(0o755)
        self.environment = dict(os.environ, PATH=str(self.root) + os.pathsep + os.environ["PATH"],
                                CALL_LOG=str(self.root / "calls.jsonl"), GITHUB_REPOSITORY="owner/repo")

    def draft(self, **flags):
        return subprocess.run(
            ["bash", str(SCRIPTS / "create-draft-release.sh"), str(self.artifacts)],
            capture_output=True, text=True, env=dict(self.environment, **flags)
        )

    def calls(self):
        return [json.loads(line) for line in (self.root / "calls.jsonl").read_text().splitlines()]

    def test_existing_tag_draft_and_authentication_error_prevent_creation(self):
        self.prepare_fake_gh()
        for flags in ({"TAG_EXISTS": "1"}, {"DRAFT_EXISTS": "1"}, {"API_ERROR": "1"}):
            with self.subTest(flags=flags):
                self.assertNotEqual(self.draft(**flags).returncode, 0)
        self.assertFalse(any(call[:2] == ["release", "create"] for call in self.calls()))

    def test_checked_archive_is_attached_only_to_a_draft_at_exact_commit(self):
        self.prepare_fake_gh()
        result = self.draft()
        self.assertEqual(result.returncode, 0, result.stderr)
        create = next(call for call in self.calls() if call[:2] == ["release", "create"])
        metadata = json.loads((self.artifacts / "release.json").read_text())
        self.assertIn("--draft", create)
        self.assertEqual(create[create.index("--target") + 1], metadata["commit"])
        self.assertIn(str(self.artifacts / metadata["archive"]), create)
        self.assertIn(str(self.artifacts / "SHA256SUMS"), create)

    def test_corrupt_archive_and_upload_failure_do_not_publish(self):
        self.prepare_fake_gh()
        failed = self.draft(UPLOAD_FAIL="1")
        self.assertNotEqual(failed.returncode, 0)
        self.assertIn("partial draft", failed.stderr)
        archive = next(self.artifacts.glob("*.zip"))
        archive.write_bytes(b"corrupt")
        result = self.draft()
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(sum(call[:2] == ["release", "create"] for call in self.calls()), 1)


if __name__ == "__main__":
    unittest.main()
