#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export PYTHONDONTWRITEBYTECODE=1

echo '== PHP lint =='
php_count=0
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
  php_count=$((php_count+1))
done < <(find . -path './.git' -prune -o -type f -name '*.php' -print0)
echo "PHP lint PASS: ${php_count} files"

echo '== JS syntax =='
js_count=0
while IFS= read -r -d '' file; do
  node --check "$file" >/dev/null
  js_count=$((js_count+1))
done < <(find assets -type f -name '*.js' -print0)
node --check service-worker.js >/dev/null
js_count=$((js_count+1))
while IFS= read -r -d '' file; do
  node --check "$file" >/dev/null
  js_count=$((js_count+1))
done < <(find public_edge -type f -name '*.js' -print0)
echo "JS syntax PASS: ${js_count} files"

echo '== Unit =='
php tests/unit.php
php tests/phase2-relay-contract.php
php tests/phase2-projection-contract.php
php tests/phase2-public-boundary-contract.php
php tests/phase3-guest-publish-contract.php
php tests/phase3-guest-runtime-contract.php
php tests/phase4-remote-read-contract.php
php tests/phase5-deferred-boundary-contract.php
php tests/phase6a-preparation-permissions-contract.php
php tests/phase6b-sellable-kind-contract.php
php tests/phase6c-table-draft-contract.php
python tests/phase7-runtime-services-contract.py

echo '== Supply / modular contracts =='
python tests/v1360-supply-module-contract.py
python tests/v1360-ui-conformance-contract.py
python tests/v1360-ui-language-contract.py
python tests/panel-tab-language-contract.py
python tests/v1360-rc4-ui-handoff-contract.py
python tests/v1360-panel-ui-inventory-contract.py
python tests/v1360-icon-system-contract.py
python tests/v1360-optional-inventory-supply-contract.py
python tests/v1360-module-ownership-contract.py
python tests/v1360-marketing-module-contract.py
python tests/v1360-reporting-module-contract.py
python tests/v1360-personnel-module-contract.py
python tests/v1360-accommodation-boundary-contract.py
python tests/v1360-defect-class-gate.py
python tests/v1360-operations-purchase-permissions.py
python tests/v1360-final-invariants.py
python tests/v1360-operational-ui-workflow.py
python tests/rc1-quick-order-cart-hardening.py
python tests/rc2-login-security-contract.py
php tests/rc2-login-security.php
python tests/rc2-module-manager-hardening.py
python tests/rc2-route-security-contract.py
python tests/v1360-settlement-race-hardening.py
php tests/v1360-settlement-signature.php
python tests/itemized-settlement-contract.py
python tests/v1364-order-context.py
python tests/v1364-runtime-owner-cleanup.py
python tests/v1364-settlement-feedback-contract.py
python tests/prelaunch-cleanup.py
python tests/v1360-pre-rc-schema-health.py
python tests/updater-current-contract.py
python tests/recovery-current-contract.py
php tests/updater-canonical.php
python tests/dev19-nonprint-contract.py
php tests/dev19-secure-backup-runtime.php
python tests/backup-manager-contract.py
python tests/dev17-portable-backup-contract.py
php tests/dev17-portable-backup-runtime.php

echo '== Cross-domain regression contracts =='
python tests/project-engineering-baseline-contract.py
python tests/refactor-preservation-policy-contract.py
python tests/v1363-batch-a-root-cleanup.py
python tests/batch-b-schema-root-contract.py
python tests/function-owner-boundary-contract.py
python tests/v1306-login-picker.py
python tests/install-compatibility.py
python tests/inventory-v1320-contracts.py
python tests/inventory-v1321-hardening.py
python tests/inventory-v1323-ux.py
python tests/order-v1322-hardening.py
python tests/operational-fast-path.py
python tests/notification-action-security.py
python tests/compact-responsibilities.py
python tests/panel-navigation.py
python tests/panel-date-input-contract.py
python tests/panel-native-ui-contract.py
python tests/v1315-touch-focus-contract.py
python tests/panel-shell-page-contract.py
python tests/panel-css-ownership.py
python tests/v1324-design-system-contracts.py
python tests/v1326-reviewed-pages-ux.py

echo '== Browser =='
python tests/v1306-login-picker-browser.py
python tests/v1360-supply-browser.py
python tests/v1360-modules-browser.py
SOKNA_UI_WIDTHS=320,390,412 python tests/v1360-ui-conformance-browser.py
python tests/panel-tab-language-browser.py
python tests/v1360-icon-system-browser.py
SOKNA_UI_WIDTHS=320,390,412 python tests/guest-1276-browser.py
python tests/panel-jalali-browser.py
python tests/panel-navigation-browser.py
python tests/v1315-touch-focus-browser.py
python tests/quick-order-production-browser.py
python tests/phase6c-table-draft-browser.py
python tests/dev19-stepper-alignment-browser.py
python tests/backup-manager-browser.py
python tests/itemized-settlement-browser.py
python tests/v1364-mobile-cashier-browser.py
python tests/v1364-mobile-table-overview-browser.py
python tests/v1364-startup-context-browser.py
python tests/phase6a-preparation-area-browser.py
python tests/desktop-invoice-density-browser.py
python tests/late-accounting-browser.py
python tests/updater-current-browser.py

VERSION="$(tr -d '[:space:]' < VERSION.txt)"
echo "Sokna ${VERSION} dev gate PASS (environment-independent gates only)."
echo 'UAT_REQUIRED remains: real MySQL/MariaDB fresh install + update from installed 1.36.4-dev.18 to current version, encrypted Backup upload/restore on a second installation, Inventory/Supply state matrix on real DB, authenticated staging HTTP, real-device Push, Windows Print Agent/printer, human visual UAT.'
