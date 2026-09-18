#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
js=(ROOT/'assets/js/panel-time-picker.js').read_text(encoding='utf-8')
css=(ROOT/'assets/css/panel-components.css').read_text(encoding='utf-8')

def need(ok,msg):
    if not ok: raise SystemExit('1.32.14 time picker contract FAILED: '+msg)

need("data-panel-time-period=\"am\"" in js and "data-panel-time-period=\"pm\"" in js,'AM/PM controls missing')
need('قبل از ظهر' in js and 'بعد از ظهر' in js,'Persian period labels missing')
need('toTwelveHour' in js and 'toTwentyFourHour' in js,'12h presentation / 24h canonical conversion owner missing')
need("hour === 12 ? 0 : hour" in js,'12 AM conversion is not explicit')
need("hour === 12 ? 12 : hour + 12" in js,'12 PM conversion is not explicit')
need("Array.from({ length: 12 }, (_, index) => index + 1)" in js,'hour grid is not 1..12')
need('.panel-time-grid{display:grid;grid-template-columns:repeat(4' in css,'four-column time grid missing')
need('.panel-time-grid' in css and 'direction:ltr' in css,'numeric time grid must be LTR')
need('[data-panel-time-period="am"]{grid-column:2' in css and '[data-panel-time-period="pm"]{grid-column:1' in css,'period placement contract missing: AM right / PM left')
need('max-height:255px' not in css and 'max-height:230px' not in css,'legacy nested time-grid scroll caps still present')
print('1.32.14 time picker contract PASS')
