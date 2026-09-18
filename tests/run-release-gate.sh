#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
export PYTHONDONTWRITEBYTECODE=1
export SOKNA_RELEASE_GATE=1
PROMOTION="${SOKNA_RELEASE_PROMOTION:-0}"
VERSION="$(tr -d '[:space:]' < VERSION.txt)"
TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT
run(){ "$@" 2>&1 | tee -a "$TMP"; }

printf 'Release identity + syntax...\n'
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([-+][A-Za-z0-9.-]+)?$ ]]
grep -q "const RELEASE='$VERSION'" service-worker.js
grep -q "const CACHE='cafe-staff-v$VERSION'" service-worker.js
find . -path './.git' -prune -o -path './storage' -prune -o -type f -name '*.php' -print0 | while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done
find assets/js -type f -name '*.js' -print0 | while IFS= read -r -d '' file; do node --check "$file" >/dev/null; done
node --check service-worker.js >/dev/null

printf 'Core/domain blocker contracts...\n'
run php tests/unit.php
run php tests/sokna-center-unit.php
run php tests/updater-canonical.php
for test in \
  install-compatibility.py project-engineering-baseline-contract.py refactor-preservation-policy-contract.py v1363-batch-a-root-cleanup.py v1364-menu-owner-route-contract.py batch-b-schema-root-contract.py function-owner-boundary-contract.py v1360-pre-rc-schema-health.py prelaunch-cleanup.py v1306-login-picker.py rc1-quick-order-cart-hardening.py rc2-login-security-contract.py rc2-module-manager-hardening.py rc2-route-security-contract.py v1360-supply-module-contract.py v1360-ui-conformance-contract.py v1360-ui-language-contract.py panel-tab-language-contract.py v1360-panel-ui-inventory-contract.py v1360-icon-system-contract.py v1361-ui-root-fixes-contract.py v1363-printing-operations-contract.py v1360-optional-inventory-supply-contract.py v1360-module-ownership-contract.py v1360-marketing-module-contract.py v1360-reporting-module-contract.py v1360-personnel-module-contract.py v1360-operational-ui-workflow.py v1360-settlement-race-hardening.py itemized-settlement-contract.py v1364-order-context.py v1364-runtime-owner-cleanup.py v1364-settlement-feedback-contract.py v1360-defect-class-gate.py v1360-final-invariants.py order-v1322-hardening.py inventory-v1320-contracts.py inventory-v1321-hardening.py inventory-v1323-ux.py \
  v1325-panel-reporting-contracts.py v1326-financial-hardening.py v1326-inventory-count-hardening.py v1326-inventory-domain-completeness.py \
  v1326-inventory-masterdata-recipe-hardening.py v1326-inventory-operations-hardening.py v1326-menu-admin-hardening.py \
  v1327-hotfix-contracts.py v1328-panel-experience-contracts.py v1329-defect-class-gate.py v1329-financial-contracts.py \
  shared-reorder-contract.py messages-v2-contract.py print-template-v2-contract.py order-review-current-bill-v13219.py \
  navigation-entitlement-contract.py sokna-center-integration.py payroll-reminder-badge-contract.py operational-fast-path.py notification-action-security.py takeaway-services-contract.py panel-keyboard-input-contract.py v1315-touch-focus-contract.py guest-copy-domain-parity.py payment-secondary-independence.py pwa-app-mode-contract.py updater-current-contract.py financial-row-interaction-parity.py financial-ui-family-contract.py financial-visual-hierarchy-contract.py financial-composition-contract.py v13210-defect-class-gate.py v13210-notification-contracts.py v13210-schedule-contracts.py v13213-time-picker-contract.py v13211-escaped-defects.py v13211-uat-fix-audit.py v13212-notification-pipeline.py v13216-notification-hybrid.py visual-layout-contracts.py v13220-operational-correctness.py v13220-audit-timeline.py v13220-print-operations.py v13220-human-reference-ui.py print-v4-reproduction-required-intent.py print-v4-reproduction-ambiguity.py print-v4-reproduction-attempts.py print-v4-server-contract.py print-v4-contract-hardening.py print-v4-empty-claim-retention.py print-dev23-claim-reconciliation-contract.py v1331-ui-notification-contracts.py v1332-cart-owner-contract.py v1333-ui-contracts.py v1334-guest-order-workflow-contract.py v1340-operations-purchase-permissions.py print-v4-only-baseline-contract.py print-agent-distribution-contract.py print-v4-agent-health-diagnostics.py print-v4-fault-load-model.py print-dev14-reliability-contract.py print-v4-agent61-accept-contract.py bill-edit-stepper-standard-contract.py backup-manager-contract.py dev17-portable-backup-contract.py dev19-nonprint-contract.py; do
  run python3 "tests/$test"
