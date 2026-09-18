ALTER TABLE auth_projections
  ADD COLUMN IF NOT EXISTS display_name VARCHAR(160) NULL AFTER username,
  ADD COLUMN IF NOT EXISTS role VARCHAR(32) NULL AFTER display_name;

CREATE TABLE IF NOT EXISTS remote_read_models (
  installation_id VARCHAR(96) NOT NULL,
  model_key VARCHAR(64) NOT NULL,
  source_version CHAR(64) NOT NULL,
  payload_json JSON NOT NULL,
  generated_at DATETIME NOT NULL,
  last_sync_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(installation_id,model_key),
  INDEX idx_remote_read_sync(installation_id,last_sync_at),
  CONSTRAINT fk_remote_read_installation FOREIGN KEY(installation_id) REFERENCES installations(installation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
