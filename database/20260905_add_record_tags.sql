-- EQUIAID normalized record-tagging migration.
CREATE TABLE IF NOT EXISTS `tags` (
  `tag_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `name` varchar(80) NOT NULL,
  `slug` varchar(90) NOT NULL, `color` char(7) NOT NULL DEFAULT '#17684e',
  `is_predefined` tinyint(1) NOT NULL DEFAULT 0, `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(), `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tag_id`), UNIQUE KEY `uq_tags_slug` (`slug`), KEY `idx_tags_name` (`name`),
  CONSTRAINT `fk_tags_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_tags_color` CHECK (`color` REGEXP '^#[0-9A-Fa-f]{6}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `record_tags` (
  `object_type` varchar(40) NOT NULL COMMENT 'resident, street, resident_report, welfare_action_plan', `record_id` int(10) UNSIGNED NOT NULL,
  `tag_id` int(10) UNSIGNED NOT NULL, `assigned_by` int(10) UNSIGNED DEFAULT NULL, `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`object_type`,`record_id`,`tag_id`), KEY `idx_record_tags_tag` (`tag_id`,`object_type`,`record_id`), KEY `idx_record_tags_record` (`object_type`,`record_id`),
  CONSTRAINT `fk_record_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_record_tags_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade compatibility for installations that ran an earlier draft migration.
ALTER TABLE `tags` ADD COLUMN IF NOT EXISTS `color` char(7) NOT NULL DEFAULT '#17684e' AFTER `slug`;
ALTER TABLE `tags` ADD COLUMN IF NOT EXISTS `is_predefined` tinyint(1) NOT NULL DEFAULT 0 AFTER `color`;
ALTER TABLE `record_tags` ADD INDEX IF NOT EXISTS `idx_record_tags_record` (`object_type`,`record_id`);
INSERT INTO `tags` (`name`,`slug`,`color`,`is_predefined`) VALUES
 ('Senior Citizen','senior-citizen','#7c3aed',1),('PWD','pwd','#2563eb',1),('Solo Parent','solo-parent','#db2777',1),('Low Income','low-income','#b45309',1),
 ('Medical Assistance','medical-assistance','#dc2626',1),('Food Assistance','food-assistance','#16a34a',1),('High Risk','high-risk','#ea580c',1),('Disaster Affected','disaster-affected','#475569',1)
ON DUPLICATE KEY UPDATE name=VALUES(name),is_predefined=1;
