#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
settings=(ROOT/'admin/settings.php').read_text(encoding='utf-8')
functions=(ROOT/'includes/functions.php').read_text(encoding='utf-8')
install=(ROOT/'install.php').read_text(encoding='utf-8')
layout=(ROOT/'includes/panel_layout.php').read_text(encoding='utf-8')
for retired in ['name="tagline"','name="currency"','name="table_sessions_enabled"','name="waiter_show_prices"','name="waiter_notify_orders"','name="closed_message"','name="menu_font"','name="ui_font"']:
    assert retired not in settings, retired
for retired_key in ["'tagline' =>", "'currency' => 'تومان'", "'closed_message' =>", "'table_sessions_enabled' =>", "'waiter_show_prices' =>", "'waiter_notify_orders' =>", "'menu_font' =>", "'ui_font' =>"]:
    assert retired_key not in install, retired_key
assert 'function table_sessions_enabled(): bool' in functions and 'return true;' in functions[functions.index('function table_sessions_enabled'):functions.index('function valid_hex_color')]
for owner in ['settingsGuestFeatures','settingsGuestMessages','settingsOperationalPreferences','settingsIntegrationLinks','settingsSystemOptions','settingsDeviceTools']:
    assert f'id="{owner}"' in settings, owner
assert 'messages.php' in settings and 'accommodation_settings.php' in settings and 'push_devices.php' in settings
assert "'messages'=>'settings'" in layout and "'push'=>'settings'" in layout and "'accommodation_settings'=>'settings'" in layout
assert 'ارتباط HTTPS، احراز هویت و پاسخ API اقامتگاه تأیید شد.' not in settings
print('Settings cleanup contract passed: dead/duplicate controls are gone, table sessions are invariant, and specialized tools live under their correct Settings owners.')
