#!/usr/bin/env python3
import hashlib
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(relative: str) -> str:
    return (ROOT / relative).read_text(encoding='utf-8')


def need(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


version=read('VERSION.txt').strip(); need(bool(version), 'current release identity')
need(f"const RELEASE='{version}'" in read('service-worker.js'), 'current service-worker release')

php_sources = '\n'.join(path.read_text(encoding='utf-8') for path in ROOT.rglob('*.php') if 'tests' not in path.parts)
need('csp_nonce(' not in php_sources, 'undefined CSP nonce call returned')

functions = read('includes/functions.php')
modules = read('includes/modules.php')
need('function render_recovery_error_page(' in functions, 'shared recoverable 404/409 owner')
need("'error' => $status === 404 ? 'not_found' : 'not_ready'" in functions, 'JSON recovery contract')
need(modules.count('render_recovery_error_page(') == 2, 'module route gates use the recovery owner')

operator = read('assets/js/operator.js')
need(operator.count("addEventListener('change',handleBillDeleteReasonChange)") == 1, 'invoice delete-reason handler is bound once')

choice = read('assets/js/panel-choice.js')
purchases_js = read('assets/js/supply-purchases.js')
need("new CustomEvent('panel:choice-commit'" in choice and 'changed,' in choice, 'choice commit fires even when value is unchanged')
need("addEventListener('panel:choice-commit'" in purchases_js, 'purchase quantity follows explicit unit commit')
need("aria-controls', resolvedMode(select) === 'embedded' ? ''" not in choice, 'choice trigger has no empty aria-controls')

menu = read('assets/js/menu.js')
guest_css = read('assets/css/guest-menu.css')
need("button.className = 'menu-item-hit'" in menu, 'product card has a real full-area button')
need("card.removeAttribute('role')" in menu and "card.removeAttribute('tabindex')" in menu, 'product article does not impersonate a button')
need('.menu-item-hit' in guest_css and '.inline-qty' in guest_css, 'card hit area and independent quantity controls are styled')
need('const setBackgroundInert' in menu and 'node.inert = value' in menu, 'guest background is inert while an overlay is open')

purchases_php = read('admin/purchases.php')
need('<div class="panel-confirm-backdrop" data-dialog-backdrop aria-hidden="true"></div>' in purchases_php, 'non-closeable purchase backdrop is non-interactive')
need('for="purchaseUnitCount"' in purchases_php, 'purchase quantity has a programmatic label')

count_php = read('admin/inventory_count.php')
count_js = read('assets/js/inventory-form-flow.js')
need('data-inventory-count-filter="empty"' in count_php, 'inventory count has an uncounted filter')
need('data-inventory-count-next' in count_php and 'sessionStorage.setItem(storageKey' in count_js, 'inventory count supports next-item and position recovery')

printing = read('admin/printing.php')
need("['all','waiting','submitted','cancelled']" in printing, 'print queue status filters')
need('$queuePerPage=20' in printing and 'class="panel-pagination"' in printing, 'print queue pagination')

shell_js = read('assets/js/panel-shell.js')
layout_css = read('assets/css/panel-layout.css')
need("matchMedia('(max-width: 1180px)')" in shell_js, 'sidebar JS breakpoint')
need(layout_css.count('@media(max-width:1180px)') >= 2, 'sidebar CSS breakpoint')

items_php = read('admin/items.php')
items_css = read('assets/css/items-management.css')
need('$perPage = 50' in items_php and 'class="panel-pagination' in items_php, 'item list is paginated in bounded batches')
need('container-type:inline-size' in items_css and '@container(max-width:1090px)' in items_css, 'item rows compact by workspace width')

css_important_count = sum(path.read_text(encoding='utf-8').count('!important') for path in (ROOT / 'assets/css').glob('*.css'))
# dev.5 baseline already contains 285 !important usages; dev.6 must not increase that debt.
need(css_important_count <= 285, f'CSS escalation count regressed: {css_important_count}')
inventory_css = read('assets/css/inventory.css')
need(inventory_css.count('.purchase-receive-form{') == 1, 'purchase receive form has one base owner')

font = ROOT / 'assets/fonts/Vazirmatn-Variable.woff2'
font_license = ROOT / 'assets/fonts/OFL.txt'
font_runtime = read('includes/font_runtime.php')
need(font.is_file() and font.stat().st_size == 111_152, 'official bundled Vazirmatn font')
need(font.read_bytes()[:4] == b'wOF2', 'bundled Vazirmatn has WOFF2 signature')
need(
    hashlib.sha256(font.read_bytes()).hexdigest() == '4e3fa217d38fdafc1fea4414ceb58ca5e662cf0ab5fa735a8c8c20e8b42cad92',
    'bundled Vazirmatn v33.003 hash',
)
need(font_license.is_file() and 'SIL OPEN FONT LICENSE Version 1.1' in font_license.read_text(encoding='utf-8'), 'bundled Vazirmatn OFL license')
need("if (is_file($absolute))" in functions and "assets/fonts/Vazirmatn-Variable.woff2" in functions, 'UI head prefers the bundled local font')
need(font_runtime.count('v33.003') == 2 and 'vazirmatn_font_valid($target)' in font_runtime, 'runtime recovery remains pinned and validates the local font')
print('Bundled Vazirmatn v33.003 font and license PASS.')

print(f'Sokna {version} developer UI handoff contract PASS.')
