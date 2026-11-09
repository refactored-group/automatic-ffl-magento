# Magento Marketplace releases

Read this runbook before preparing an Automatic FFL Marketplace release. Build from a recorded Git commit, validate the resulting ZIP in Magento, and upload that same file. Record the commit, version, SHA-256, test environment, release URL, submission ID, and review results together.

## What happened with 1.0.39 and 1.0.40

The November 2026 submission encountered several independent failures. Each successful check allowed the package to reach the next check.

| Failure | Evidence and correction |
| --- | --- |
| Package validation rejected `.gitignore` | Remove it from the distribution archive. Keep the development repository intact. |
| Composer excluded PHP 8.5 | The root requirement was `>=7.1 <8.5`. Version 1.0.40 changes the upper bound to `<8.6` after testing on PHP 8.5. |
| Code Sniffer scanned development tests | Smoke tests contained direct output that Adobe rejected. Exclude the root `tests/` directory from the Marketplace ZIP and scan the extracted distribution. |
| Installation & Varnish failed during compilation | Adobe's raw reports for both CE and EE showed `RetryAttribution::execute()` missing the `int` return type required by Symfony Console 7. This happened before cache testing. Add `: int` to the command method. |
| A clean local install failed before stores existed | Registering the command constructed its helper, which loaded a checkout session and attempted to resolve the default website. Inject `RefactoredGroup\AutoFflCore\Helper\Data\Proxy` for the command's `helper` argument so Magento can register the command during setup. |
| The GitHub release workflow failed | `Auth GitHub CLI` returned HTTP 401, `Bad credentials`, for `TOKEN_GITHUB`. The release was completed through the already authenticated local CLI. |

The compatibility fix is commit `47d75380bbf4332f477fcec327da2ff0728b6ad9`. It changes three files, with eight insertions and three deletions including the version bump. Source changes are in `composer.json`, `core/Console/Command/RetryAttribution.php`, and `core/etc/di.xml`.

Repeated uploads were inefficient because package validation, static analysis, and a running Magento installation exercise different requirements. Downloading the detailed installation report and reproducing its first fatal error should happen as soon as a build check fails.

## Prepare the version and environment

1. Identify this nested repository and inspect `git status --short`. Preserve unrelated working files.
2. Read the current Adobe installation-test and system-requirements pages linked below. Record the Magento patch, PHP, Symfony, database, search, and Varnish versions used by Adobe. The test service uses current compatible dependencies, which can differ from an older demo or a source checkout's lock file.
3. Check the supported PHP range in the root `composer.json` against that matrix. Raising the bound requires runtime validation on the added version.
4. Choose an unused root package version and ensure it matches the intended Git tag and Marketplace version. Nested module versions have their own history. The root package replaces those modules at `self.version`.
5. Commit the release changes. Record the full commit ID before packaging. Use a new version for corrections to a published release so Composer caches, tags, and downloadable assets remain consistent.
6. Prepare an isolated Magento installation with disposable data. Verify both fresh installation and upgrade from the prior extension version when applicable. Keep checkout and payment acceptance appropriate to the code changed.

The Magento demo used PHP 8.2 at this release. A successful build there would not establish PHP 8.5 compatibility. Use permitted demo access only within the task's scope and obey the parent workspace's environment restrictions.

## Build the Marketplace ZIP

Run this Bash block from the Magento extension repository after committing the release. It creates a temporary export and writes the ZIP to the parent workspace's `outputs` directory. It refuses to replace an existing artifact.

```bash
set -euo pipefail

release_commit="$(git rev-parse HEAD)"
release_version="$(git show "${release_commit}:composer.json" | php -r '$p = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR); echo $p["version"];')"
release_name="refactored-group-automatic-ffl-magento-${release_version}"
release_dir="$(cd .. && pwd)/outputs/magento-marketplace-${release_version}"
release_zip="${release_dir}/${release_name}.zip"

if [ -e "$release_zip" ]; then
    echo "An artifact already exists at $release_zip. Inspect it before rebuilding."
    exit 1
fi

build_root="$(mktemp -d)"
mkdir -p "$release_dir"
git archive --format=tar --prefix="${release_name}/" "$release_commit" |
    tar -xf - -C "$build_root"
rm -f "${build_root}/${release_name}/.gitignore"
rm -rf "${build_root}/${release_name}/tests"
(
    cd "$build_root"
    zip -q -r "$release_zip" "$release_name"
)
unzip -t "$release_zip"
shasum -a 256 "$release_zip"
```

