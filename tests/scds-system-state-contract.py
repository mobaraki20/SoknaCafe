from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
menu=(ROOT/'menu/index.php').read_text('utf-8')
css=(ROOT/'assets/css/scds-system-state.css').read_text('utf-8')
checks={
 'shared system state stylesheet loaded twice': menu.count("assets/css/scds-system-state.css")==2,
 'no legacy inline system-state style blocks': 'font-family:Tahoma,Arial,sans-serif' not in menu and '<style>body{margin:0;min-height:100vh;display:grid;place-items:center' not in menu,
 'Persian product title': 'QR نامعتبر | سکنا' in menu and 'QR نامعتبر | Sokna' not in menu,
 'canonical touch target': 'min-height:var(--scds-touch-target)' in css,
 'canonical tokens only': '#f7f3ec' not in css and '#fff' not in css and '#365b4c' not in css,
 'mobile narrow contract': '@media(max-width:360px)' in css,
}
failed=[name for name,ok in checks.items() if not ok]
if failed: raise SystemExit('FAIL SCDS system state contract: '+', '.join(failed))
print('PASS SCDS system state contract: '+', '.join(checks))
