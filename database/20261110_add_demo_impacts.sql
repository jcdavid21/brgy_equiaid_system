CREATE TABLE IF NOT EXISTS typhoon_demo_imports (
  event_id int(10) UNSIGNED NOT NULL,
  demo_key varchar(80) NOT NULL,
  content_sha256 char(64) NOT NULL,
  imported_json longtext NOT NULL,
  imported_by int(10) UNSIGNED DEFAULT NULL,
  imported_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id), UNIQUE KEY uq_demo_key (demo_key),
  CONSTRAINT fk_demo_event FOREIGN KEY (event_id) REFERENCES typhoon_events(event_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE typhoon_street_impacts MODIFY report_source enum('AI','Staff','Resident','PAGASA','Demo') NOT NULL DEFAULT 'Staff';
