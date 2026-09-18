#!/usr/bin/env python3
from pathlib import Path
import re
import tinycss2

FILES=[Path('assets/css/app.css'),Path('assets/css/responsive.css'),Path('assets/css/panel.css')]
ROOTS=(
 '.sidebar','.brand-block','.brand-mark','.brand-copy','.side-nav','.panel-shell','.panel-main',
 '.panel-topbar','.panel-page','.panel-eyebrow','.topbar-','.panel-role-pill','.panel-clock',
 '.mobile-menu','.panel-content','.quick-order-launch','.panel-subnav'
)

def owned(selector:str)->bool:
 s=' '.join(selector.strip().split())
 if s=='.panel-body' or s.startswith('.panel-body.panel-sidebar-open'):
  return True
 if s.startswith('.panel-body .sidebar'):
  return True
 return s.startswith(ROOTS)

def clean_rules(rules,removed):
 out=[]
 for rule in rules:
  if rule.type=='qualified-rule':
   selectors=[x.strip() for x in tinycss2.serialize(rule.prelude).split(',')]
   keep=[x for x in selectors if not owned(x)]
   removed.extend(x for x in selectors if owned(x))
   if keep:
    rule.prelude=tinycss2.parse_component_value_list(','.join(keep));out.append(rule)
  elif rule.type=='at-rule' and rule.content is not None and rule.lower_at_keyword in {'media','supports','layer','container'}:
   inner=clean_rules(tinycss2.parse_rule_list(rule.content,skip_whitespace=False,skip_comments=False),removed)
   if any(x.type in {'qualified-rule','at-rule'} for x in inner):
    rule.content=tinycss2.parse_component_value_list(tinycss2.serialize(inner));out.append(rule)
  else: out.append(rule)
 return out

for path in FILES:
 removed=[]
 rules=tinycss2.parse_stylesheet(path.read_text(),skip_whitespace=False,skip_comments=False)
 path.write_text(tinycss2.serialize(clean_rules(rules,removed)))
 print(path, len(removed))
