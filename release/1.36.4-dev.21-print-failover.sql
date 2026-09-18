-- Sokna 1.36.4-dev.20 -> 1.36.4-dev.21
-- Print heartbeat/failover remediation. Do NOT backfill last_heartbeat_at from last_seen_at.
ALTER TABLE print_agents
    ADD COLUMN last_heartbeat_at DATETIME NULL AFTER disk_free_mb,
    ADD INDEX idx_print_agents_active_heartbeat (active,retired_at,last_heartbeat_at);
