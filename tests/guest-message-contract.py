#!/usr/bin/env python3
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
functions=(ROOT/'includes/functions.php').read_text(encoding='utf-8')
message_owner=(ROOT/'includes/function_domains/messages.php').read_text(encoding='utf-8')
index=((ROOT/'menu/index.php').read_text(encoding='utf-8') + '\n' + (ROOT/'includes/guest_menu_view.php').read_text(encoding='utf-8'))
menu=(ROOT/'assets/js/menu.js').read_text(encoding='utf-8')
messages=(ROOT/'admin/messages.php').read_text(encoding='utf-8')
region=message_owner[message_owner.index('function message_definitions'):message_owner.index('function message_definition_map')]
keys=re.findall(r"\['key'=>'([^']+)'",region)
assert len(keys)==len(set(keys)) and len(keys)>=45
# Technical/dead definitions may remain in the registry for stable API defaults, but must not be editable.
for key in ('invalid_qr','page_expired','visit_duration'):
    row=next(line for line in region.splitlines() if f"'key'=>'{key}'" in line)
    assert "'editable'=>false" in row, key
# Every editable key must have a real current consumer, not merely a hidden/dead editor node.
noneditable={'invalid_qr','page_expired','visit_duration'}
runtime='\n'.join([index,menu]+[(ROOT/p).read_text(encoding='utf-8') for p in [
    'api/create_order.php','includes/guest_order_service.php',
    'api/guest_orders.php','includes/guest_order_manage_service.php',
    'api/waiter_call.php','includes/waiter_call_service.php',
    'api/table_context.php','api/order_status.php','includes/guest_order_status_service.php',
    'admin/settings.php','includes/push.php'
]])
unused=[k for k in keys if k not in noneditable and k not in runtime and f"order_acceptance_message('{k.removeprefix('ordering_pause_')}')" not in index]
assert not unused, 'Editable message definitions without a real runtime consumer: '+', '.join(unused)
# Previously broken editors now have explicit consumers.
assert "msg('no_results'" in menu
assert "msg('order_received_title'" in menu
assert "msg('duplicate_order'" in menu
for key in ('submit_order','submit_order_add','submit_order_update'):
    assert f"msg('{key}'" in menu, key
assert "customer_message('item_note_placeholder')" in index
assert "customer_message('post_order_social_cta')" in index
assert "customer_message('item_unavailable')" in index and "msg('item_unavailable'" in menu
for key in ('ordering_pause_cafe','ordering_pause_kitchen','ordering_pause_bar'):
    assert key in region
assert "return customer_message('ordering_pause_' . $scope);" in functions
assert "if (($definition['editable'] ?? true) !== true) continue;" in messages
assert 'data-message-search' in messages and 'data-message-filter="changed"' in messages
assert 'data-insert-token' in messages and 'data-message-preview' in messages
assert "guest_message.reset" in messages and 'نسخه استاندارد' in messages
assert "متغیر ناشناخته" in messages and "باید حفظ شود" in messages
print('Guest message contract passed: runtime consumers remain real, noneditable system copy stays protected, and Messages v2 adds scalable search/filter/reset/token guardrails.')
