CREATE TABLE IF NOT EXISTS `street_welfare_needs` (
 `need_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `street_id` int(10) UNSIGNED NOT NULL,
 `category` enum('Food','Medicine','Housing','Livelihood','Sanitation','Evacuation','Financial Assistance') NOT NULL,
 `affected_households` int(10) UNSIGNED NOT NULL DEFAULT 0, `affected_residents` int(10) UNSIGNED NOT NULL DEFAULT 0,
 `priority` enum('Urgent','High','Medium','Low') NOT NULL DEFAULT 'Medium', `description` text NOT NULL,
 `date_reported` date NOT NULL, `assigned_to` int(10) UNSIGNED DEFAULT NULL,
 `status` enum('Reported','Under Assessment','Approved','In Progress','Completed','Rejected') NOT NULL DEFAULT 'Reported',
 `created_by` int(10) UNSIGNED DEFAULT NULL, `created_at` datetime NOT NULL DEFAULT current_timestamp(),
 `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
 PRIMARY KEY (`need_id`), KEY `idx_swn_filters` (`street_id`,`category`,`priority`,`status`,`date_reported`),
 KEY `idx_swn_status_priority` (`status`,`priority`), KEY `idx_swn_assigned` (`assigned_to`),
 CONSTRAINT `fk_swn_street` FOREIGN KEY (`street_id`) REFERENCES `streets` (`street_id`) ON DELETE CASCADE,
 CONSTRAINT `fk_swn_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
 CONSTRAINT `fk_swn_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
 CONSTRAINT `chk_swn_affected` CHECK (`affected_households` > 0 OR `affected_residents` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
