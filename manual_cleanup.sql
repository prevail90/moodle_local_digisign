-- Manual Database Cleanup for local_digisign plugin
-- Run this SQL if the plugin uninstall fails

-- 1. Remove plugin settings
DELETE FROM {config_plugins} WHERE plugin = 'local_digisign';

-- 2. Remove submission records
DELETE FROM {local_digisign_sub};

-- 3. Drop the plugin table
DROP TABLE IF EXISTS {local_digisign_sub};

-- 4. Clear any cached plugin data
DELETE FROM {config} WHERE name LIKE 'local_digisign%';

-- 5. Remove from installed plugins (if exists)
DELETE FROM {config} WHERE name = 'local_digisign';

-- 6. Clear plugin cache entries
DELETE FROM {cache} WHERE name LIKE '%local_digisign%';
