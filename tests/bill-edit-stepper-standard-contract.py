from pathlib import Path
root=Path(__file__).resolve().parents[1]
page=(root/'includes/operator_page.php').read_text()
js=(root/'assets/js/operator.js').read_text()
css=(root/'assets/css/operator-live.css').read_text()
panel=(root/'assets/css/panel-components.css').read_text()
assert 'data-stepper-for="billItemPreparedQuantity"' in page
assert 'data-bill-prepared-step' not in page
assert 'data-bill-prepared-step' not in js
assert "preparedInput.max=String(removed)" in js
assert '.bill-edit-modal .quantity-stepper{display:grid;grid-template-columns:44px 70px 44px' in css
assert '.bill-edit-prepared-stepper{min-width:168px}' not in panel
print('Bill edit stepper standard contract PASS: final/prepared use one stepper interaction and geometry owner.')
