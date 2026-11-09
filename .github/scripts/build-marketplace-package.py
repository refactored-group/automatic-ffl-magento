#!/usr/bin/env python3
"""Build the Marketplace distribution from one committed source revision."""

import argparse
import hashlib
import io
import json
from pathlib import Path, PurePosixPath
import re
import subprocess
import zipfile


def build_package(commit, output_dir):
    commit = subprocess.check_output(
        ["git", "rev-parse", "--verify", f"{commit}^{{commit}}"], text=True
    ).strip()
    source = subprocess.check_output(["git", "archive", "--format=zip", commit])
    excluded = {
        ".git", ".github", ".gitignore", ".DS_Store", ".idea", ".vscode",
        "tests", "vendor", "node_modules", "outputs", "work", "__MACOSX",
    }
    with zipfile.ZipFile(io.BytesIO(source)) as archive:
        manifest = json.loads(archive.read("composer.json"))
        version = manifest["version"]
        if not isinstance(version, str) or not re.fullmatch(r"\d+\.\d+\.\d+", version):
            raise ValueError("The package version must have three numeric components.")
        if manifest["name"] != "refactored_group/automatic-ffl-magento":
            raise ValueError("Unexpected Composer package name.")
        entries = [
            entry for entry in archive.infolist()
            if not entry.is_dir()
            and not excluded.intersection(PurePosixPath(entry.filename).parts)
            and not any(part.startswith(".env") for part in PurePosixPath(entry.filename).parts)
        ]
        names = {entry.filename for entry in entries}
        for filename in manifest["autoload"]["files"]:
            if filename not in names:
                raise ValueError(f"Missing autoload file in distribution: {filename}")
        output_dir.mkdir(parents=True, exist_ok=True)
        package_name = f"refactored-group-automatic-ffl-magento-{version}"
        destination = output_dir / f"{package_name}.zip"
        with zipfile.ZipFile(destination, "x", compression=zipfile.ZIP_DEFLATED) as package:
            for entry in entries:
                data = archive.read(entry)
                entry.filename = f"{package_name}/{entry.filename}"
                package.writestr(entry, data)
    digest = hashlib.sha256(destination.read_bytes()).hexdigest()
    (output_dir / "SHA256SUMS").write_text(f"{digest}  {destination.name}\n")
    metadata = {"version": version, "commit": commit, "archive": destination.name, "sha256": digest}
    (output_dir / "release.json").write_text(json.dumps(metadata, indent=2) + "\n")
    return metadata


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--commit", default="HEAD")
    parser.add_argument("--output-dir", required=True, type=Path)
    args = parser.parse_args()
    print(json.dumps(build_package(args.commit, args.output_dir)))
