CREATE TABLE IF NOT EXISTS installations (
  installation_id VARCHAR(96) PRIMARY KEY,
  display_name VARCHAR(160) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  remote_enabled TINYINT(1) NOT NULL DEFAULT 1,
  order_intake_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_projections (
  installation_id VARCHAR(96) NOT NULL,
  projection_id VARCHAR(96) NOT NULL,
  username VARCHAR(160) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  capabilities_json JSON NOT NULL,
  preparation_areas_json JSON NULL,
  projection_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(installation_id,projection_id),
  UNIQUE KEY uq_auth_projection_username(installation_id,username),
  CONSTRAINT fk_auth_projection_installation FOREIGN KEY(installation_id) REFERENCES installations(installation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS public_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id VARCHAR(96) NOT NULL,
  projection_id VARCHAR(96) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_public_session_token(token_hash),
  INDEX idx_public_session_expiry(expires_at),
  CONSTRAINT fk_public_session_projection FOREIGN KEY(installation_id,projection_id) REFERENCES auth_projections(installation_id,projection_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS realtime_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id VARCHAR(96) NOT NULL,
  request_id VARCHAR(96) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  kind VARCHAR(64) NOT NULL,
  actor_projection_id VARCHAR(96) NOT NULL,
  envelope_json JSON NOT NULL,
  state VARCHAR(24) NOT NULL DEFAULT 'queued',
  lease_token_hash CHAR(64) NULL,
  lease_expires_at DATETIME NULL,
  claimed_at DATETIME NULL,
  result_json JSON NULL,
  error_code VARCHAR(96) NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_realtime_request(installation_id,request_id),
  INDEX idx_realtime_claim(installation_id,state,expires_at,lease_expires_at,id),
  CONSTRAINT fk_realtime_installation FOREIGN KEY(installation_id) REFERENCES installations(installation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS request_nonces (
  installation_id VARCHAR(96) NOT NULL,
  nonce VARCHAR(96) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(installation_id,nonce),
  INDEX idx_nonce_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installation_heartbeats (
  installation_id VARCHAR(96) PRIMARY KEY,
  local_version VARCHAR(64) NULL,
  runtime_status VARCHAR(32) NULL,
  telemetry_json JSON NULL,
  last_seen_at DATETIME NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_heartbeat_installation FOREIGN KEY(installation_id) REFERENCES installations(installation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS emergency_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id VARCHAR(96) NOT NULL,
  actor VARCHAR(160) NOT NULL,
  action_key VARCHAR(64) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_emergency_audit_installation(installation_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS guest_publish_revisions (
  installation_id VARCHAR(96) NOT NULL,
  revision_id VARCHAR(64) NOT NULL,
  content_hash CHAR(64) NOT NULL,
  snapshot_json JSON NOT NULL,
  media_manifest_json JSON NOT NULL,
  generated_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(installation_id,revision_id),
  UNIQUE KEY uq_guest_publish_hash(installation_id,content_hash),
  CONSTRAINT fk_guest_revision_installation FOREIGN KEY(installation_id) REFERENCES installations(installation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guest_active_revisions (
  installation_id VARCHAR(96) PRIMARY KEY,
  revision_id VARCHAR(64) NOT NULL,
  activated_at DATETIME NOT NULL,
  CONSTRAINT fk_guest_active_installation FOREIGN KEY(installation_id) REFERENCES installations(installation_id) ON DELETE CASCADE,
  CONSTRAINT fk_guest_active_revision FOREIGN KEY(installation_id,revision_id) REFERENCES guest_publish_revisions(installation_id,revision_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guest_availability_state (
  installation_id VARCHAR(96) PRIMARY KEY,
  version CHAR(64) NOT NULL,
  payload_json JSON NOT NULL,
  generated_at DATETIME NOT NULL,
  last_sync_at DATETIME NOT NULL,
  CONSTRAINT fk_guest_availability_installation FOREIGN KEY(installation_id) REFERENCES installations(installation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
