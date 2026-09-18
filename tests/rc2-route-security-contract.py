#!/usr/bin/env python3
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]

# Every privileged page family must establish auth/capability/module policy near the route itself.
for folder in ('admin','operator','staff'):
    for p in sorted((root/folder).glob('*.php')):
        s=p.read_text(encoding='utf-8')[:7000]
        assert any(token in s for token in ('require_login(', 'require_capability(', 'require_any_capability(')), f'{p.relative_to(root)} missing direct authentication/capability guard'

# JSON APIs that parse a request body must explicitly constrain the HTTP method unless their
# contract is the signed GET directory endpoint.
for p in sorted((root/'api').glob('*.php')):
    s=p.read_text(encoding='utf-8')
    if 'request_json()' in s:
        assert 'REQUEST_METHOD' in s, f'{p.name} parses JSON without explicit method guard'

# Mutating browser/session APIs use CSRF. Signed one-time push actions/kicks are deliberately
# token-authenticated instead, and the Center directory is a signed GET contract.
token_only={'push_action.php','push_kick.php'}
signed_get={'sokna_center_users.php'}
for p in sorted((root/'api').glob('*.php')):
    s=p.read_text(encoding='utf-8')
    if p.name in token_only:
        assert 'token' in s and ('_decode(' in s or 'token_decode(' in s), f'{p.name} token contract missing'
        continue
    if p.name in signed_get:
        assert 'sokna_center_verify_user_directory_request' in s
        continue
    if 'request_json()' in s:
        assert 'csrf_valid(' in s, f'{p.name} session/browser JSON endpoint missing CSRF validation'

# Sensitive downloads stay behind admin authentication; filenames are basename-constrained.
maintenance=(root/'admin/maintenance.php').read_text(encoding='utf-8')
prints=(root/'admin/print_templates.php').read_text(encoding='utf-8')
assert "require_login(['admin'])" in maintenance and "if ($action === 'export_secure')" in maintenance and "basename((string)($_POST['name'] ?? ''))" in maintenance
assert "if (isset($_GET['download']))" not in maintenance and "?download=" not in maintenance
assert "require_login(['admin'])" in prints and 'Content-Disposition' in prints


# Runtime storage contains sessions, updater pointers/history and backups. It must be denied from
# direct HTTP access from the first bootstrap request, not only after opening Maintenance/Updater.
bootstrap=(root/'bootstrap.php').read_text(encoding='utf-8')
assert "$runtimeStorage = __DIR__ . '/storage'" in bootstrap
assert "$runtimeStorage . '/.htaccess'" in bootstrap and 'Require all denied' in bootstrap
assert "$runtimeStorage . '/index.html'" in bootstrap

metric=(root/'api/metric.php').read_text(encoding='utf-8')
assert "REQUEST_METHOD'] ?? 'GET') !== 'POST'" in metric and 'csrf_valid(' in metric
print('RC2 route security contract PASS: privileged route auth, explicit JSON methods, CSRF/token boundaries, and sensitive download guards are locked.')
