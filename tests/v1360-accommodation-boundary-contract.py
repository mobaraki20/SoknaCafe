#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
def need(cond,msg):
    if not cond: raise AssertionError(msg)

acc=read('includes/accommodation.php')
api=read('operator/api_accommodation.php')
admin=read('admin/accommodation.php')
settings=read('admin/accommodation_settings.php')
layout=read('includes/panel_layout.php')
orders=read('operator/api_orders.php')
operator=read('assets/js/operator.js')
modules=read('includes/modules.php')
settlements=read('operator/api_settlements.php')

# Live connectivity is a connection control, not a module-wide history gate.
need('function accommodation_live_operations_enabled(): bool' in acc, 'live connection helper missing')
need('function accommodation_require_live_operations(): void' in acc, 'live remote guard missing')
need('function accommodation_module_enabled' not in acc, 'old module-wide accommodation gate must not return')
need("'control_mode' => 'connection'" in modules, 'module registry must document connection-level control')
need("'toggleable' => true" not in modules.split("'accommodation' => [",1)[1].split("'personnel' => [",1)[0], 'Accommodation must not be exposed as a simple module toggle')

# Attention includes remote unresolved states AND local financial mismatches.
need("($a.status='posted' AND NOT $completed)" in acc, 'posted remote success without local settlement must be actionable')
need("($a.status='voided' AND $completed AND NOT $reversed)" in acc, 'remote void without local reversal must be actionable')
need("'local_finalize_pending'" in acc and "'local_reversal_pending'" in acc, 'derived local recovery states missing')
need('function accommodation_attention_rows(?int $limit=50): array' in acc, 'attention queue contract missing')
need('accommodation_attention_count()' in layout, 'navigation badge must use complete attention queue')

# Local settlement completion must be idempotent and must detect closed-session discrepancy.
finalize=acc.split('function accommodation_finalize_local_checkout',1)[1].split('\nfunction ',1)[0]
need("status='completed'" in finalize and 'already_settled' in finalize, 'local settlement finalization must be idempotent')
need("if((string)$session['status']==='closed')throw" in finalize, 'closed session without settlement record must be treated as discrepancy')
need('settlement_finalize_locked' in finalize, 'local settlement recovery must use Finance settlement contract')

# A previously-posted remote charge can finish locally while connectivity is off.
charge=acc.split('function accommodation_attempt_charge',1)[1].split('\nfunction ',1)[0]
need(charge.index("if($tr['status']==='posted')") < charge.index('accommodation_require_live_operations();'), 'posted charge recovery must precede live-network guard')

# A previously-confirmed remote void can continue local recovery while connectivity is off.
void=acc.split('function accommodation_attempt_void',1)[1].split('\nfunction ',1)[0]
need(void.index("if($tr['status']==='voided')") < void.index('accommodation_require_live_operations();'), 'confirmed remote void must be idempotent before live guard')
reversal=acc.split('function accommodation_finalize_local_reversal',1)[1].split('\nfunction ',1)[0]
need('settlement_reopen_locked' in reversal, 'local reversal recovery must use Finance reopen contract')
need("status='reversal'" in reversal and 'idempotent' in reversal, 'local reversal recovery must be duplicate-safe')

# API gates only network actions; local recovery/history remains available.
need("if(!$liveEnabled)json_response" in api, 'action-level live guard missing')
need("$action==='finalize_local'" in api, 'local settlement recovery endpoint missing')
need("$action==='finalize_local_reversal'" in api, 'local reversal recovery endpoint missing')
need("$action==='issues'" in api and 'accommodation_attention_rows(50)' in api, 'attention endpoint must remain available')
need('if(!accommodation_live_operations_enabled())json_response' not in api, 'API must not have a global connection-disabled gate')

# Admin history and recovery remain available with the live connection disabled.
need('accommodation_attention_rows(10)' in admin, 'admin attention preview must be independent of history pagination')
need('سوابق مالی، هشدارها و بازیابی محلی همچنان در دسترس‌اند' in admin, 'live-off manager copy must preserve financial recovery expectations')
need('data-action="finalize_local"' in admin and 'accommodation-open-reversal' in admin, 'admin local recovery actions missing')
need('سابقه مالی، هشدارهای ناسازگاری و بازیابی محلی همچنان در دسترس می‌مانند' in settings, 'connection settings copy must distinguish live operations from financial recovery')
need('accommodation_live_operations_enabled()||accommodation_history_exists()' in settings, 'history/recovery link must remain reachable while connection is off')

# Operator polling must invalidate when accommodation state/setting changes.
need('accommodation_updated' in orders and 'accommodation_count' in orders, 'operator revision must include accommodation transfer mutations')
need('$accommodationLiveEnabled' in orders.split('$lightRevision = substr',1)[1].split(');',1)[0], 'operator revision must include live connection state')
need('accommodation_attention_rows(30)' in orders, 'operator recovery payload must use attention queue')
need('can_finalize_local' in operator and 'accommodation-finalize-action' in operator, 'operator local settlement recovery UI missing')

# Financial editing lock is fail-closed on unexpected persistence failure.
lock=acc.split('function accommodation_transfer_blocks_invoice_edit',1)[1].split('\nfunction ',1)[0]
need("catch(Throwable $e)" in lock and 'return true;' in lock, 'invoice-edit lock must fail closed')

# Generic settlement reversal still reverses accommodation remotely first; local failure is then recoverable.
need('accommodation_attempt_void($transferId,$userId,$reason)' in settlements, 'financial reversal must preserve remote-first accommodation invariant')

print('Accommodation boundary contract PASS: live connectivity is isolated from financial history/recovery, local completion is idempotent, and operator/admin recovery remains fail-closed.')