Inspect the archive entries. Confirm the root `composer.json` has the intended version, all module registration and autoload files are present, and development files such as `.gitignore`, `tests/`, `.git/`, `vendor/`, editor files, and local output directories are absent. Investigate any newly tracked build artifacts before shipping them.

Keep the ZIP immutable once testing begins. An edit requires a rebuilt archive, a new checksum, and rerunning the affected checks. Use the named release ZIP asset when uploading to Adobe. GitHub's automatically generated source archives contain the repository's development files.

## Validate the distribution

Extract the ZIP into a separate directory. Install the Magento Coding Standard in an isolated Composer tool project and record its version. Ensure PHPCS can resolve the `Magento2`, `PHPCompatibility`, and `PHPCSUtils` standards. Run Adobe's documented scan against the extracted package.

```bash
phpcs --standard=Magento2 --extensions=php,phtml \
    --error-severity=10 --ignore-annotations \
    --report=json --report-file=phpcs-report.json \
    /absolute/path/to/extracted-package
```

Read the JSON totals and messages. Severity 10 errors block submission. PHPCS can also return a nonzero exit status for warnings, and tool deprecation notices can appear on standard output. Keep the JSON report in its own file and inspect the actual errors. The 1.0.40 local scan had zero blocking errors and 682 warnings. [Adobe Code Sniffer guidance](https://developer.adobe.com/commerce/marketplace/guides/sellers/code-sniffer)

Install the ZIP through Composer's artifact repository in the isolated Magento project. Give the artifact directory an absolute path and use the intended package version.

```bash
composer config repositories.automaticffl artifact /absolute/path/to/artifact-directory
composer require refactored_group/automatic-ffl-magento:VERSION \
    --update-no-dev --no-interaction
```

`composer require` uses `--update-no-dev`. Compare the installed extension files against the ZIP contents before compiling, especially if an artifact with that version was previously installed or cached.

Capture exit codes and logs for a fresh `setup:install` using the isolated database and search service, and for these build stages. Run an upgrade on an existing test installation when the release changes upgrade behavior.

```bash
php -d memory_limit=3G bin/magento setup:upgrade
php -d memory_limit=3G bin/magento setup:di:compile
php -d memory_limit=3G bin/magento setup:static-content:deploy -f en_US
php -d memory_limit=3G bin/magento deploy:mode:set production --skip-compilation
php -d memory_limit=3G bin/magento indexer:reindex
php -d memory_limit=1G bin/magento cache:flush
php -d memory_limit=1G bin/magento autoffl:attribution:retry 0
```

The retry command must print `Use a positive Magento order entity ID.` and exit with status 1 for input `0`. The preceding build commands must exit successfully. The PHP CLI's local 128 MB default was insufficient for some Magento configuration commands during 1.0.40 validation.

Use Varnish with Magento's generated VCL. Check backend health, HTTP status, and a miss followed by a hit. Include product/category cache behavior and invalidation after product edits when reproducing Adobe's full test. Adobe documents its fixture profile and `X-EQP-Cache` instrumentation. [Adobe Installation and Varnish tests](https://developer.adobe.com/commerce/marketplace/guides/sellers/installation-and-varnish-tests)

The local 1.0.40 environment used Magento Open Source 2.4.9, PHP 8.5.12, Symfony Console 7.4.7, MariaDB 11.8.9, OpenSearch 3.9.0, and Varnish 8.0.2. Installation, compilation, static deployment, production mode, indexing, the invalid-order command check, and a home-page HTTP 200 miss/hit smoke test passed. Adobe's failed 1.0.39 report used Symfony Console 7.4.23. The local checks covered CE and a basic cache smoke test. Adobe's EE environment and full product/category cache suite remain part of its separate review.

Choose the service matrix again for each release. Adobe currently lists MariaDB 11.8 as an option for its 2.4.9 Cloud matrix and MariaDB 12.3 for on-premises. Record which configuration the test environment follows. [Adobe system requirements](https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/system-requirements)

### Local test setup lessons

- Use a reproducible test environment that already has Magento dependencies and services available. Downloading Magento and building the database/search/cache environment accounted for much of the elapsed time during this incident.
- If using a sparse Magento source checkout, create the required `pub/media` directory before installation. A missing directory can appear as a write-permissions failure.
- The clean-install default-website error was caused by the extension's eager checkout dependency. The helper proxy fixed it and the full installation then passed.
- The local Magento health endpoint returned HTTP 500 because the generated file-cache configuration had `backend_options` without an explicit `backend`. Adding `backend = file` for the isolated environment's default and page cache entries restored HTTP 200. Diagnose backend health before treating a Varnish 503 as an extension cache failure.
- Stop temporary PHP servers and task-specific containers after validation. Retain useful logs and the final ZIP checksum.

## Publish and submit

1. Inspect the intended remote branches and tag. Push the validated commit only to the authorized release branches. Avoid force pushes and changes to an existing published tag.
2. Verify the release workflow result. The current workflow triggers on `dev` and `master`, uses PHP 7.4, and creates a tag/release. It does not run the Magento/PHP runtime checks above or attach the cleaned Marketplace ZIP.
3. Attach the exact tested ZIP to the matching GitHub release. Verify its SHA-256 against GitHub's asset digest. If the workflow failed before creating a tag, use an authenticated CLI to create the release at the recorded full commit ID and attach that ZIP. Check for an existing tag/release first.
4. In the Developer Portal, create the intended version and upload the named ZIP. Confirm the version, compatibility selections, license, customer documentation, release notes, and launch timing.
5. Submit and confirm Technical Submission. A completed upload or saved draft still requires the final submission confirmation. Verify the completion message and resulting review status.
6. Complete Marketing Submission as required. Review inherited details before confirming, and record its resulting status separately.
7. Open Test Reports. Record results for Code Sniffer, Installation & Varnish, Semantic Version Check, and MFTF. Preserve the submission URL and screenshot. Automated testing in progress establishes acceptance into testing. Approval and publication require their own observed results.

Adobe's portal provides the submission and report workflow. [Technical submission guidance](https://developer.adobe.com/commerce/marketplace/guides/sellers/submit-for-technical-review)

For a failed test, download its raw report before making another package change. Record the tested editions and dependency versions, identify the first failing command and actual error, reproduce it, and validate the correction using the resulting archive. An empty report needs a refreshed or subsequent report. The Installation & Varnish label covers installation and compilation failures as well as cache failures.

## Release automation follow-up

At the 1.0.40 release, `.github/workflows/create-release.yml` failed in `Auth GitHub CLI` with HTTP 401. The workflow references `secrets.TOKEN_GITHUB` and the `Proxy Server` environment. Verify the resolved secret and its repository permissions before relying on automated publication. The evidence establishes invalid credentials, without identifying whether the token expired, was revoked, or was unavailable.

Future automation should perform the package build, distribution scan, and supported Magento/PHP build checks above, then attach the resulting ZIP. Until implemented, those are operator release gates. Repairing the workflow credential and adding these gates are outstanding work.

## 1.0.40 release record

| Field | Recorded value |
| --- | --- |
| Release commit and tag | `47d75380bbf4332f477fcec327da2ff0728b6ad9`, `1.0.40` |
| Artifact | `refactored-group-automatic-ffl-magento-1.0.40.zip` |
| Artifact SHA-256 | `df695c3dcddfb7277e364d18bb2d91dc8a16ef6f0c8debc81fe6f2dda34f7bbb` |
| GitHub release | [Release 1.0.40](https://github.com/refactored-group/automatic-ffl-magento/releases/tag/1.0.40) |
| Adobe submission | [Submission cdn8h0oo77](https://commercedeveloper.adobe.com/extensions/details/cdn8h0oo77) |
| Previous failed submission | [Submission 69u5ach4od](https://commercedeveloper.adobe.com/extensions/details/69u5ach4od) |
| Failed release workflow | [Run 42558772923](https://github.com/refactored-group/automatic-ffl-magento/actions/runs/42558772923) |
| Status observed after submission on November 8, 2026, America/Denver | Technical automated tests in progress. Marketing review passed using inherited details. Launch scheduled on approval. Refresh the portal for subsequent results. |

Local artifacts are under the parent workspace's `outputs/magento-marketplace-1.0.40/`. The raw 1.0.39 installation report was saved as `../Downloads/report (1).json`, relative to the parent workspace. Local test logs were under `/private/tmp/automatic-ffl-magento-249.SQKV0R/`, which is temporary storage. Preserve needed failure excerpts with future release records.
