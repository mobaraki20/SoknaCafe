-- Sokna 1.36.4-dev.19 -> 1.36.4-dev.20
-- Print web remediation only. DDL is intentionally split for updater replay/audit.
ALTER TABLE print_agents
    ADD COLUMN bridge_protocol_version TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER health_json,
    ADD COLUMN bridge_port INT UNSIGNED NOT NULL DEFAULT 0 AFTER bridge_protocol_version,
    ADD COLUMN bridge_pairing_id VARCHAR(128) NULL AFTER bridge_port,
    ADD COLUMN bridge_origin VARCHAR(240) NULL AFTER bridge_pairing_id,
    ADD COLUMN bridge_runtime_seen_at DATETIME NULL AFTER bridge_origin;
-- CAFE-STMT --
ALTER TABLE print_attempts
    ADD COLUMN accept_request_hash CHAR(64) NULL AFTER accept_request_id,
    ADD COLUMN start_request_hash CHAR(64) NULL AFTER start_request_id,
    ADD COLUMN renew_request_hash CHAR(64) NULL AFTER renew_request_id,
    ADD COLUMN report_request_hash CHAR(64) NULL AFTER report_request_id;
-- CAFE-STMT --
ALTER TABLE print_claim_requests
    ADD COLUMN request_hash CHAR(64) NULL AFTER request_id,
    ADD COLUMN agent_version VARCHAR(40) NULL AFTER request_hash;
-- CAFE-STMT --
ALTER TABLE print_jobs
    ADD COLUMN last_admin_action_id VARCHAR(96) NULL AFTER resolution_note,
    ADD COLUMN last_admin_action_type VARCHAR(32) NULL AFTER last_admin_action_id,
    ADD COLUMN last_admin_action_hash CHAR(64) NULL AFTER last_admin_action_type;
