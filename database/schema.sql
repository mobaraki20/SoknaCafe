CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(40) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'operator',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role_active (role, active)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_capabilities (
    user_id INT UNSIGNED NOT NULL,
    capability VARCHAR(50) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, capability),
    CONSTRAINT fk_user_capabilities_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_user_capability_enabled (capability, enabled, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_preparation_areas (
    user_id INT UNSIGNED NOT NULL,
    area_key VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, area_key),
    CONSTRAINT fk_user_preparation_area_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_user_preparation_area (area_key, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_notification_preferences (
    user_id INT UNSIGNED NOT NULL,
    preference_key VARCHAR(40) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, preference_key),
    CONSTRAINT fk_user_notification_preference_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_user_notification_preference_enabled (preference_key, enabled, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS menus (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    menu_key VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    sort_order INT NOT NULL DEFAULT 0,
    schedule_days VARCHAR(30) NULL,
    daily_start TIME NULL,
    daily_end TIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_menus_key (menu_key),
    INDEX idx_menus_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_key VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    audience VARCHAR(20) NOT NULL DEFAULT 'guest_staff',
    image_path VARCHAR(255) NULL,
    icon_key VARCHAR(40) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categories_key (category_key),
    INDEX idx_categories_active_sort (active, sort_order),
    INDEX idx_categories_audience (audience, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_categories (
    menu_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (menu_id, category_id),
    CONSTRAINT fk_menu_categories_menu FOREIGN KEY (menu_id) REFERENCES menus(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_menu_categories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_menu_categories_order (menu_id, sort_order, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(80) NULL UNIQUE,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    description TEXT NULL,
    price BIGINT UNSIGNED NOT NULL DEFAULT 0,
    image_path VARCHAR(255) NULL,
    available TINYINT(1) NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    cancelled_at DATETIME NULL,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    staff_only TINYINT(1) NOT NULL DEFAULT 0,
    takeaway_allowed TINYINT(1) NOT NULL DEFAULT 1,
    preparation_station VARCHAR(30) NOT NULL DEFAULT 'other',
    schedule_start DATETIME NULL,
    schedule_end DATETIME NULL,
    schedule_days VARCHAR(30) NULL,
    daily_start TIME NULL,
    daily_end TIME NULL,
    suggested_item_id INT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_items_menu (category_id, active, available, sort_order),
    INDEX idx_items_featured (featured, active),
    INDEX idx_items_schedule (active, schedule_start, schedule_end),
    CONSTRAINT fk_items_suggested FOREIGN KEY (suggested_item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_items (
    menu_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (menu_id, item_id),
    CONSTRAINT fk_menu_items_menu FOREIGN KEY (menu_id) REFERENCES menus(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_menu_items_item FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_menu_items_item (item_id, menu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cafe_tables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    table_number SMALLINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    access_token VARCHAR(80) NOT NULL UNIQUE,
    previous_access_token VARCHAR(80) NULL,
    qr_rotated_at DATETIME NULL,
    qr_rotated_by_user_id INT UNSIGNED NULL,
    zone_label VARCHAR(80) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tables_number (table_number),
    UNIQUE KEY uq_tables_previous_token (previous_access_token),
    INDEX idx_tables_active_sort (active, sort_order),
    INDEX idx_tables_number_active (table_number, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS table_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_token VARCHAR(80) NOT NULL UNIQUE,
    table_id INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    live_table_guard INT UNSIGNED NULL,
    guest_count SMALLINT UNSIGNED NULL,
    continued_from_session_id BIGINT UNSIGNED NULL,
    note VARCHAR(500) NULL,
    discount_type VARCHAR(20) NULL,
    discount_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    discount_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    discount_by_user_id INT UNSIGNED NULL,
    discount_updated_at DATETIME NULL,
    checkout_subtotal BIGINT UNSIGNED NULL,
    checkout_discount BIGINT UNSIGNED NULL,
    checkout_total BIGINT UNSIGNED NULL,
    settlement_destination VARCHAR(30) NULL,
    checkout_voided_at DATETIME NULL,
    checkout_voided_by_user_id INT UNSIGNED NULL,
    opened_by_user_id INT UNSIGNED NULL,
    closed_by_user_id INT UNSIGNED NULL,
    ended_reason VARCHAR(40) NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    business_date DATE NOT NULL,
    business_shift_key VARCHAR(40) NOT NULL,
    business_shift_label VARCHAR(80) NOT NULL,
    business_cutoff_snapshot CHAR(5) NOT NULL,
    ended_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_table_sessions_table FOREIGN KEY (table_id) REFERENCES cafe_tables(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_table_sessions_opened_by FOREIGN KEY (opened_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_table_sessions_closed_by FOREIGN KEY (closed_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_table_sessions_discount_by FOREIGN KEY (discount_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_table_sessions_checkout_voided_by FOREIGN KEY (checkout_voided_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_table_sessions_one_live_table (live_table_guard),
    INDEX idx_table_sessions_table_status (table_id, status, started_at),
    INDEX idx_table_sessions_status_started (status, started_at),
    INDEX idx_table_sessions_business_day (business_date,business_shift_key,started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS table_session_clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    device_token VARCHAR(80) NOT NULL,
    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_session_clients_session FOREIGN KEY (session_id) REFERENCES table_sessions(id) ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_session_device (session_id, device_token),
    INDEX idx_session_clients_seen (session_id, first_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_code VARCHAR(32) NOT NULL UNIQUE,
    client_token VARCHAR(80) NOT NULL UNIQUE,
    device_token VARCHAR(80) NULL,
    table_id INT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NULL,
    order_source VARCHAR(20) NOT NULL DEFAULT 'guest',
    status VARCHAR(30) NOT NULL DEFAULT 'new',
    customer_note TEXT NULL,
    total_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    source_ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    accepted_at DATETIME NULL,
    accepted_by_user_id INT UNSIGNED NULL,
    created_by_user_id INT UNSIGNED NULL,
    business_order_number INT UNSIGNED NOT NULL,
    business_date DATE NOT NULL,
    business_shift_key VARCHAR(40) NOT NULL,
    business_shift_label VARCHAR(80) NOT NULL,
    business_cutoff_snapshot CHAR(5) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_table FOREIGN KEY (table_id) REFERENCES cafe_tables(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_orders_session FOREIGN KEY (session_id) REFERENCES table_sessions(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_orders_accepted_by FOREIGN KEY (accepted_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_orders_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_orders_status_created (status, created_at),
    INDEX idx_orders_table_created (table_id, created_at),
    INDEX idx_orders_session_created (session_id, created_at),
    INDEX idx_orders_source_created (order_source, created_at),
    UNIQUE KEY uq_orders_business_number (business_date,business_order_number),
    INDEX idx_orders_business_day (business_date,business_shift_key,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_business_sequences (
    business_date DATE PRIMARY KEY,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NULL,
    item_name VARCHAR(160) NOT NULL,
    unit_price BIGINT UNSIGNED NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    ordered_quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    adjustment_reason VARCHAR(300) NULL,
    adjusted_by_user_id INT UNSIGNED NULL,
    adjusted_at DATETIME NULL,
    item_note VARCHAR(500) NULL,
    fulfillment_mode VARCHAR(16) NOT NULL DEFAULT 'dine_in',
    preparation_station VARCHAR(30) NOT NULL DEFAULT 'other',
    line_total BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_order_items_item FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_order_items_adjusted_by FOREIGN KEY (adjusted_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_order_items_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_item_adjustments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_item_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NULL,
    previous_quantity SMALLINT UNSIGNED NOT NULL,
    new_quantity SMALLINT UNSIGNED NOT NULL,
    reason VARCHAR(300) NOT NULL,
    prepared_removed_quantity SMALLINT UNSIGNED NULL,
    actor_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_item_adjustment_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_item_adjustment_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_item_adjustment_session FOREIGN KEY (session_id) REFERENCES table_sessions(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_item_adjustment_user FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_item_adjustment_order (order_id,created_at),
    INDEX idx_item_adjustment_session (session_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS preparation_adjustments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_item_adjustment_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NULL,
    table_id INT UNSIGNED NOT NULL,
    area_key VARCHAR(20) NOT NULL,
    item_name VARCHAR(160) NOT NULL,
    previous_quantity SMALLINT UNSIGNED NOT NULL,
    new_quantity SMALLINT UNSIGNED NOT NULL,
    reason VARCHAR(300) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'pending_delivery',
    delivered_at DATETIME NULL,
    acknowledged_at DATETIME NULL,
    acknowledged_by_user_id INT UNSIGNED NULL,
    applied_at DATETIME NULL,
    applied_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_prep_adjustment_source FOREIGN KEY (order_item_adjustment_id) REFERENCES order_item_adjustments(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_prep_adjustment_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_prep_adjustment_session FOREIGN KEY (session_id) REFERENCES table_sessions(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_prep_adjustment_table FOREIGN KEY (table_id) REFERENCES cafe_tables(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_prep_adjustment_ack_user FOREIGN KEY (acknowledged_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_prep_adjustment_apply_user FOREIGN KEY (applied_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_prep_adjustment_area (order_item_adjustment_id, area_key),
    INDEX idx_prep_adjustment_queue (area_key,status,created_at),
    INDEX idx_prep_adjustment_session (session_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    endpoint_hash CHAR(64) NOT NULL UNIQUE,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    auth_key VARCHAR(255) NOT NULL,
    device_label VARCHAR(120) NULL,
    user_agent VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_success_at DATETIME NULL,
    last_error_at DATETIME NULL,
    last_error_message VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_push_subscription_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_push_subscription_user_active (user_id, active, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_event_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(40) NOT NULL,
    request_id VARCHAR(80) NULL,
    event_key VARCHAR(160) NULL,
    payload_json JSON NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_at DATETIME NULL,
    sent_at DATETIME NULL,
    last_error VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_push_event_queue_ready (status,available_at,id),
    INDEX idx_push_event_queue_created (created_at,status),
    UNIQUE KEY uq_push_event_queue_event_key (event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_event_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_http_status SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_push_delivery_queue FOREIGN KEY (queue_id) REFERENCES push_event_queue(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_push_delivery_subscription FOREIGN KEY (subscription_id) REFERENCES push_subscriptions(id) ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_push_delivery_target (queue_id,subscription_id),
    INDEX idx_push_delivery_retry (queue_id,status,attempt_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_action_claims (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nonce_hash CHAR(64) NOT NULL,
    delivery_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    action_name VARCHAR(40) NOT NULL,
    subject_type VARCHAR(40) NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    claimed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_push_action_nonce (nonce_hash),
    INDEX idx_push_action_delivery (delivery_id,claimed_at),
    CONSTRAINT fk_push_action_delivery FOREIGN KEY (delivery_id) REFERENCES push_event_deliveries(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_push_action_subscription FOREIGN KEY (subscription_id) REFERENCES push_subscriptions(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_push_action_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_delivery_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(40) NOT NULL,
    http_status SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    success TINYINT(1) NOT NULL DEFAULT 0,
    error_message VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_push_log_subscription FOREIGN KEY (subscription_id) REFERENCES push_subscriptions(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_push_log_created (created_at, success),
    INDEX idx_push_log_subscription (subscription_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_preparation_claims (
    order_id BIGINT UNSIGNED NOT NULL,
    area_key VARCHAR(20) NOT NULL,
    claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claimed_by_user_id INT UNSIGNED NULL,
    item_signature CHAR(64) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (order_id, area_key),
    CONSTRAINT fk_order_preparation_claim_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_order_preparation_claim_user FOREIGN KEY (claimed_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_order_preparation_claim_area (area_key, claimed_at),
    INDEX idx_order_preparation_claim_user (claimed_by_user_id, claimed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_discount_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    previous_type VARCHAR(20) NULL,
    previous_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    previous_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    new_type VARCHAR(20) NULL,
    new_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    new_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    subtotal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    actor_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_discount_audit_session FOREIGN KEY (session_id) REFERENCES table_sessions(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_discount_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_discount_audit_session (session_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(30) NULL,
    to_status VARCHAR(30) NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_status_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_status_history_user FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_status_history_order_created (order_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS financial_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(80) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    next_invoice_sequence INT UNSIGNED NOT NULL DEFAULT 1,
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    opened_by_user_id INT UNSIGNED NULL,
    closed_at DATETIME NULL,
    closed_by_user_id INT UNSIGNED NULL,
    close_summary_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_financial_period_opened_by FOREIGN KEY (opened_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_financial_period_closed_by FOREIGN KEY (closed_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_financial_period_dates (start_date,end_date),
    INDEX idx_financial_period_status (status,start_date,end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscribers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    mobile VARCHAR(30) NOT NULL,
    mobile_normalized VARCHAR(20) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id INT UNSIGNED NULL,
    updated_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_subscriber_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_subscriber_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_subscriber_mobile (mobile_normalized),
    INDEX idx_subscriber_active_name (active,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriber_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscriber_id BIGINT UNSIGNED NOT NULL,
    financial_period_id INT UNSIGNED NULL,
    entry_type VARCHAR(20) NOT NULL,
    amount_delta BIGINT NOT NULL,
    balance_after BIGINT NOT NULL,
    table_session_id BIGINT UNSIGNED NULL,
    related_entry_id BIGINT UNSIGNED NULL,
    reference VARCHAR(120) NULL,
    reason VARCHAR(300) NULL,
    invoice_snapshot_json JSON NULL,
    actor_user_id INT UNSIGNED NULL,
    idempotency_key VARCHAR(190) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_subscriber_ledger_subscriber FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_subscriber_ledger_period FOREIGN KEY (financial_period_id) REFERENCES financial_periods(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_subscriber_ledger_session FOREIGN KEY (table_session_id) REFERENCES table_sessions(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_subscriber_ledger_related FOREIGN KEY (related_entry_id) REFERENCES subscriber_ledger(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_subscriber_ledger_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_subscriber_reversal_entry (related_entry_id),
    UNIQUE KEY uq_subscriber_ledger_idempotency (idempotency_key),
    INDEX idx_subscriber_ledger_account (subscriber_id,created_at),
    INDEX idx_subscriber_ledger_period (financial_period_id,created_at),
    INDEX idx_subscriber_ledger_session (table_session_id),
    INDEX idx_subscriber_ledger_type (entry_type,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accommodation_transfers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    financial_period_id INT UNSIGNED NULL,
    external_order_id VARCHAR(80) NOT NULL,
    reservation_code VARCHAR(80) NOT NULL,
    guest_name_snapshot VARCHAR(160) NOT NULL,
    room_name_snapshot VARCHAR(240) NOT NULL,
    phone_hint_snapshot VARCHAR(40) NULL,
    amount BIGINT UNSIGNED NOT NULL,
    invoice_snapshot_json JSON NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    remote_transaction_id VARCHAR(120) NULL,
    remote_void_transaction_id VARCHAR(120) NULL,
    remote_original_transaction_id VARCHAR(120) NULL,
    operator_user_id INT UNSIGNED NULL,
    print_final_requested TINYINT(1) NOT NULL DEFAULT 0,
    void_requested_by_user_id INT UNSIGNED NULL,
    voided_by_user_id INT UNSIGNED NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_attempt_at DATETIME NULL,
    posted_at DATETIME NULL,
    voided_at DATETIME NULL,
    last_error_code VARCHAR(80) NULL,
    last_error VARCHAR(500) NULL,
    suspicious_response TINYINT(1) NOT NULL DEFAULT 0,
    resolved_at DATETIME NULL,
    resolved_by_user_id INT UNSIGNED NULL,
    resolution_method VARCHAR(40) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_accommodation_transfer_session FOREIGN KEY (session_id) REFERENCES table_sessions(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_accommodation_transfer_period FOREIGN KEY (financial_period_id) REFERENCES financial_periods(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_accommodation_transfer_operator FOREIGN KEY (operator_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_accommodation_transfer_void_requested FOREIGN KEY (void_requested_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_accommodation_transfer_voided_by FOREIGN KEY (voided_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_accommodation_transfer_resolved_by FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_accommodation_transfer_session (session_id),
    UNIQUE KEY uq_accommodation_external_order (external_order_id),
    INDEX idx_accommodation_transfer_status (status, updated_at),
    INDEX idx_accommodation_transfer_period (financial_period_id,created_at),
    INDEX idx_accommodation_transfer_reservation (reservation_code, created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settlement_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    financial_period_id INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(40) NOT NULL,
    invoice_snapshot_json JSON NOT NULL,
    destination VARCHAR(30) NOT NULL,
    table_name_snapshot VARCHAR(160) NOT NULL,
    subtotal BIGINT UNSIGNED NOT NULL,
    discount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    reverses_settlement_id BIGINT UNSIGNED NULL,
    actor_user_id INT UNSIGNED NULL,
    subscriber_ledger_entry_id BIGINT UNSIGNED NULL,
    accommodation_transfer_id BIGINT UNSIGNED NULL,
    final_print_job_id BIGINT UNSIGNED NULL,
    request_id VARCHAR(96) NULL,
    request_fingerprint CHAR(64) NULL,
    settlement_kind VARCHAR(20) NOT NULL DEFAULT 'full',
    closes_session TINYINT(1) NOT NULL DEFAULT 1,
    remaining_subtotal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    remaining_discount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    remaining_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
    allocation_version SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    settled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    business_date DATE NOT NULL,
    business_shift_key VARCHAR(40) NOT NULL,
    business_shift_label VARCHAR(80) NOT NULL,
    business_cutoff_snapshot CHAR(5) NOT NULL,
    void_reason VARCHAR(300) NULL,
    voided_by_user_id INT UNSIGNED NULL,
    voided_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settlement_record_session FOREIGN KEY (session_id) REFERENCES table_sessions(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_settlement_record_period FOREIGN KEY (financial_period_id) REFERENCES financial_periods(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_settlement_record_reverses FOREIGN KEY (reverses_settlement_id) REFERENCES settlement_records(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_settlement_record_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_settlement_record_subscriber_entry FOREIGN KEY (subscriber_ledger_entry_id) REFERENCES subscriber_ledger(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_settlement_record_accommodation FOREIGN KEY (accommodation_transfer_id) REFERENCES accommodation_transfers(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_settlement_record_voided_by FOREIGN KEY (voided_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_settlement_invoice_number (invoice_number),
    UNIQUE KEY uq_settlement_reversal (reverses_settlement_id),
    UNIQUE KEY uq_settlement_request_id (request_id),
    INDEX idx_settlement_record_today (settled_at,status),
    INDEX idx_settlement_business_day (business_date,business_shift_key,status),
    INDEX idx_settlement_record_period (financial_period_id,settled_at),
    INDEX idx_settlement_record_session (session_id,settled_at),
    INDEX idx_settlement_record_destination (destination,settled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settlement_record_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    settlement_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED NULL,
    order_id BIGINT UNSIGNED NULL,
    item_id_snapshot INT UNSIGNED NULL,
    item_name_snapshot VARCHAR(190) NOT NULL,
    unit_price_snapshot BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    gross_amount BIGINT UNSIGNED NOT NULL,
    discount_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    net_amount BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_settlement_line_record FOREIGN KEY (settlement_id) REFERENCES settlement_records(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_settlement_line_item (settlement_id,order_item_id),
    INDEX idx_settlement_line_order_item (order_item_id),
    INDEX idx_settlement_line_item_snapshot (item_id_snapshot),
    INDEX idx_settlement_line_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NULL,
    actor_display_name_snapshot VARCHAR(160) NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id VARCHAR(100) NULL,
    details_json JSON NULL,
    source_ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_log_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_audit_entity (entity_type, entity_id, created_at),
    INDEX idx_audit_action_created (action, created_at),
    INDEX idx_audit_actor_created (actor_user_id, created_at),
    INDEX idx_audit_created (created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS inventory_categories (
    category_key VARCHAR(64) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    system_category TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inventory_category_name (name),
    INDEX idx_inventory_category_active (active,sort_order,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO inventory_categories(category_key,name,active,system_category,sort_order) VALUES
('ingredient','مواد اولیه',1,1,10),
('ready_drink','نوشیدنی آماده',1,1,20),
('ready_food','خوراکی آماده',1,1,30),
('packaging','بسته‌بندی و یک‌بارمصرف',1,1,40),
('consumable','ملزومات مصرفی',1,1,50)
ON DUPLICATE KEY UPDATE category_key=VALUES(category_key);

CREATE TABLE IF NOT EXISTS inventory_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    category VARCHAR(64) NOT NULL DEFAULT 'ingredient',
    base_unit VARCHAR(12) NOT NULL DEFAULT 'count',
    default_department VARCHAR(20) NOT NULL DEFAULT 'shared',
    warning_threshold BIGINT UNSIGNED NOT NULL DEFAULT 0,
    review_status VARCHAR(20) NOT NULL DEFAULT 'ready',
    review_note VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_items_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_inventory_items_category FOREIGN KEY (category) REFERENCES inventory_categories(category_key) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_inventory_items_active_name (active,name),
    INDEX idx_inventory_items_review (review_status,active),
    INDEX idx_inventory_items_category (category,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_purchase_units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_item_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    conversion_mode VARCHAR(24) NOT NULL DEFAULT 'fixed',
    base_quantity BIGINT UNSIGNED NULL,
    review_status VARCHAR(20) NOT NULL DEFAULT 'ready',
    note VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_purchase_unit_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_inventory_purchase_units_item (inventory_item_id,active,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_supply_needs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_item_id INT UNSIGNED NULL,
    item_name_snapshot VARCHAR(160) NOT NULL,
    base_unit VARCHAR(12) NOT NULL,
    requested_quantity_base BIGINT UNSIGNED NOT NULL,
    fulfilled_quantity_base BIGINT UNSIGNED NOT NULL DEFAULT 0,
    preparing_quantity_base BIGINT UNSIGNED NOT NULL DEFAULT 0,
    preparing_by_user_id INT UNSIGNED NULL,
    preparing_at DATETIME NULL,
    department VARCHAR(20) NOT NULL DEFAULT 'shared',
    source VARCHAR(20) NOT NULL DEFAULT 'staff',
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    last_outcome VARCHAR(24) NULL,
    note VARCHAR(500) NULL,
    created_by_user_id INT UNSIGNED NULL,
    updated_by_user_id INT UNSIGNED NULL,
    closed_by_user_id INT UNSIGNED NULL,
    closed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    open_item_guard VARCHAR(255) NULL,
    CONSTRAINT fk_inventory_supply_need_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_supply_need_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_inventory_supply_need_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_inventory_supply_need_preparing_by FOREIGN KEY (preparing_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_inventory_supply_need_closed_by FOREIGN KEY (closed_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_inventory_supply_need_open_item (open_item_guard),
    INDEX idx_inventory_supply_need_status_department (status,department,updated_at,id),
    INDEX idx_inventory_supply_need_item (inventory_item_id,status,updated_at),
    INDEX idx_inventory_supply_need_preparing (status,preparing_quantity_base,preparing_at,id),
    INDEX idx_inventory_supply_need_creator (created_by_user_id,status,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_balances (
    inventory_item_id INT UNSIGNED PRIMARY KEY,
    quantity_base BIGINT NOT NULL DEFAULT 0,
    average_unit_cost DECIMAL(20,6) NULL,
    cost_status VARCHAR(20) NOT NULL DEFAULT 'unknown',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_balance_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_item_id INT UNSIGNED NOT NULL,
    movement_type VARCHAR(32) NOT NULL,
    quantity_base BIGINT NOT NULL,
    base_unit VARCHAR(12) NOT NULL,
    department VARCHAR(20) NULL,
    purchase_unit_id INT UNSIGNED NULL,
    purchase_unit_name_snapshot VARCHAR(160) NULL,
    purchase_unit_count DECIMAL(14,3) NULL,
    conversion_base_quantity_snapshot BIGINT UNSIGNED NULL,
    unit_cost_snapshot DECIMAL(20,6) NULL,
    total_cost_delta BIGINT NULL,
    cost_status VARCHAR(20) NOT NULL DEFAULT 'unknown',
    source_type VARCHAR(50) NULL,
    source_id VARCHAR(100) NULL,
    correction_of_id BIGINT UNSIGNED NULL,
    reversal_of_id BIGINT UNSIGNED NULL,
    idempotency_key VARCHAR(190) NULL,
    metadata_json JSON NULL,
    note VARCHAR(500) NULL,
    actor_user_id INT UNSIGNED NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_movement_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_movement_purchase_unit FOREIGN KEY (purchase_unit_id) REFERENCES inventory_purchase_units(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_inventory_movement_correction FOREIGN KEY (correction_of_id) REFERENCES inventory_movements(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_movement_reversal FOREIGN KEY (reversal_of_id) REFERENCES inventory_movements(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_movement_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_inventory_movement_idempotency (idempotency_key),
    INDEX idx_inventory_movement_item_created (inventory_item_id,created_at),
    INDEX idx_inventory_movement_type_created (movement_type,created_at),
    INDEX idx_inventory_movement_source (source_type,source_id),
    INDEX idx_inventory_movement_department (department,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_supply_receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supply_need_id BIGINT UNSIGNED NOT NULL,
    inventory_item_id INT UNSIGNED NOT NULL,
    requested_quantity_snapshot BIGINT UNSIGNED NOT NULL,
    received_quantity_base BIGINT UNSIGNED NOT NULL,
    remaining_quantity_after BIGINT UNSIGNED NOT NULL DEFAULT 0,
    purchase_unit_id INT UNSIGNED NULL,
    purchase_unit_name_snapshot VARCHAR(160) NULL,
    purchase_unit_count DECIMAL(14,3) NULL,
    conversion_base_quantity_snapshot BIGINT UNSIGNED NULL,
    total_cost BIGINT UNSIGNED NULL,
    supplier VARCHAR(160) NULL,
    note VARCHAR(500) NULL,
    request_token CHAR(32) NOT NULL,
    movement_id BIGINT UNSIGNED NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_supply_receipt_need FOREIGN KEY (supply_need_id) REFERENCES inventory_supply_needs(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_supply_receipt_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_supply_receipt_unit FOREIGN KEY (purchase_unit_id) REFERENCES inventory_purchase_units(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_inventory_supply_receipt_movement FOREIGN KEY (movement_id) REFERENCES inventory_movements(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_supply_receipt_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_inventory_supply_receipt_request (request_token),
    INDEX idx_inventory_supply_receipt_need (supply_need_id,created_at,id),
    INDEX idx_inventory_supply_receipt_item (inventory_item_id,created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_supply_receipt_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_id BIGINT UNSIGNED NOT NULL,
    supply_need_id BIGINT UNSIGNED NOT NULL,
    allocated_quantity_base BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_supply_allocation_receipt FOREIGN KEY (receipt_id) REFERENCES inventory_supply_receipts(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_supply_allocation_need FOREIGN KEY (supply_need_id) REFERENCES inventory_supply_needs(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_inventory_supply_allocation_receipt_need (receipt_id,supply_need_id),
    INDEX idx_inventory_supply_allocation_need (supply_need_id,receipt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_recipe_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    menu_item_id INT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by_user_id INT UNSIGNED NULL,
    retired_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_recipe_menu_item FOREIGN KEY (menu_item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_recipe_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_inventory_recipe_version (menu_item_id,version_no),
    INDEX idx_inventory_recipe_active (menu_item_id,status,version_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_recipe_components (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipe_version_id BIGINT UNSIGNED NOT NULL,
    inventory_item_id INT UNSIGNED NOT NULL,
    quantity_base BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_recipe_component_version FOREIGN KEY (recipe_version_id) REFERENCES inventory_recipe_versions(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_recipe_component_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_inventory_recipe_component (recipe_version_id,inventory_item_id),
    INDEX idx_inventory_recipe_component_item (inventory_item_id,recipe_version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_count_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    session_type VARCHAR(20) NOT NULL DEFAULT 'periodic',
    scope_type VARCHAR(20) NOT NULL DEFAULT 'full',
    scope_category_key VARCHAR(64) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    snapshot_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    finalized_by_user_id INT UNSIGNED NULL,
    finalized_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_count_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_inventory_count_finalized_by FOREIGN KEY (finalized_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_inventory_count_status (status,created_at),
    INDEX idx_inventory_count_type (session_type,created_at),
    INDEX idx_inventory_count_scope (scope_type,scope_category_key,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_count_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    inventory_item_id INT UNSIGNED NOT NULL,
    system_quantity_snapshot BIGINT NOT NULL DEFAULT 0,
    unit_cost_snapshot DECIMAL(20,6) NULL,
    actual_quantity BIGINT UNSIGNED NULL,
    actual_total_cost BIGINT UNSIGNED NULL,
    difference_base BIGINT NULL,
    note VARCHAR(500) NULL,
    counted_by_user_id INT UNSIGNED NULL,
    counted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_count_line_session FOREIGN KEY (session_id) REFERENCES inventory_count_sessions(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_count_line_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_count_line_user FOREIGN KEY (counted_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_inventory_count_line (session_id,inventory_item_id),
    INDEX idx_inventory_count_line_item (inventory_item_id,session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_order_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(30) NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED NULL,
    payload_json JSON NULL,
    idempotency_key VARCHAR(190) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NULL,
    actor_user_id INT UNSIGNED NULL,
    processed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_order_event_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_order_event_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_inventory_order_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_inventory_order_event_idempotency (idempotency_key),
    INDEX idx_inventory_order_event_pending (status,id),
    INDEX idx_inventory_order_event_order (order_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS print_agents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    token_hint VARCHAR(16) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    retired_at DATETIME NULL,
    retired_by_user_id INT UNSIGNED NULL,
    hostname VARCHAR(190) NULL,
    agent_version VARCHAR(40) NULL,
    os_version VARCHAR(190) NULL,
    printers_json JSON NULL,
    health_json JSON NULL,
    bridge_protocol_version TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bridge_port INT UNSIGNED NOT NULL DEFAULT 0,
    bridge_pairing_id VARCHAR(128) NULL,
    bridge_origin VARCHAR(240) NULL,
    bridge_runtime_seen_at DATETIME NULL,
    uptime_seconds BIGINT UNSIGNED NULL,
    last_poll_success_at DATETIME NULL,
    local_backlog_count INT UNSIGNED NOT NULL DEFAULT 0,
    local_unknown_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_submission_at DATETIME NULL,
    sqlite_health VARCHAR(30) NULL,
    disk_free_mb BIGINT UNSIGNED NULL,
    last_heartbeat_at DATETIME NULL,
    last_seen_at DATETIME NULL,
    last_error VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_print_agent_retired_by FOREIGN KEY (retired_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_print_agents_active_seen (active,last_seen_at),
    INDEX idx_print_agents_active_heartbeat (active,retired_at,last_heartbeat_at),
    INDEX idx_print_agents_retired (retired_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS print_destinations (
    destination_key VARCHAR(40) PRIMARY KEY,
    label VARCHAR(160) NOT NULL,
    destination_type VARCHAR(20) NOT NULL DEFAULT 'preparation',
    preparation_areas_json JSON NULL,
    agent_id INT UNSIGNED NULL,
    windows_queue_name VARCHAR(190) NULL,
    active TINYINT(1) NOT NULL DEFAULT 0,
    required_for_operation TINYINT(1) NOT NULL DEFAULT 1,
    paper_width_mm DECIMAL(5,1) NOT NULL DEFAULT 80.0,
    printable_width_mm DECIMAL(5,1) NOT NULL DEFAULT 72.1,
    copies TINYINT UNSIGNED NOT NULL DEFAULT 1,
    layout_mode VARCHAR(30) NOT NULL DEFAULT 'combined',
    fallback_agent_id INT UNSIGNED NULL,
    fallback_windows_queue_name VARCHAR(190) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_print_destination_agent FOREIGN KEY (agent_id) REFERENCES print_agents(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_print_destination_fallback_agent FOREIGN KEY (fallback_agent_id) REFERENCES print_agents(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_print_destination_agent_active (agent_id,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS print_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(30) NOT NULL,
    name VARCHAR(160) NOT NULL,
    template_version VARCHAR(40) NOT NULL,
    origin VARCHAR(20) NOT NULL DEFAULT 'custom',
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    definition_json JSON NOT NULL,
    definition_sha256 CHAR(64) NULL,
    active TINYINT(1) NOT NULL DEFAULT 0,
    active_guard VARCHAR(30) NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_print_template_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_print_template_version (template_key,template_version),
    UNIQUE KEY uq_print_template_one_active (active_guard),
    INDEX idx_print_template_origin (template_key,origin,created_at),
    INDEX idx_print_template_active (template_key,active,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO print_templates(template_key,name,template_version,origin,revision,definition_json,definition_sha256,active,active_guard)
VALUES
('preparation','قالب خوانای آماده‌سازی Sokna','builtin-v2-2','builtin',1,JSON_OBJECT(
  'format','sokna-print-template-v1','package_format','sokna-print-template-package-v2','template_key','preparation','layout_contract','preparation-compact-v1','paper_width_mm',80,
  'base_font_size',28,'title_font_size',38,'table_font_size',44,'line_spacing',6,'margin',10,
  'show_actor',false,'show_time',true,'show_order_number',true,'show_section_titles',true,
  'show_prices',false,'footer','',
  'design',JSON_OBJECT('format','sokna-print-design-v2','layout_contract','preparation-ticket-v2','density','compact','font_stack','Vazirmatn, Tahoma, "Segoe UI", sans-serif','header_alignment','center','separator_style','solid','item_layout','quantity-first','section_order',JSON_ARRAY('status','meta','items','notes','footer'),'labels',JSON_OBJECT('ticket_title','فیش آماده‌سازی','new_order','سفارش جدید','reprint','چاپ مجدد','adjustment','اصلاح سفارش','cancel','لغو سفارش','note','یادداشت','takeaway','بیرون‌بر'))
),NULL,1,'preparation'),
('customer','قالب خوانای سند مشتری Sokna','builtin-v2-2','builtin',1,JSON_OBJECT(
  'format','sokna-print-template-v1','package_format','sokna-print-template-package-v2','template_key','customer','layout_contract','customer-receipt-v1','paper_width_mm',80,
  'base_font_size',23,'title_font_size',30,'table_font_size',28,'line_spacing',5,'margin',9,
  'show_actor',false,'show_time',true,'show_order_number',false,'show_section_titles',true,
  'show_prices',true,'footer','از همراهی شما سپاسگزاریم.',
  'design',JSON_OBJECT('format','sokna-print-design-v2','layout_contract','customer-receipt-v2','density','compact','font_stack','Vazirmatn, Tahoma, "Segoe UI", sans-serif','header_alignment','center','separator_style','solid','item_layout','responsive-receipt','section_order',JSON_ARRAY('brand','meta','items','summary','settlement','footer'),'labels',JSON_OBJECT('invoice','فاکتور','prebill','صورتحساب','items','اقلام','subtotal','جمع اقلام','discount','تخفیف','total','جمع نهایی','settlement','نحوه ثبت'))
),NULL,1,'customer');


CREATE TABLE IF NOT EXISTS print_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_token CHAR(32) NOT NULL UNIQUE,
    idempotency_key VARCHAR(190) NOT NULL UNIQUE,
    contract_version SMALLINT UNSIGNED NOT NULL DEFAULT 4,
    job_type VARCHAR(40) NOT NULL,
    destination_key VARCHAR(40) NOT NULL,
    required TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    blocked_reason VARCHAR(160) NULL,
    payload_json JSON NOT NULL,
    content_sha256 CHAR(64) NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id VARCHAR(100) NULL,
    requested_by_user_id INT UNSIGNED NULL,
    reprint_of_id BIGINT UNSIGNED NULL,
    reprint_reason VARCHAR(300) NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    retry_cycle INT UNSIGNED NOT NULL DEFAULT 0,
    retry_cycle_started_at DATETIME NULL,
    retry_cycle_started_by_user_id INT UNSIGNED NULL,
    next_attempt_at DATETIME NULL,
    claim_token CHAR(64) NULL,
    claimed_by_agent_id INT UNSIGNED NULL,
    claimed_at DATETIME NULL,
    lease_expires_at DATETIME NULL,
    accepted_at DATETIME NULL,
    local_receipt_id VARCHAR(96) NULL,
    submitted_at DATETIME NULL,
    completed_at DATETIME NULL,
    last_error_code VARCHAR(80) NULL,
    last_error VARCHAR(500) NULL,
    resolution_state VARCHAR(30) NULL,
    resolved_at DATETIME NULL,
    resolved_by_user_id INT UNSIGNED NULL,
    resolution_note VARCHAR(500) NULL,
    last_admin_action_id VARCHAR(96) NULL,
    last_admin_action_type VARCHAR(32) NULL,
    last_admin_action_hash CHAR(64) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_print_job_destination FOREIGN KEY (destination_key) REFERENCES print_destinations(destination_key) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_print_job_user FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_print_job_reprint FOREIGN KEY (reprint_of_id) REFERENCES print_jobs(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_print_job_agent FOREIGN KEY (claimed_by_agent_id) REFERENCES print_agents(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_print_job_retry_user FOREIGN KEY (retry_cycle_started_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_print_jobs_queue (status,destination_key,created_at),
    INDEX idx_print_jobs_v4_queue (destination_key,status,next_attempt_at,id),
    INDEX idx_print_jobs_claim (claimed_by_agent_id,status,claimed_at),
    INDEX idx_print_jobs_entity (entity_type,entity_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS print_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    attempt_no INT UNSIGNED NOT NULL,
    retry_cycle INT UNSIGNED NOT NULL DEFAULT 0,
    cycle_attempt_no INT UNSIGNED NOT NULL DEFAULT 1,
    agent_id INT UNSIGNED NOT NULL,
    state VARCHAR(24) NOT NULL DEFAULT 'reserved',
    claim_request_id VARCHAR(80) NOT NULL,
    lease_token_hash CHAR(64) NOT NULL,
    lease_expires_at DATETIME NOT NULL,
    destination_snapshot_json JSON NULL,
    local_receipt_id VARCHAR(96) NULL,
    spooler_job_id VARCHAR(96) NULL,
    accept_request_id VARCHAR(80) NULL,
    accept_request_hash CHAR(64) NULL,
    start_request_id VARCHAR(80) NULL,
    start_request_hash CHAR(64) NULL,
    renew_request_id VARCHAR(80) NULL,
    renew_request_hash CHAR(64) NULL,
    report_request_id VARCHAR(80) NULL,
    report_request_hash CHAR(64) NULL,
    leased_at DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    started_at DATETIME NULL,
    submitted_at DATETIME NULL,
    finished_at DATETIME NULL,
    outcome VARCHAR(40) NULL,
    error_code VARCHAR(80) NULL,
    error_message VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_print_attempt_job FOREIGN KEY (job_id) REFERENCES print_jobs(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_print_attempt_agent FOREIGN KEY (agent_id) REFERENCES print_agents(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_print_attempt_job_no (job_id,attempt_no),
    UNIQUE KEY uq_print_attempt_job_cycle_no (job_id,retry_cycle,cycle_attempt_no),
    UNIQUE KEY uq_print_attempt_agent_receipt (agent_id,local_receipt_id),
    UNIQUE KEY uq_print_attempt_accept_request (agent_id,accept_request_id),
    UNIQUE KEY uq_print_attempt_start_request (agent_id,start_request_id),
    UNIQUE KEY uq_print_attempt_renew_request (agent_id,renew_request_id),
    UNIQUE KEY uq_print_attempt_report_request (agent_id,report_request_id),
    INDEX idx_print_attempt_claim_request (agent_id,claim_request_id,id),
    INDEX idx_print_attempt_state (state,lease_expires_at,id),
    INDEX idx_print_attempt_job (job_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS print_claim_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agent_id INT UNSIGNED NOT NULL,
    request_id VARCHAR(80) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    agent_version VARCHAR(40) NOT NULL,
    attempt_ids_json JSON NULL,
    response_snapshot_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_print_claim_request_agent FOREIGN KEY (agent_id) REFERENCES print_agents(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_print_claim_request_agent_request (agent_id,request_id),
    INDEX idx_print_claim_request_created (created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS print_claim_reconciliations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_request_row_id BIGINT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NOT NULL,
    request_id VARCHAR(80) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    old_attempt_id BIGINT UNSIGNED NOT NULL,
    replacement_attempt_id BIGINT UNSIGNED NOT NULL,
    evidence_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_print_claim_reconciliation_claim FOREIGN KEY (claim_request_row_id) REFERENCES print_claim_requests(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_print_claim_reconciliation_agent FOREIGN KEY (agent_id) REFERENCES print_agents(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_print_claim_reconciliation_old_attempt FOREIGN KEY (old_attempt_id) REFERENCES print_attempts(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_print_claim_reconciliation_new_attempt FOREIGN KEY (replacement_attempt_id) REFERENCES print_attempts(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_print_claim_reconciliation_request (agent_id,request_id),
    UNIQUE KEY uq_print_claim_reconciliation_attempt (claim_request_row_id,old_attempt_id),
    INDEX idx_print_claim_reconciliation_created (created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO print_destinations(destination_key,label,destination_type,preparation_areas_json,windows_queue_name,active,paper_width_mm,printable_width_mm,layout_mode,fallback_agent_id,fallback_windows_queue_name)
VALUES
('customer_receipt','سند مشتری','customer',NULL,NULL,0,80.0,72.1,'combined',NULL,NULL),
('prep_shared','آماده‌سازی مشترک','preparation',JSON_ARRAY('kitchen','bar'),NULL,0,80.0,72.1,'combined',NULL,NULL);

CREATE TABLE IF NOT EXISTS waiter_calls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_code VARCHAR(32) NOT NULL UNIQUE,
    client_token VARCHAR(80) NOT NULL UNIQUE,
    device_token VARCHAR(80) NULL,
    table_id INT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    active_table_guard INT UNSIGNED NULL,
    accepted_by_user_id INT UNSIGNED NULL,
    accepted_at DATETIME NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    cancel_reason VARCHAR(40) NULL,
    cancelled_by_user_id INT UNSIGNED NULL,
    business_date DATE NOT NULL,
    business_shift_key VARCHAR(40) NOT NULL,
    business_shift_label VARCHAR(80) NOT NULL,
    business_cutoff_snapshot CHAR(5) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_waiter_calls_table FOREIGN KEY (table_id) REFERENCES cafe_tables(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_waiter_calls_session FOREIGN KEY (session_id) REFERENCES table_sessions(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_waiter_calls_user FOREIGN KEY (accepted_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_waiter_calls_cancelled_by FOREIGN KEY (cancelled_by_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_waiter_calls_one_active_table (active_table_guard),
    INDEX idx_waiter_calls_status_created (status, created_at),
    INDEX idx_waiter_calls_table_status (table_id, status, created_at),
    INDEX idx_waiter_calls_business_day (business_date,business_shift_key,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    short_description VARCHAR(500) NULL,
    description TEXT NULL,
    image_path VARCHAR(255) NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    venue VARCHAR(180) NULL,
    admission_text VARCHAR(180) NULL,
    fee_amount INT UNSIGNED NULL,
    capacity INT UNSIGNED NULL,
    registration_type VARCHAR(20) NOT NULL DEFAULT 'none',
    registration_value VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    cancelled_at DATETIME NULL,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_events_public (active, cancelled_at, starts_at, sort_order),
    INDEX idx_events_featured (featured, active, starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE,
    tag_type VARCHAR(20) NOT NULL DEFAULT 'marketing',
    color_key VARCHAR(30) NOT NULL DEFAULT 'primary',
    icon VARCHAR(12) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tags_public (tag_type,active,sort_order,starts_at,ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_tags (
    item_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(item_id,tag_id),
    CONSTRAINT fk_item_tags_item FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_item_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_item_tags_tag (tag_id,item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(140) NOT NULL,
    body VARCHAR(400) NULL,
    image_path VARCHAR(255) NULL,
    action_label VARCHAR(80) NULL,
    action_type VARCHAR(20) NOT NULL DEFAULT 'none',
    action_value VARCHAR(500) NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_campaigns_public (active,starts_at,ends_at,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_metrics_daily (
    metric_date DATE NOT NULL,
    metric_key VARCHAR(40) NOT NULL,
    ref_id INT UNSIGNED NOT NULL DEFAULT 0,
    metric_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(metric_date,metric_key,ref_id),
    INDEX idx_metrics_key_date (metric_key,metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_search_terms_daily (
    metric_date DATE NOT NULL,
    normalized_term VARCHAR(120) NOT NULL,
    display_term VARCHAR(120) NOT NULL,
    search_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    zero_result_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_result_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(metric_date,normalized_term),
    INDEX idx_search_terms_date_count (metric_date,search_count),
    INDEX idx_search_terms_zero (metric_date,zero_result_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS relay_processed_requests (
    request_id VARCHAR(96) PRIMARY KEY,
    request_hash CHAR(64) NOT NULL,
    kind VARCHAR(64) NOT NULL,
    status VARCHAR(24) NOT NULL,
    result_json JSON NULL,
    error_code VARCHAR(96) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_relay_processed_status(status,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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
