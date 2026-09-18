from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text()
version=(R/'VERSION.txt').read_text().strip(); assert version
layout=read('includes/panel_layout.php'); login=read('login.php'); sw=read('service-worker.js')
choice=read('assets/js/panel-choice.js'); time=read('assets/js/panel-time-picker.js'); core=read('assets/js/panel-core.js'); shell=read('assets/js/panel-shell.js'); menus=read('assets/js/panel-menus.js')
assert "assets/js/interaction-modality.js" in layout and layout.index('interaction-modality.js') < layout.index('panel-shell.js')
assert "assets/js/interaction-modality.js" in login
assert "trigger.focus({preventScroll:true})" not in login
assert "let interactionModality = 'keyboard'" not in choice
assert "window.CafeUI?.interaction" in choice and "window.CafeUI?.interaction" in time
assert "keyboardInteraction" in core and "keyboardInteraction" in shell and "keyboardInteraction" in menus
assert "interaction-modality.js" in sw and f"const RELEASE='{version}'" in sw
css=read('assets/css/panel.css')+read('assets/css/panel-components.css')
assert '-webkit-tap-highlight-color:transparent' in css
assert 'html[data-input-modality="pointer"] .panel-choice-trigger[aria-expanded="true"]' in css
print(f'PASS {version} shared touch/focus structural contract')