done
run php tests/v1326-inventory-cost-projection.php
run php tests/v13211-subscriber-enrichment.php
run php tests/rc2-login-security.php
run php tests/v1360-settlement-signature.php
run php tests/print-template-v2-runtime.php
run php tests/print-agent-receipt-validator.php
run php tests/backup-policy-runtime.php
run php tests/dev17-portable-backup-runtime.php
run php tests/dev19-secure-backup-runtime.php
run php tests/accommodation-api-contract.php
run python3 tests/accommodation-http-integration.py
run python3 tests/accommodation-connection.py
run python3 tests/accommodation-schema-consistency.py
run python3 tests/v1360-accommodation-boundary-contract.py

printf 'No-skip browser blocker matrix...\n'
for test in \
  v1306-login-picker-browser.py v1305-waiter-picker-browser.py guest-preview-clean-browser.py shared-reorder-browser.py messages-v2-browser.py print-template-v2-browser.py hotfix-1301-operator-browser.py quick-order-undo-swipe-browser.py \
  quick-order-production-browser.py v1325-panel-reporting-browser.py v1326-financial-browser.py v1326-center-entitlement-browser.py \
  v1328-panel-experience-browser.py v1329-panel-browser.py v13210-panel-browser.py v13211-sheet-browser.py v13213-time-picker-browser.py v13216-notification-runtime-browser.py payroll-reminder-badge-browser.py panel-keyboard-input-browser.py v1315-touch-focus-browser.py pwa-app-mode-browser.py updater-current-browser.py visual-quality-browser.py v1340-ui-browser.py ui-density-root-fixes-browser.py v1360-supply-browser.py v1360-modules-browser.py v1360-ui-conformance-browser.py panel-tab-language-browser.py v1360-icon-system-browser.py v1364-mobile-cashier-browser.py v1364-mobile-table-overview-browser.py v1364-startup-context-browser.py itemized-settlement-browser.py desktop-invoice-density-browser.py late-accounting-browser.py printing-settings-browser.py v1363-ui-browser.py dev19-stepper-alignment-browser.py backup-manager-browser.py; do
  run python3 "tests/$test"
done

printf 'Visual promotion evidence...\n'
run python3 tests/quick-order-production-browser.py
python3 tests/v1333-financial-filter-browser.py
python3 tests/v1333-activity-browser.py
python3 tests/visual-promotion-baselines.py

printf 'Critical route environment probes...\n'
if [[ "$PROMOTION" == "1" ]]; then
  printf 'Promotion mode: MariaDB/MySQL + authenticated HTTP are mandatory.\n'
  SOKNA_REQUIRE_DB_ROUTE_GATE=1 run php tests/v13211-critical-route-db.php
  SOKNA_REQUIRE_HTTP_ROUTE_GATE=1 run python3 tests/v13211-critical-route-http.py
else
  run php tests/v13211-critical-route-db.php
  run python3 tests/v13211-critical-route-http.py
fi

if grep -Eqi '(^|[^A-Z])SKIP(PED)?([: ]|$)|unavailable.*skipped' "$TMP"; then
  echo 'Release gate FAILED: a blocker test was skipped.' >&2
  exit 1
fi
if [[ "$PROMOTION" == "1" ]] && grep -Eqi 'UAT_REQUIRED|NOT_TESTED|BLOCKED:' "$TMP"; then
  echo 'Release promotion FAILED: an environment blocker did not execute.' >&2
  exit 1
fi
printf 'Sokna %s %s gate PASS.\n' "$VERSION" "$([[ "$PROMOTION" == "1" ]] && echo promotion || echo source)"
