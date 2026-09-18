#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
layout=read('includes/panel_layout.php')
core=read('assets/js/panel-core.js')
personnel=read('admin/personnel.php')

# Navigation visibility and route authorization must use the same business policy.
# Admin keeps the management launcher; staff sees Personnel & Payroll only after explicit Center allow.
assert 'if ($isAdmin)' in layout, 'admin management launcher branch missing'
assert "($personnelState['state'] ?? '') === 'allow'" in layout, 'explicit allow branch missing'
assert "elseif (($personnelState['state'] ?? '') === 'unknown')" in layout, 'unknown refresh branch missing'
unknown=layout.split("elseif (($personnelState['state'] ?? '') === 'unknown')",1)[1].split('}',1)[0]
assert "'hidden'=>true" in unknown, 'unknown staff entitlement must render hidden'
assert "'refresh'=>true" in unknown, 'unknown staff entitlement must refresh non-blockingly'
assert "'hidden'=>false" not in unknown, 'unknown staff entitlement must never be visible by compatibility fallback'

# Browser refresh can reveal only explicit allow; deny/unsupported/failure stay hidden.
assert 'if (data?.supported !== true)' in core
unsupported=core.split('if (data?.supported !== true)',1)[1].split('}',1)[0]
assert 'link.hidden = true' in unsupported, 'unsupported entitlement must remain hidden'
assert "if (data.allowed === true)" in core and 'link.hidden = false' in core, 'explicit allow reveal missing'
refresh=core.split('CafeUI.refreshPersonnelEntitlement = async () => {',1)[1].split('void CafeUI.refreshPersonnelEntitlement()',1)[0]
assert 'catch (_)' in refresh, 'refresh failure handling missing'
catch=refresh.split('catch (_)',1)[1].split('}',1)[0]
assert 'return null' in catch, 'refresh failure must not invent access'

# The sidebar remains fail-closed as a visibility hint, but the launcher itself must not depend
# on Probe availability. Center is the final authorization source for the signed handoff.
assert 'sokna_center_test_connection(' not in personnel, 'probe must not be a handoff prerequisite'
assert 'sokna_center_handoff_target()' in personnel and 'sokna_center_handoff_token(' in personnel, 'direct signed handoff launcher missing'
assert '$personnelAccess' not in personnel, 'local entitlement hint must not become launcher authorization'

print('Navigation entitlement parity passed: admin launcher retained; staff allow-only; unknown/deny/failure hidden and direct route fail-closed.')
