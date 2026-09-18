#!/usr/bin/env python3
from pathlib import Path
import re

root=Path(__file__).resolve().parents[1]
read=lambda p:(root/p).read_text(encoding='utf-8')

core=read('assets/js/panel-core.js')
operator=read('assets/js/operator.js')
choice=read('assets/js/panel-choice.js')
ui=read('docs/UI_DESIGN_SYSTEM_FA.md')

# Shared owner must distinguish virtual-keyboard intent from validation and respect IME composition.
assert 'CafeUI.keyboard' in core
assert "event.isComposing" in core
assert "hint === 'next'" in core and "hint === 'done'" in core
assert 'focusNextKeyboardField' in core and 'dismissVirtualKeyboard' in core
assert 'applyKeyboardHints' in core and "form.hasAttribute('data-enter-native')" in core
assert "data-keyboard-dismiss-on-enter" in core

# AJAX settlement lookups use one action for button/Enter, dismiss only after a valid query,
# and never duplicate the search implementation.
assert 'runSettlementSearchAction' in operator
assert "CafeUI.keyboard?.dismissForTouch?.(input)" in operator
assert "runSettlementSearchAction('subscriberSearch',searchSubscribers)" in operator
assert "runSettlementSearchAction('accommodationSearchQuery',searchAccommodation)" in operator
assert "e.key==='Enter'&&!e.isComposing" in operator

# Browse Choice has its own search semantics but shares the keyboard owner.
assert 'enterkeyhint="search"' in choice and 'inputmode="search"' in choice
assert 'window.CafeUI?.keyboard?.isTouchContext?.()' in choice
assert 'window.CafeUI.keyboard.dismiss(searchInput)' in choice

# Every panel search input that opts into type=search must also carry keyboard semantics.
panel_php=[]
for base in ('admin','operator','staff','includes'):
    for path in (root/base).rglob('*.php'):
        if 'updater_engine' in path.parts:
            continue
        panel_php.append(path)
for path in panel_php:
    text=path.read_text(encoding='utf-8')
    for tag in re.findall(r'<input\b[^>]*type="search"[^>]*>',text,re.I|re.S):
        assert 'inputmode="search"' in tag, (path,tag)
        assert 'enterkeyhint="search"' in tag, (path,tag)

# Numeric/decimal panel controls must declare an Enter intent so mobile keyboards are predictable.
for path in panel_php:
    text=path.read_text(encoding='utf-8')
    for tag in re.findall(r'<input\b[^>]*inputmode="(?:numeric|decimal)"[^>]*>',text,re.I|re.S):
        assert 'enterkeyhint=' in tag, (path,tag)

# High-value semantic fields: telephone is not treated as a generic number; live searches dismiss on Enter.
settings=read('admin/settings.php')
subscribers=read('includes/subscribers_page.php')
assert 'type="tel" inputmode="tel" enterkeyhint="next" autocomplete="tel" name="public_phone"' in settings
assert 'type="tel" inputmode="tel" enterkeyhint="next" autocomplete="tel" name="whatsapp_number"' in settings
assert 'type="tel" inputmode="tel" autocomplete="tel" name="mobile"' in subscribers
quick=read('staff/quick-order.php')
media=read('includes/function_domains/media.php')
assert 'id="quickOrderSearch" type="search" inputmode="search" enterkeyhint="search" data-keyboard-dismiss-on-enter' in quick
assert 'image-library-search" type="search" inputmode="search" enterkeyhint="search" data-keyboard-dismiss-on-enter' in media

registry=read('tests/defect_class_registry.json')
assert '"id": "virtual_keyboard_semantics"' in registry

# Standards register the same behavior; no page-specific folklore.
for needle in ('inputmode=numeric','inputmode=search','enterkeyhint=search','KeyboardEvent.isComposing=true','UAT_REQUIRED'):
    assert needle in ui, needle

print('Panel keyboard/input contract passed: semantic input modes, Search/Next/Done Enter behavior, IME guard and shared touch keyboard owner are present.')
