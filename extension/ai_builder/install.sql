-- Manual registration for AI Website Builder (if not using Marketplace Installer)
-- Run this SQL if you copied files manually without uploading the .ocmod.zip

INSERT INTO `oc_extension_install` (`extension_id`, `extension_download_id`, `name`, `description`, `code`, `version`, `author`, `link`, `status`, `date_added`)
SELECT 0, 0, 'AI Website Builder Assistant', 'AI-powered store management chat', 'ai_builder', '1.0.0', 'AI Builder Team', '', 0, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `oc_extension_install` WHERE `code` = 'ai_builder');

-- After running this SQL:
-- 1. Go to Extensions → Extensions → Other → Install "AI Website Builder"
-- 2. Go to Extensions → Modifications → Refresh
-- 3. Configure API key in extension settings
