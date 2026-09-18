#!/usr/bin/env python3
from pathlib import Path
import json, subprocess, urllib.parse, re
ROOT=Path(__file__).resolve().parents[1]
version=(ROOT/'VERSION.txt').read_text().strip()
code="""$d=require 'includes/help_topics.php'; echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);"""
out=subprocess.check_output(['php','-r',code],cwd=ROOT,text=True)
data=json.loads(out)
assert data.get('reviewed_for_version')==version, (data.get('reviewed_for_version'),version)
topics=data.get('topics',[])
assert len(topics)>=38, len(topics)
ids=[]
required={'quick-order','tables-management','guest-qr-order','backup','restore','recovery-center','analytics','invoices','subscribers','business-day-shifts','carryover-session','date-time-pickers','operations-report'}
for t in topics:
    assert isinstance(t,dict) and t.get('id') and t.get('title') and t.get('body')
    ids.append(t['id'])
    assert isinstance(t['body'],list) and len(t['body'])>=2
    p=t.get('path')
    if p:
        path=urllib.parse.urlparse(p).path.lstrip('/')
        target=ROOT/path
        if path.endswith('/'):
            target=target/'index.php'
        assert target.exists(), f"broken help route {p} -> {target}"
assert len(ids)==len(set(ids)), 'duplicate help topic ids'
assert required.issubset(ids), required-set(ids)

by_id={t['id']:t for t in topics}
assert 'روز عملیاتی' in ' '.join(by_id['business-day-shifts']['body'])
assert '۱ تا ۳ شیفت' in ' '.join(by_id['business-day-shifts']['body'])
assert 'خودکار نمی‌بندد' in ' '.join(by_id['carryover-session']['body'])
assert 'روز عملیاتی' in ' '.join(by_id['analytics']['body'])
assert '۰۰:۰۰ تا ۲۳:۵۹' in ' '.join(by_id['operations-report']['body'])
assert 'انتخابگر پیش‌فرض مرورگر' in ' '.join(by_id['date-time-pickers']['body'])
assert '۲۸ نسخه اخیر' in ' '.join(by_id['backup']['body']) and '۶ ساعت' in ' '.join(by_id['backup']['body'])
assert 'تأیید سفارش' in ' '.join(by_id['pending-guest-order']['body']) and 'رد سفارش' in ' '.join(by_id['pending-guest-order']['body'])
assert 'Audit' not in ' '.join(' '.join(t['body']) for t in topics)
assert 'Snapshot' not in ' '.join(' '.join(t['body']) for t in topics)
assert 'Owner' not in ' '.join(' '.join(t['body']) for t in topics)
assert 'نقطه بازگشت داخلی' in ' '.join(by_id['restore']['body'])
help_php=(ROOT/'help.php').read_text()
for needle in ['reviewed_for_version','const normalize =','const tokens =','topic=','مسیرهای سریع']:
    assert needle in help_php, needle
print(f'Help Center contracts passed: {len(topics)} topics, version {version}, all internal routes exist.')
