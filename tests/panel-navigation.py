#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
layout = (ROOT / 'includes/panel_layout.php').read_text(encoding='utf-8')
shell_js = (ROOT / 'assets/js/panel-shell.js').read_text(encoding='utf-8')
menus_js = (ROOT / 'assets/js/panel-menus.js').read_text(encoding='utf-8')
css = (ROOT / 'assets/css/panel-layout.css').read_text(encoding='utf-8')

checks = [
    ('data-panel-nav-toggle', layout, 'The main panel navigation toggle is not addressable.'),
    ('aria-controls="sidebar"', layout, 'The main navigation toggle is not associated with the sidebar.'),
    ("ui_icon('more')", layout, 'The secondary tools button still looks like the main hamburger menu.'),
    ('id="panelToolsPopover" role="menu"', layout, 'The tools popover is not exposed as a menu.'),
    ('panel-sidebar-open', shell_js, 'Opening the sidebar does not lock the background document.'),
    ('sidebarFocusable', shell_js, 'The mobile sidebar has no keyboard focus boundary.'),
    ("event.key !== 'Tab'", shell_js, 'The sidebar does not keep keyboard focus inside the open navigation.'),
    ("event.key === 'Escape'", shell_js, 'Escape handling is missing.'),
    ('aria-expanded', shell_js, 'Expanded state is not synchronized.'),
    ('closeTools', shell_js, 'The secondary tools menu has no standard close behavior.'),
    ('.panel-body.panel-sidebar-open{overflow:hidden', css, 'The open mobile sidebar does not lock page scrolling.'),
]
for needle, source, message in checks:
    if needle not in source:
        raise SystemExit(message)
if shell_js.count("const sidebar = document.getElementById('sidebar')") != 1:
    raise SystemExit('Panel shell must have exactly one sidebar owner.')
if "panel-shell.js" not in layout:
    raise SystemExit('Panel shell bootstrap is not loaded by the shared layout.')
if layout.find('panel-shell.js') > layout.find('id="panelContent"'):
    raise SystemExit('Critical panel shell is still loaded after page content.')
if "panel-navigation.js" in layout:
    raise SystemExit('Legacy navigation owner is still loaded by the shared layout.')
if (ROOT / 'assets/js/panel-navigation.js').exists():
    raise SystemExit('Replaced legacy panel-navigation.js still exists; panel-shell.js must be the only shell owner.')
if 'panelToolsToggle' in menus_js:
    raise SystemExit('Topbar tools still have a second controller in panel-menus.js.')
if (ROOT / 'assets/js/panel-ui.js').exists():
    raise SystemExit('The removed monolithic panel-ui.js still exists.')
if 'onclick="document.getElementById(\'sidebar\')' in layout:
    raise SystemExit('The navigation toggle still uses an unmanaged inline click handler.')
print('Panel navigation structure passed: distinct controls, scroll lock, focus management and accessible state.')
