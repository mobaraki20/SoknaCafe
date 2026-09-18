-- Sokna 1.36.4-dev.22 -> 1.36.4-dev.23
-- Durable Claim response snapshots and audited collision reconciliation.
ALTER TABLE print_claim_requests
    ADD COLUMN response_snapshot_json JSON NULL AFTER attempt_ids_json;

CREATE TABLE print_claim_reconciliations (
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
