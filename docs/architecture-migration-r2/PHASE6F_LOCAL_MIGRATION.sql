-- SOKNA R2 Phase 6F — optional Tax domain
-- Prices remain pre-tax. Existing history remains tax=0; line final_amount is backfilled from net_amount.
CREATE TABLE tax_rate_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rate_bps SMALLINT UNSIGNED NOT NULL,
    effective_from DATETIME NOT NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tax_rate_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_tax_rate_effective (effective_from,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- CAFE-STMT --
CREATE TABLE tax_item_policy_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL,
    policy VARCHAR(24) NOT NULL DEFAULT 'inherit_default',
    custom_rate_bps SMALLINT UNSIGNED NULL,
    effective_from DATETIME NOT NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tax_item_policy_item FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tax_item_policy_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_tax_item_policy_effective (item_id,effective_from,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- CAFE-STMT --
ALTER TABLE order_items
    ADD COLUMN tax_policy_snapshot VARCHAR(24) NOT NULL DEFAULT 'disabled' AFTER line_total,
    ADD COLUMN tax_rate_bps_snapshot SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER tax_policy_snapshot,
    ADD COLUMN tax_rate_version_id BIGINT UNSIGNED NULL AFTER tax_rate_bps_snapshot,
    ADD COLUMN tax_item_policy_version_id BIGINT UNSIGNED NULL AFTER tax_rate_version_id,
    ADD CONSTRAINT fk_order_items_tax_rate FOREIGN KEY (tax_rate_version_id) REFERENCES tax_rate_versions(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_order_items_tax_policy FOREIGN KEY (tax_item_policy_version_id) REFERENCES tax_item_policy_versions(id) ON UPDATE CASCADE ON DELETE RESTRICT;
-- CAFE-STMT --
ALTER TABLE table_sessions
    ADD COLUMN checkout_taxable BIGINT UNSIGNED NULL AFTER checkout_discount,
    ADD COLUMN checkout_tax BIGINT UNSIGNED NULL AFTER checkout_taxable;
-- CAFE-STMT --
ALTER TABLE settlement_records
    ADD COLUMN taxable_amount BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER discount,
    ADD COLUMN tax_amount BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER taxable_amount,
    ADD COLUMN remaining_tax BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER remaining_discount;
-- CAFE-STMT --
ALTER TABLE settlement_record_lines
    ADD COLUMN taxable_amount BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER net_amount,
    ADD COLUMN tax_rate_bps SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER taxable_amount,
    ADD COLUMN tax_amount BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER tax_rate_bps,
    ADD COLUMN final_amount BIGINT UNSIGNED NULL AFTER tax_amount;
-- CAFE-STMT --
UPDATE settlement_record_lines SET final_amount=net_amount WHERE final_amount IS NULL;
-- CAFE-STMT --
ALTER TABLE settlement_record_lines MODIFY COLUMN final_amount BIGINT UNSIGNED NOT NULL;
-- CAFE-STMT --
INSERT INTO settings(setting_key,setting_value) VALUES('module.tax.enabled','0') ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);
