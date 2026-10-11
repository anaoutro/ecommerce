#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html
wp() { php /ja/tools/wp-cli.phar --allow-root "$@"; }
if ! wp core is-installed >/dev/null 2>&1; then
    php /ja/setup/core-install.php
    wp option update home http://localhost:8090
    wp option update siteurl http://localhost:8090
fi
if ! wp plugin is-installed woocommerce; then
    wp plugin install /ja/tools/woocommerce.zip
fi
wp plugin activate woocommerce ja-loja-core
wp theme activate ja-eletronicos-theme
wp eval-file /ja/setup/configure.php
# As traduções oficiais estão incluídas no pacote local.
if [ -f /ja/tools/translations/pt_BR.mo ]; then
    mkdir -p wp-content/languages
    cp -R /ja/tools/translations/. wp-content/languages/
    chown -R www-data:www-data wp-content/languages
    wp option update WPLANG pt_BR
else
    wp language core install pt_BR --activate || true
    wp language plugin install woocommerce pt_BR || true
fi
if [ "$(wp option get ja_local_catalog_imported 2>/dev/null || true)" != 'yes' ]; then
    args=(--source=/ja/imports/catalog.json --images=/ja/imports/images)
    if [ -n "${JA_IMPORT_LIMIT:-}" ]; then args+=("--limit=$JA_IMPORT_LIMIT"); fi
    wp ja import-catalog "${args[@]}"
    wp eval-file /ja/setup/publish-reference.php
    wp option update ja_local_catalog_imported yes
fi
mkdir -p wp-content/uploads
chown -R www-data:www-data wp-content/uploads
printf '\nJA pronta para abrir em http://localhost:8090\nPainel: http://localhost:8090/?ja_management=1\nCredenciais: arquivo ACESSO-LOCAL.txt no seu computador.\n'
