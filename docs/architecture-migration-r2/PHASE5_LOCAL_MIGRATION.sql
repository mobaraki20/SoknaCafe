CREATE TABLE IF NOT EXISTS expense_categories (
    category_key VARCHAR(64) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    system_category TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_expense_category_name (name),
    INDEX idx_expense_category_active (active,sort_order,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO expense_categories(category_key,name,active,system_category,sort_order) VALUES
('rent','اجاره',1,1,10),
('utilities','آب، برق و انرژی',1,1,20),
('maintenance','تعمیر و نگهداری',1,1,30),
('transport','حمل‌ونقل',1,1,40),
('services','خدمات',1,1,50),
('equipment','تجهیزات',1,1,60),
('other','سایر',1,1,70)
ON DUPLICATE KEY UPDATE category_key=VALUES(category_key);

CREATE TABLE IF NOT EXISTS expenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    financial_period_id INT UNSIGNED NOT NULL,
    category_key VARCHAR(64) NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    description VARCHAR(500) NULL,
    occurred_at DATETIME NOT NULL,
    committed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actor_user_id INT UNSIGNED NULL,
    source_request_id VARCHAR(96) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'committed',
    reverses_expense_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_expense_period FOREIGN KEY (financial_period_id) REFERENCES financial_periods(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_expense_category FOREIGN KEY (category_key) REFERENCES expense_categories(category_key) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_expense_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_expense_reversal FOREIGN KEY (reverses_expense_id) REFERENCES expenses(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_expense_source_request (source_request_id),
    UNIQUE KEY uq_expense_reversal (reverses_expense_id),
    INDEX idx_expense_period_occurred (financial_period_id,occurred_at),
    INDEX idx_expense_category_occurred (category_key,occurred_at),
    INDEX idx_expense_status_occurred (status,occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deferred_work_receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    installation_id VARCHAR(96) NOT NULL,
    request_id VARCHAR(96) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    kind VARCHAR(64) NOT NULL,
    actor_projection_id VARCHAR(96) NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    occurred_at DATETIME NOT NULL,
    envelope_json JSON NOT NULL,
    state VARCHAR(20) NOT NULL,
    result_json JSON NULL,
    error_code VARCHAR(80) NULL,
    financial_period_id INT UNSIGNED NULL,
    public_reconcile_pending TINYINT(1) NOT NULL DEFAULT 0,
    committed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_deferred_receipt_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_deferred_receipt_period FOREIGN KEY (financial_period_id) REFERENCES financial_periods(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_deferred_receipt_request (installation_id,request_id),
    INDEX idx_deferred_receipt_state (state,updated_at),
    INDEX idx_deferred_receipt_period (financial_period_id,state,occurred_at),
    INDEX idx_deferred_receipt_reconcile (public_reconcile_pending,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deferred_review_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_id BIGINT UNSIGNED NOT NULL,
    review_type VARCHAR(32) NOT NULL,
    financial_period_id INT UNSIGNED NULL,
    reason_code VARCHAR(80) NOT NULL,
    message VARCHAR(500) NOT NULL,
    state VARCHAR(20) NOT NULL DEFAULT 'pending',
    resolved_by_user_id INT UNSIGNED NULL,
    resolution_reason VARCHAR(500) NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_deferred_review_receipt FOREIGN KEY (receipt_id) REFERENCES deferred_work_receipts(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_deferred_review_period FOREIGN KEY (financial_period_id) REFERENCES financial_periods(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_deferred_review_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_deferred_review_receipt (receipt_id),
    INDEX idx_deferred_review_state (state,review_type,created_at),
    INDEX idx_deferred_review_period (financial_period_id,state,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_period_close_overrides (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    financial_period_id INT UNSIGNED NOT NULL,
    actor_user_id INT UNSIGNED NOT NULL,
    reason VARCHAR(500) NOT NULL,
    public_status_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_period_close_override_period FOREIGN KEY (financial_period_id) REFERENCES financial_periods(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_period_close_override_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_period_close_override_period (financial_period_id,created_at),
    INDEX idx_period_close_override_actor (actor_user_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
