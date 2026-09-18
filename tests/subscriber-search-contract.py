#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
s=(ROOT/'includes/subscribers.php').read_text(encoding='utf-8')
assert "$hasDigits = $mobile !== '' && preg_match('/\\d/', en_digits($query)) === 1" in s
assert "if ($hasDigits)" in s and "strlen($mobile) < 3" in s
assert "AND s.mobile_normalized LIKE ?" in s
assert "if (text_length($query) < 2) return [];" in s
assert "AND s.name LIKE ?" in s
assert "['%' . $query . '%', $query, $query . '%']" in s
# The old bug was an unconditional mobile LIKE %% on text searches.
text_branch=s.split("if ($hasDigits)",1)[1].split("function subscriber_invoice_snapshot_locked",1)[0]
assert "mobile_normalized LIKE ?" not in text_branch.split("if (text_length($query) < 2)",1)[1]
print('Subscriber search contract passed: names and mobile numbers have separate predicates, minimum lengths and ranked results; text cannot produce LIKE %% on mobile.')
