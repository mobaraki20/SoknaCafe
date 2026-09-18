INSERT INTO settings(setting_key, setting_value)
SELECT 'public_waiter_call_enabled', setting_value
FROM settings
WHERE setting_key='preview_waiter_call_enabled'
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
DELETE FROM settings WHERE setting_key='preview_waiter_call_enabled';
INSERT INTO settings(setting_key, setting_value)
SELECT 'message.public_waiter_prompt', setting_value
FROM settings
WHERE setting_key='message.preview_waiter_prompt'
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
INSERT INTO settings(setting_key, setting_value)
SELECT 'message_enabled.public_waiter_prompt', setting_value
FROM settings
WHERE setting_key='message_enabled.preview_waiter_prompt'
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
DELETE FROM settings WHERE setting_key IN ('message.preview_waiter_prompt','message_enabled.preview_waiter_prompt');
