#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
page=(ROOT/'includes/operator_page.php').read_text(encoding='utf-8')
op=(ROOT/'assets/css/operator-live.css').read_text(encoding='utf-8')
panel=(ROOT/'assets/css/panel-components.css').read_text(encoding='utf-8')
quick=(ROOT/'assets/css/quick-order.css').read_text(encoding='utf-8')
maint=(ROOT/'includes/maintenance.php').read_text(encoding='utf-8')
admin=(ROOT/'admin/maintenance.php').read_text(encoding='utf-8')
installer=(ROOT/'install.php').read_text(encoding='utf-8')
builder=(ROOT/'tools/build-release.php').read_text(encoding='utf-8')

# One CSS owner for bill-edit row geometry; both steppers consume it.
assert page.count('bill-edit-control-row') == 2
assert '.bill-edit-control-row{display:grid;grid-template-columns:minmax(0,1fr) max-content' in op
assert '.bill-edit-prepared-count{display:grid' not in panel
assert '.bill-edit-prepared-count .quantity-stepper' not in panel

# Product-card compact geometry must not override the cart stepper on desktop.
assert '.quick-order-item .quick-order-inline-qty{width:98px' in quick
assert quick.count('.quick-order-line-qty{') == 1
assert '.quick-order-line-qty{width:132px;height:44px;grid-template-columns:44px 44px 44px}' in quick
assert '.quick-order-cart-line .quick-order-line-qty{width:132px' not in quick
assert '  .quick-order-inline-qty{width:98px' not in quick

# Off-server copies are encrypted by default. Raw GET download path is gone.
assert 'MAINTENANCE_SECURE_BACKUP_FORMAT' in maint
assert 'sodium_crypto_secretstream_xchacha20poly1305' in maint
assert 'MAINTENANCE_SECURE_BACKUP_MIN_PASSPHRASE_CHARS' in maint and 'MAINTENANCE_SECURE_BACKUP_MAX_PASSPHRASE_BYTES' in maint
assert "preg_match_all('/./us', $passphrase" in maint
assert "sodium_crypto_pwhash" in maint and 'ARGON2ID13' in maint
assert "if ($action === 'export_secure')" in admin
assert "if (isset($_GET['download']))" not in admin
assert '?download=' not in admin
assert 'data-secure-export' in admin
assert 'name="backup_passphrase"' in admin
assert "'sodium'=>'Sodium'" in installer
assert "'required_extensions'=>['pdo_mysql','openssl','sodium','Phar']" in builder
print('dev19 non-print contract PASS: stepper ownership is unified, cart geometry is isolated, and off-server backup export is authenticated encrypted .skb with no raw download route.')
