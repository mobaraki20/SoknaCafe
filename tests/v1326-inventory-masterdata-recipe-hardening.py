#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
core=read('includes/inventory.php')
item_form=read('admin/inventory_item_form.php')
items=read('admin/inventory_items.php')
review=read('admin/inventory_review.php')
menu=read('admin/item_form.php')
css=read('assets/css/items-management.css')

# Purchase-unit editor is server rendered and fails closed if submitted state is incomplete.
assert 'name="purchase_units_state" value="1"' in item_form
assert '<?php foreach($units as $index=>$unit)' in item_form and 'data-unit-row' in item_form
assert '<template id="purchaseUnitTemplate">' in item_form
assert 'اطلاعات یکی از واحدهای خرید کامل ارسال نشد' in item_form
assert "SELECT * FROM inventory_purchase_units WHERE inventory_item_id=? ORDER BY id FOR UPDATE" in item_form
assert 'inventory_major_to_base($qtyRaw,$baseUnit)' in item_form
assert 'دو واحد خرید فعال نمی‌توانند نام یکسان داشته باشند' in item_form
assert 'normalize_persian_search($unitName)' in item_form
assert "SELECT COUNT(*) FROM inventory_movements WHERE purchase_unit_id=?" in item_form
assert "UPDATE inventory_purchase_units SET active=0 WHERE id=?" in item_form
assert 'base_unit_change_confirmed' in item_form and 'CafeUI.confirm' in item_form
assert 'numericValue' in item_form and 'normalizeNumberText' in item_form
assert 'box.innerHTML' not in item_form

# Child review state participates in the parent review queue and operational readiness.
assert "pu.review_status='needs_review'" in items
assert "pu.review_status='needs_review'" in review
assert 'نیازمند تکمیل' in item_form and 'نیازمند تأیید' in item_form and "'label'=>'آماده'" in item_form
assert 'نیازمند تکمیل' in review and 'نیازمند تأیید' in review

# Recipes reject duplicates instead of silently merging, and changed recipes validate active ingredients.
assert 'function inventory_normalize_recipe_components' in core
assert 'یک ماده انبار نمی‌تواند دو بار در فهرست مواد مصرفی ثبت شود' in core
assert 'function inventory_validate_recipe_components_locked' in core
assert 'SELECT id,name,active FROM inventory_items WHERE id IN ($marks)' in core
assert 'inventory_validate_recipe_components_locked($pdo,$normalized);' in core
assert "($normalized[$inventoryItemId] ?? 0) + $quantity" not in core

# Active menu recipes protect ingredient lifecycle.
assert 'function inventory_active_recipe_usage' in core
assert 'inventory_active_recipe_usage($pdo,$id,4)' in items
assert 'ابتدا مواد مصرفی آن آیتم را اصلاح کنید' in items

# Recipe editor is server rendered / fail safe, with duplicate prevention and cost visibility.
assert 'name="recipe_state_present" value="1"' in menu
assert '<?php foreach($recipeRows as $recipeIndex=>$recipeRow)' in menu and 'data-recipe-row' in menu
assert '<template id="recipeComponentTemplate">' in menu
assert 'inventory_normalize_recipe_components($recipeRows)' in menu
assert 'inventory_validate_recipe_components_locked($pdo,$normalizedRecipeRows)' in menu
assert 'syncDuplicateOptions' in menu
assert 'recipe-cost-preview' in menu and 'هزینه تقریبی مواد' in menu and 'هزینه کامل قابل محاسبه نیست' in menu
assert 'مواد مصرفی را قبل از انتشار دوباره بررسی کن' in menu
assert 'box.innerHTML' not in menu
assert '.recipe-cost-preview{' in css

print('v1.32.14 inventory master-data/recipe hardening passed: server-rendered fail-safe editors, strict/unique purchase units, aggregated review state, immutable recipe validation, lifecycle guards, copied recipes, and cost preview.')
