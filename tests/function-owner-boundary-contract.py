#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
read = lambda p: (ROOT / p).read_text(encoding='utf-8')

agg = read('includes/functions.php')
jalali = read('includes/function_domains/jalali.php')
media = read('includes/function_domains/media.php')
favicon = read('includes/function_domains/favicon.php')
audit = read('includes/function_domains/audit.php')
messages = read('includes/function_domains/messages.php')
events = read('includes/function_domains/events.php')

assert "require_once __DIR__ . '/function_domains/jalali.php';" in agg
assert "require_once __DIR__ . '/function_domains/media.php';" in agg
assert "require_once __DIR__ . '/function_domains/favicon.php';" in agg
assert "require_once __DIR__ . '/function_domains/audit.php';" in agg
assert "require_once __DIR__ . '/function_domains/messages.php';" in agg
assert "require_once __DIR__ . '/function_domains/events.php';" in agg

for fn in ('app_timezone','gregorian_to_jalali','jalali_to_gregorian','parse_optional_jalali_day_boundary','format_jalali_human_datetime'):
    assert f'function {fn}' in jalali
    assert f'function {fn}' not in agg

for fn in ('upload_image','responsive_image_data','image_library_entries','assert_square_uploaded_image','resolve_image_input','image_picker_html'):
    assert f'function {fn}' in media
    assert f'function {fn}' not in agg

for fn in ('favicon_default_paths','favicon_paths','favicon_revision','favicon_url','favicon_save_payload','favicon_delete_paths'):
    assert f'function {fn}' in favicon
    assert f'function {fn}' not in agg

for fn in ('menu_item_audit_snapshot','menu_item_important_changes','audit_actor_display_snapshot','audit_log_insert','audit_log_write','audit_log_write_strict'):
    assert f'function {fn}' in audit
    assert f'function {fn}' not in agg

for fn in ('message_definitions','message_definition_map','customer_message','customer_message_enabled','public_customer_messages'):
    assert f'function {fn}' in messages
    assert f'function {fn}' not in agg

for fn in ('event_display_fee_amount','event_display_admission_text','event_registration_label','event_effective_end','event_lifecycle_status','event_lifecycle_label'):
    assert f'function {fn}' in events
    assert f'function {fn}' not in agg

# Extracted filesystem owners must resolve project root explicitly after moving one directory deeper.
for source in (media, favicon):
    assert 'dirname(__DIR__, 2)' in source
    assert 'dirname(__DIR__)' not in source

# The aggregator remains the stable include contract; callers should not include function_domains directly.
for base in ('admin','api','operator','staff','waiter','modules'):
    for path in (ROOT/base).rglob('*.php'):
        text = path.read_text(encoding='utf-8')
        assert 'function_domains/' not in text, path

print('Function owner boundary contract PASS: Jalali, Media, Favicon, Audit, Messages and Events have canonical owners while functions.php remains the stable aggregator.')
