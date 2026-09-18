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
