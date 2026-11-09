#!/usr/bin/env bash
set -euo pipefail

artifact_dir="$(cd "$1" && pwd)"
magento_dir="$(cd "$2" && pwd)"
log_dir="$(cd "$3" && pwd)"
version="$(jq -er '.version' "$artifact_dir/release.json")"

cd "$artifact_dir"
sha256sum --check SHA256SUMS
cd "$magento_dir"
mkdir -p pub/media
composer install --no-dev --no-interaction --no-scripts --no-progress
composer config repositories.automaticffl artifact "$artifact_dir"
composer require "refactored_group/automatic-ffl-magento:$version" --update-no-dev --no-interaction --no-scripts --no-progress

python3 - "$artifact_dir" <<'PY'
import json
from pathlib import Path
import sys
from zipfile import ZipFile
artifact = Path(sys.argv[1])
metadata = json.loads((artifact / 'release.json').read_text())
installed = Path('vendor/refactored_group/automatic-ffl-magento')
with ZipFile(artifact / metadata['archive']) as package:
    for entry in package.infolist():
        if not entry.is_dir():
            relative = entry.filename.split('/', 1)[1]
            if installed.joinpath(relative).read_bytes() != package.read(entry):
                raise SystemExit(f'Installed file differs from checked ZIP: {relative}')
print('Installed extension files match the checked ZIP.')
PY

php -v
composer show symfony/console
php -d memory_limit=3G bin/magento setup:install \
    --base-url=http://127.0.0.1:18441/ --db-host=127.0.0.1 --db-name=autoffl_release \
    --db-user=root --db-password=autoffl-ci-only \
    --admin-firstname=Release --admin-lastname=Check --admin-email=release@example.com \
    --admin-user=release --admin-password=Autoffl-CI-Local-Only-140 --backend-frontname=admin \
    --language=en_US --currency=USD --timezone=America/Denver --use-rewrites=1 \
    --search-engine=opensearch --opensearch-host=127.0.0.1 --opensearch-port=9200 \
    --opensearch-index-prefix=autoffl_release --opensearch-enable-auth=0 --no-interaction
php -d memory_limit=3G bin/magento setup:upgrade
php -d memory_limit=3G bin/magento setup:di:compile
php -d memory_limit=3G bin/magento setup:static-content:deploy -f en_US
php -d memory_limit=3G bin/magento deploy:mode:set production --skip-compilation
php -d memory_limit=3G bin/magento indexer:reindex
set +e
php -d memory_limit=1G bin/magento autoffl:attribution:retry 0 > "$log_dir/retry-command.log" 2>&1
retry_status=$?
set -e
test "$retry_status" -eq 1
grep -Fq 'Use a positive Magento order entity ID.' "$log_dir/retry-command.log"

php -r '$path="app/etc/env.php"; $config=require $path; foreach (["default", "page_cache"] as $cache) { $config["cache"]["frontend"][$cache]["backend"]="file"; } file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n");'
php -d memory_limit=1G bin/magento config:set system/full_page_cache/caching_application 2
php -d memory_limit=1G bin/magento cache:flush
php -d memory_limit=1G bin/magento varnish:vcl:generate --backend-host=127.0.0.1 --backend-port=18440 --output-file="$log_dir/default.vcl"

cat > "$log_dir/router.php" <<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file($_SERVER['DOCUMENT_ROOT'] . $path)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
PHP
PHP_CLI_SERVER_WORKERS=4 php -d memory_limit=1G -S 127.0.0.1:18440 -t pub "$log_dir/router.php" > "$log_dir/php-server.log" 2>&1 &
server_pid=$!
cleanup() {
    docker logs autoffl-release-cache > "$log_dir/varnish.log" 2>&1 || true
    docker exec autoffl-release-cache varnishadm backend.list > "$log_dir/backend-health.log" 2>&1 || true
    kill "$server_pid" 2>/dev/null || true
    docker stop autoffl-release-cache >/dev/null 2>&1 || true
}
trap cleanup EXIT
docker run -d --name autoffl-release-cache --network host -e VARNISH_HTTP_PORT=18441 \
    -v "$log_dir/default.vcl:/etc/varnish/default.vcl:ro" varnish:8.0
for attempt in {1..30}; do
    if docker exec autoffl-release-cache varnishadm backend.list | grep -q ' healthy '; then
        break
    fi
    sleep 2
done
curl --fail --silent --show-error --max-time 60 -D "$log_dir/cache-first.headers" http://127.0.0.1:18441/ -o "$log_dir/storefront.html"
curl --fail --silent --show-error --max-time 60 -D "$log_dir/cache-second.headers" http://127.0.0.1:18441/ -o /dev/null
grep -qi 'X-Magento-Cache-Debug: MISS' "$log_dir/cache-first.headers"
grep -qi 'X-Magento-Cache-Debug: HIT' "$log_dir/cache-second.headers"
echo 'Magento installation, production build, command registration, and Varnish miss/hit checks passed.'
