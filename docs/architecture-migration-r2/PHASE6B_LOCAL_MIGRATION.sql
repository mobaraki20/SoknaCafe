-- SOKNA 1.36.4-dev.33 / Phase 6B
-- Explicit sellable kind. Additive; no historical order row rewrite.

ALTER TABLE items
  ADD COLUMN IF NOT EXISTS sellable_kind VARCHAR(20) NOT NULL DEFAULT 'menu_item' AFTER staff_only;

UPDATE items
SET sellable_kind='service_item'
WHERE item_code IN ('SERVICE-TAKEAWAY','SERVICE-CAKE')
  AND sellable_kind<>'service_item';

ALTER TABLE order_items
  ADD COLUMN IF NOT EXISTS sellable_kind_snapshot VARCHAR(20) NULL AFTER item_name;
