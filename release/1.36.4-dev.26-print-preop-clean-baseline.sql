-- Sokna Cafe 1.36.4-dev.26
-- PRE-OPERATIONAL TEST DATA RESET ONLY.
-- Clears print execution history after the Windows Agent local queue has been
-- stopped and reset. Agent registration, destinations, templates and all
-- business-domain data are intentionally preserved.
--
-- IMPORTANT: Do NOT reset AUTO_INCREMENT values. Agent durable history may
-- contain historical attempt ids; keeping monotonic server ids prevents
-- accidental identity reuse after a pre-operational reset.

DELETE FROM print_claim_reconciliations;
-- CAFE-STMT --
DELETE FROM print_claim_requests;
-- CAFE-STMT --
DELETE FROM print_attempts;
-- CAFE-STMT --
UPDATE print_jobs SET reprint_of_id = NULL WHERE reprint_of_id IS NOT NULL;
-- CAFE-STMT --
DELETE FROM print_jobs;
