-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 22, 2026 at 01:28 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `brgy_equiaid`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK → users.id; NULL for system actions',
  `action` varchar(512) NOT NULL COMMENT 'Human-readable description of the action performed',
  `module` varchar(80) DEFAULT NULL COMMENT 'Which module triggered this log (e.g. Reports, Users)',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IPv4 or IPv6 of the requesting client',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System-wide activity audit trail — every meaningful admin/staff action';

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `module`, `ip_address`, `created_at`) VALUES
(1, 1, 'Updated report #7 (Flood) status to &quot;In Progress&quot;', 'Reports', '::1', '2026-04-08 22:28:32'),
(2, 1, 'Updated report #7 (Flood) status to &quot;Dismissed&quot;', 'Reports', '::1', '2026-04-08 22:31:45');

-- --------------------------------------------------------

--
-- Table structure for table `analytics_snapshots`
--

CREATE TABLE `analytics_snapshots` (
  `snap_id` int(10) UNSIGNED NOT NULL,
  `snapshot_date` date NOT NULL,
  `snapshot_type` enum('Daily','Weekly','Monthly','Event') NOT NULL DEFAULT 'Daily',
  `event_id` int(10) UNSIGNED DEFAULT NULL,
  `total_streets` smallint(5) UNSIGNED DEFAULT 0,
  `streets_red` smallint(5) UNSIGNED DEFAULT 0,
  `streets_orange` smallint(5) UNSIGNED DEFAULT 0,
  `streets_yellow` smallint(5) UNSIGNED DEFAULT 0,
  `streets_green` smallint(5) UNSIGNED DEFAULT 0,
  `streets_needs_welfare` smallint(5) UNSIGNED DEFAULT 0,
  `streets_moderate_welfare` smallint(5) UNSIGNED DEFAULT 0,
  `streets_no_welfare` smallint(5) UNSIGNED DEFAULT 0,
  `pct_affected` decimal(5,2) DEFAULT 0.00 COMMENT 'RED+ORANGE as % of total',
  `pct_needs_welfare` decimal(5,2) DEFAULT 0.00,
  `pct_improved` decimal(5,2) DEFAULT NULL COMMENT 'Streets that dropped a risk tier vs prior snapshot',
  `total_affected_persons` int(10) UNSIGNED DEFAULT 0,
  `total_affected_households` int(10) UNSIGNED DEFAULT 0,
  `total_budget_recommended` decimal(14,2) DEFAULT 0.00,
  `total_budget_approved` decimal(14,2) DEFAULT 0.00,
  `total_budget_spent` decimal(14,2) DEFAULT 0.00,
  `total_food_packs_dist` int(10) UNSIGNED DEFAULT 0,
  `total_medicine_dist` int(10) UNSIGNED DEFAULT 0,
  `total_shelter_kits_dist` int(10) UNSIGNED DEFAULT 0,
  `images_uploaded_count` int(10) UNSIGNED DEFAULT 0,
  `images_analyzed_count` int(10) UNSIGNED DEFAULT 0,
  `labels_collected_count` int(10) UNSIGNED DEFAULT 0,
  `active_model_id` int(10) UNSIGNED DEFAULT NULL,
  `avg_model_confidence` decimal(5,4) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pre-aggregated analytics for dashboard charts — Feature 9';

--
-- Dumping data for table `analytics_snapshots`
--

INSERT INTO `analytics_snapshots` (`snap_id`, `snapshot_date`, `snapshot_type`, `event_id`, `total_streets`, `streets_red`, `streets_orange`, `streets_yellow`, `streets_green`, `streets_needs_welfare`, `streets_moderate_welfare`, `streets_no_welfare`, `pct_affected`, `pct_needs_welfare`, `pct_improved`, `total_affected_persons`, `total_affected_households`, `total_budget_recommended`, `total_budget_approved`, `total_budget_spent`, `total_food_packs_dist`, `total_medicine_dist`, `total_shelter_kits_dist`, `images_uploaded_count`, `images_analyzed_count`, `labels_collected_count`, `active_model_id`, `avg_model_confidence`, `created_at`) VALUES
(1, '2024-07-24', 'Event', 1, 10, 3, 2, 1, 4, 5, 1, 4, 50.00, 60.00, NULL, 2170, 476, 555000.00, 540000.00, 210000.00, 0, 0, 0, 5, 5, 5, 2, 0.8826, '2026-03-15 23:04:48'),
(2, '2026-04-08', 'Daily', NULL, 10, 3, 2, 0, 5, 4, 1, 5, 50.00, 40.00, NULL, 0, 0, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, 0, NULL, NULL, '2026-04-08 22:38:20');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Linked typhoon/disaster event (optional)',
  `category` enum('Alert','Evacuation','Relief','Weather','General') NOT NULL DEFAULT 'General',
  `priority` enum('Critical','High','Normal') NOT NULL DEFAULT 'Normal',
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = shows as pinned banner on announcements page',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `author_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Barangay official announcements, advisories, and notices';

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `event_id`, `category`, `priority`, `title`, `body`, `is_pinned`, `is_active`, `author_id`, `created_at`, `updated_at`) VALUES
(1, 2, 'Alert', 'Critical', 'BAGYO FALCON: Evacuation Order for Zone 3', 'Lahat ng residente sa Zone 3 (Rizal Ave, Bonifacio St, Luna St) ay inuutusan na mag-evacuate papunta sa pinakamalapit na evacuation center. Ang baha ay umabot na sa 1.5 metro. Magdala ng mahahalagang gamit at dokumento. Huwag mag-atubiling lumapit sa barangay hall para sa tulong.', 1, 1, 1, '2026-03-21 19:35:26', '2026-03-21 19:35:26'),
(2, 2, 'Weather', 'High', 'Signal No. 2 Raised — Typhoon Falcon Update', 'Ayon sa PAGASA, ang Typhoon Falcon ay naglalayag patungo sa Luzon at inaasahang makakarating sa ating lugar sa loob ng 24 na oras. Lahat ng residente ay pinapaalalahanan na mag-ingat, mag-stock ng pagkain at tubig, at manatili sa loob ng bahay hanggang sa susunod na abiso.', 0, 1, 1, '2026-03-21 19:35:26', '2026-03-21 19:35:26'),
(3, 2, 'Relief', 'High', 'Relief Operations — Food Packs Distribution Today', 'Ang pamamahagi ng food packs ay magsisimula ngayong araw sa Bagong Silang Elementary School (Zone 1) mula 8:00 AM hanggang 5:00 PM. Magdala ng valid ID at barangay clearance. Priority ang buntis, may sakit, at matatanda.', 0, 1, 2, '2026-03-21 19:35:26', '2026-03-21 19:35:26'),
(4, 2, 'Evacuation', 'High', 'All Evacuation Centers Now Open', 'Ang lahat ng evacuation centers ay bukas na at handang tumanggap ng mga evacuees. Bagong Silang Elementary School (Zone 1) — 500 capacity. Bagong Silang Covered Court (Zone 3) — 800 capacity. Zone 2 Multi-Purpose Hall — 350 capacity. Libre ang pagkain at tirahan para sa lahat ng evacuees.', 0, 1, 1, '2026-03-21 19:35:26', '2026-03-21 19:35:26'),
(5, NULL, 'General', 'Normal', 'Barangay Hall Extended Hours During Typhoon Season', 'Ang barangay hall ay magbubukas mula 6:00 AM hanggang 10:00 PM sa panahon ng bagyo para sa mga emergency na pangangailangan ng mga residente. Para sa agarang tulong, makipag-ugnayan sa EQUIAID hotline o personal na pumunta sa barangay hall.', 0, 1, 1, '2026-03-21 19:35:26', '2026-03-21 19:35:26'),
(6, NULL, 'General', 'Normal', 'EQUIAID System Update — Flood Reporting Now Available', 'Ang bagong flood reporting feature ay available na sa inyong mobile browser. Maaari na kayong mag-submit ng flood reports na may larawan at GPS location direkta mula sa inyong telepono. Bisitahin ang report.php para masubukan. Ang lahat ng reports ay direkta na napupunta sa barangay staff para sa agarang aksyon.', 0, 1, 1, '2026-03-21 19:35:26', '2026-03-21 19:35:26');

-- --------------------------------------------------------

--
-- Table structure for table `budget_allocations`
--

CREATE TABLE `budget_allocations` (
  `alloc_id` int(10) UNSIGNED NOT NULL,
  `street_id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED DEFAULT NULL,
  `risk_level` enum('RED','ORANGE','YELLOW','GREEN') NOT NULL,
  `vuln_score` decimal(5,2) DEFAULT NULL,
  `recommended_budget` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'System-recommended (₱)',
  `approved_budget` decimal(14,2) DEFAULT NULL COMMENT 'Approved by admin (₱)',
  `actual_spent` decimal(14,2) DEFAULT 0.00 COMMENT 'Actual disbursement (₱)',
  `affected_households` int(10) UNSIGNED DEFAULT 0,
  `priority_score` decimal(5,2) DEFAULT NULL COMMENT 'Composite priority 0-100',
  `allocation_basis` text DEFAULT NULL COMMENT 'Explanation of how budget was computed',
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `fiscal_period` varchar(20) DEFAULT NULL COMMENT 'e.g. 2024-Q3',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Budget allocation recommendations and approvals — Feature 5';

--
-- Dumping data for table `budget_allocations`
--

INSERT INTO `budget_allocations` (`alloc_id`, `street_id`, `event_id`, `risk_level`, `vuln_score`, `recommended_budget`, `approved_budget`, `actual_spent`, `affected_households`, `priority_score`, `allocation_basis`, `approved_by`, `approved_at`, `fiscal_period`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'RED', 87.00, 120000.00, 120000.00, 0.00, 98, 92.00, 'Score 87 RED; 98 affected HH; ₱1,224/HH', 1, '2024-07-25 08:00:00', '2024-Q3', '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(2, 6, 1, 'RED', 91.00, 135000.00, 130000.00, 0.00, 140, 94.50, 'Score 91 RED; 140 affected HH; ₱929/HH', 1, '2024-07-25 08:00:00', '2024-Q3', '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(3, 7, 1, 'RED', 85.00, 115000.00, 115000.00, 0.00, 118, 89.00, 'Score 85 RED; 118 affected HH', 1, '2024-07-25 08:00:00', '2024-Q3', '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(4, 2, 1, 'ORANGE', 72.00, 80000.00, 75000.00, 0.00, 52, 74.20, 'Score 72 ORANGE; 52 affected HH', 1, '2024-07-25 09:00:00', '2024-Q3', '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(5, 8, 1, 'ORANGE', 62.00, 65000.00, 60000.00, 0.00, 68, 65.00, 'Score 62 ORANGE; 68 affected HH', 1, '2024-07-25 09:00:00', '2024-Q3', '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(6, 3, NULL, 'YELLOW', 45.00, 40000.00, 40000.00, 0.00, 0, 45.00, 'Score 45 YELLOW; no active event', 1, '2024-07-26 08:00:00', '2024-Q3', '2026-03-15 23:04:48', '2026-03-15 23:04:48');

-- --------------------------------------------------------

--
-- Table structure for table `demographic_indicators`
--

CREATE TABLE `demographic_indicators` (
  `demo_id` int(10) UNSIGNED NOT NULL,
  `street_id` int(10) UNSIGNED NOT NULL,
  `survey_date` date NOT NULL COMMENT 'Date data was collected',
  `fourps_households` int(10) UNSIGNED DEFAULT 0 COMMENT 'Pantawid Pamilya beneficiary households',
  `poverty_rate_pct` decimal(5,2) DEFAULT NULL COMMENT 'Estimated % of households below poverty line',
  `avg_monthly_income` decimal(10,2) DEFAULT NULL COMMENT 'Average household income (₱)',
  `pwd_count` int(10) UNSIGNED DEFAULT 0 COMMENT 'Persons with Disability',
  `senior_count` int(10) UNSIGNED DEFAULT 0 COMMENT 'Senior citizens (60+)',
  `pregnant_count` int(10) UNSIGNED DEFAULT 0 COMMENT 'Pregnant/lactating women',
  `child_count` int(10) UNSIGNED DEFAULT 0 COMMENT 'Children under 5',
  `informal_settlers_pct` decimal(5,2) DEFAULT NULL COMMENT '% of households that are informal settlers',
  `concrete_houses_pct` decimal(5,2) DEFAULT NULL,
  `light_materials_pct` decimal(5,2) DEFAULT NULL COMMENT 'Bamboo, wood, light materials',
  `drainage_type` enum('None','Open Canal','Closed Drainage','Underground') DEFAULT 'None',
  `road_surface` enum('Unpaved','Gravel','Asphalt','Concrete') DEFAULT 'Unpaved',
  `street_width_m` decimal(5,2) DEFAULT NULL,
  `elevation_m` decimal(7,2) DEFAULT NULL COMMENT 'Elevation above sea level',
  `dist_to_waterway_m` decimal(8,2) DEFAULT NULL COMMENT 'Distance to nearest river/canal (m)',
  `flood_frequency` tinyint(3) UNSIGNED DEFAULT 0 COMMENT 'Number of times flooded in last 5 years',
  `avg_flood_height_m` decimal(5,2) DEFAULT NULL COMMENT 'Average historical flood height',
  `data_source` varchar(150) DEFAULT NULL COMMENT 'CBMS, DSWD survey, barangay records',
  `encoded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Poverty and demographic features — non-image ML inputs';

--
-- Dumping data for table `demographic_indicators`
--

INSERT INTO `demographic_indicators` (`demo_id`, `street_id`, `survey_date`, `fourps_households`, `poverty_rate_pct`, `avg_monthly_income`, `pwd_count`, `senior_count`, `pregnant_count`, `child_count`, `informal_settlers_pct`, `concrete_houses_pct`, `light_materials_pct`, `drainage_type`, `road_surface`, `street_width_m`, `elevation_m`, `dist_to_waterway_m`, `flood_frequency`, `avg_flood_height_m`, `data_source`, `encoded_by`, `created_at`) VALUES
(1, 1, '2024-01-15', 38, 62.50, 8500.00, 12, 18, 0, 0, 75.00, NULL, NULL, 'Open Canal', 'Asphalt', NULL, 4.20, 120.00, 5, 1.60, NULL, 2, '2026-03-15 23:04:48'),
(2, 2, '2024-01-15', 25, 48.30, 10200.00, 8, 12, 0, 0, 55.00, NULL, NULL, 'Open Canal', 'Asphalt', NULL, 5.10, 200.00, 3, 0.90, NULL, 2, '2026-03-15 23:04:48'),
(3, 3, '2024-01-15', 22, 35.00, 13500.00, 6, 10, 0, 0, 30.00, NULL, NULL, 'Closed Drainage', 'Concrete', NULL, 6.80, 450.00, 1, 0.30, NULL, 2, '2026-03-15 23:04:48'),
(4, 4, '2024-01-15', 8, 18.50, 18000.00, 3, 6, 0, 0, 12.00, NULL, NULL, 'Closed Drainage', 'Concrete', NULL, 8.50, 620.00, 0, NULL, NULL, 1, '2026-03-15 23:04:48'),
(5, 5, '2024-01-15', 15, 28.00, 14500.00, 5, 9, 0, 0, 22.00, NULL, NULL, 'Open Canal', 'Asphalt', NULL, 7.20, 380.00, 1, 0.40, NULL, 2, '2026-03-15 23:04:48'),
(6, 6, '2024-01-15', 55, 70.20, 7200.00, 16, 24, 0, 0, 82.00, NULL, NULL, 'None', 'Unpaved', NULL, 2.80, 55.00, 6, 2.10, NULL, 2, '2026-03-15 23:04:48'),
(7, 7, '2024-01-15', 48, 65.00, 7800.00, 14, 20, 0, 0, 78.00, NULL, NULL, 'Open Canal', 'Unpaved', NULL, 3.20, 80.00, 5, 1.95, NULL, 2, '2026-03-15 23:04:48'),
(8, 8, '2024-01-15', 40, 58.00, 8900.00, 10, 16, 0, 0, 68.00, NULL, NULL, 'Open Canal', 'Gravel', NULL, 3.80, 110.00, 4, 0.75, NULL, 2, '2026-03-15 23:04:48'),
(9, 9, '2024-01-15', 6, 15.00, 20000.00, 2, 5, 0, 0, 8.00, NULL, NULL, 'Underground', 'Concrete', NULL, 12.00, 900.00, 0, NULL, NULL, 2, '2026-03-15 23:04:48'),
(10, 10, '2024-01-15', 4, 12.00, 22000.00, 1, 4, 0, 0, 5.00, NULL, NULL, 'Underground', 'Concrete', NULL, 14.50, 1200.00, 0, NULL, NULL, 2, '2026-03-15 23:04:48');

-- --------------------------------------------------------

--
-- Table structure for table `evacuation_centers`
--

CREATE TABLE `evacuation_centers` (
  `center_id` int(10) UNSIGNED NOT NULL,
  `zone_id` int(10) UNSIGNED DEFAULT NULL,
  `center_name` varchar(150) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `capacity` int(10) UNSIGNED DEFAULT 0 COMMENT 'Max persons',
  `current_occupancy` int(10) UNSIGNED DEFAULT 0,
  `contact_person` varchar(120) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Evacuation centers — shown on map';

--
-- Dumping data for table `evacuation_centers`
--

INSERT INTO `evacuation_centers` (`center_id`, `zone_id`, `center_name`, `address`, `latitude`, `longitude`, `capacity`, `current_occupancy`, `contact_person`, `contact_number`, `is_active`, `created_at`) VALUES
(1, 1, 'Bagong Silang Elementary School', 'Zone 1, Bagong Silang', 14.7428000, 120.9842000, 500, 0, NULL, NULL, 1, '2026-03-15 23:04:48'),
(2, 3, 'Bagong Silang Covered Court', 'Zone 3, Bagong Silang', 14.7412000, 120.9822000, 800, 0, NULL, NULL, 1, '2026-03-15 23:04:48'),
(3, 2, 'Zone 2 Multi-Purpose Hall', 'Zone 2, Bagong Silang', 14.7437000, 120.9851000, 350, 0, NULL, NULL, 1, '2026-03-15 23:04:48');

-- --------------------------------------------------------

--
-- Table structure for table `ground_truth_labels`
--

CREATE TABLE `ground_truth_labels` (
  `label_id` int(10) UNSIGNED NOT NULL,
  `image_id` int(10) UNSIGNED NOT NULL,
  `street_id` int(10) UNSIGNED NOT NULL,
  `true_condition` enum('Flooded','Damaged','Accessible','Safe','Inaccessible') NOT NULL,
  `flood_severity` enum('None','Low','Medium','High','Severe') NOT NULL DEFAULT 'None',
  `damage_severity` enum('None','Minor','Moderate','Severe') NOT NULL DEFAULT 'None',
  `road_accessible` tinyint(1) NOT NULL DEFAULT 1,
  `needs_welfare` enum('Yes','Moderate','No') NOT NULL DEFAULT 'No',
  `welfare_priority` tinyint(3) UNSIGNED DEFAULT NULL COMMENT '1=highest … 4=lowest',
  `risk_level` enum('RED','ORANGE','YELLOW','GREEN') NOT NULL,
  `vulnerability_score` decimal(5,2) DEFAULT NULL COMMENT 'Human-assessed score 0-100',
  `labeled_by` int(10) UNSIGNED NOT NULL COMMENT 'Role: labeler or staff or admin',
  `label_method` enum('Field Inspection','Remote Verification','Resident Confirmation') NOT NULL DEFAULT 'Field Inspection',
  `label_confidence` enum('High','Medium','Low') DEFAULT 'High',
  `label_date` date NOT NULL,
  `verified_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'Second reviewer for QA',
  `verified_at` datetime DEFAULT NULL,
  `is_qa_passed` tinyint(1) DEFAULT NULL,
  `model_predicted` enum('Flooded','Damaged','Accessible','Safe','Inaccessible') DEFAULT NULL,
  `model_agreed` tinyint(1) DEFAULT NULL COMMENT '1=model matched ground truth',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Human-verified ground truth labels — REQUIRED for supervised training';

--
-- Dumping data for table `ground_truth_labels`
--

INSERT INTO `ground_truth_labels` (`label_id`, `image_id`, `street_id`, `true_condition`, `flood_severity`, `damage_severity`, `road_accessible`, `needs_welfare`, `welfare_priority`, `risk_level`, `vulnerability_score`, `labeled_by`, `label_method`, `label_confidence`, `label_date`, `verified_by`, `verified_at`, `is_qa_passed`, `model_predicted`, `model_agreed`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Flooded', 'Severe', 'Moderate', 0, 'Yes', NULL, 'RED', 88.00, 5, 'Field Inspection', 'High', '2024-07-24', NULL, NULL, 1, NULL, NULL, NULL, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(2, 2, 6, 'Flooded', 'Severe', 'Moderate', 0, 'Yes', NULL, 'RED', 92.00, 5, 'Field Inspection', 'High', '2024-07-24', NULL, NULL, 1, NULL, NULL, NULL, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(3, 3, 7, 'Damaged', 'Medium', 'Severe', 0, 'Yes', NULL, 'RED', 85.00, 5, 'Field Inspection', 'High', '2024-07-24', NULL, NULL, 1, NULL, NULL, NULL, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(4, 4, 2, 'Accessible', 'Low', 'Minor', 1, 'Moderate', NULL, 'ORANGE', 70.00, 5, 'Field Inspection', 'Medium', '2024-07-24', NULL, NULL, 1, NULL, NULL, NULL, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(5, 5, 4, 'Safe', 'None', 'None', 1, 'No', NULL, 'GREEN', 14.00, 5, 'Field Inspection', 'High', '2024-07-24', NULL, NULL, 1, NULL, NULL, NULL, '2026-03-15 23:04:48', '2026-03-15 23:04:48');

-- --------------------------------------------------------

--
-- Table structure for table `image_analysis_queue`
--

CREATE TABLE `image_analysis_queue` (
  `analysis_id` int(10) UNSIGNED NOT NULL,
  `image_id` int(10) UNSIGNED NOT NULL,
  `model_id` int(10) UNSIGNED NOT NULL COMMENT 'FK → model_registry',
  `predicted_class` enum('Flooded','Damaged','Accessible','Safe','Inaccessible') NOT NULL,
  `confidence_score` decimal(5,4) NOT NULL COMMENT 'Softmax probability of top class (0–1)',
  `class_probabilities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '{"Flooded":0.87,"Damaged":0.08,"Safe":0.05}' CHECK (json_valid(`class_probabilities`)),
  `flood_severity` enum('None','Low','Medium','High','Severe') NOT NULL DEFAULT 'None',
  `damage_severity` enum('None','Minor','Moderate','Severe') NOT NULL DEFAULT 'None',
  `road_accessibility` enum('Accessible','Partially Blocked','Blocked') NOT NULL DEFAULT 'Accessible',
  `processing_time_ms` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('Queued','Processing','Completed','Failed') NOT NULL DEFAULT 'Queued',
  `error_message` text DEFAULT NULL,
  `analyzed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ResNet-50 inference results per image — Feature 8; raw logits stored';

--
-- Dumping data for table `image_analysis_queue`
--

INSERT INTO `image_analysis_queue` (`analysis_id`, `image_id`, `model_id`, `predicted_class`, `confidence_score`, `class_probabilities`, `flood_severity`, `damage_severity`, `road_accessibility`, `processing_time_ms`, `status`, `error_message`, `analyzed_at`, `created_at`) VALUES
(1, 1, 2, 'Flooded', 0.9210, '{\"Flooded\":0.9210,\"Damaged\":0.0510,\"Accessible\":0.0180,\"Safe\":0.0100}', 'Severe', 'Moderate', 'Blocked', NULL, 'Completed', NULL, '2024-07-24 12:00:00', '2026-03-15 23:04:48'),
(2, 2, 2, 'Flooded', 0.9540, '{\"Flooded\":0.9540,\"Damaged\":0.0310,\"Accessible\":0.0090,\"Safe\":0.0060}', 'Severe', 'Moderate', 'Blocked', NULL, 'Completed', NULL, '2024-07-24 12:05:00', '2026-03-15 23:04:48'),
(3, 3, 2, 'Damaged', 0.8730, '{\"Flooded\":0.0900,\"Damaged\":0.8730,\"Accessible\":0.0250,\"Safe\":0.0120}', 'Medium', 'Severe', 'Partially Blocked', NULL, 'Completed', NULL, '2024-07-24 12:10:00', '2026-03-15 23:04:48'),
(4, 4, 2, 'Accessible', 0.7120, '{\"Flooded\":0.1850,\"Damaged\":0.0890,\"Accessible\":0.7120,\"Safe\":0.0140}', 'Low', 'Minor', 'Accessible', NULL, 'Completed', NULL, '2024-07-24 12:15:00', '2026-03-15 23:04:48'),
(5, 5, 2, 'Safe', 0.9610, '{\"Flooded\":0.0120,\"Damaged\":0.0160,\"Accessible\":0.0110,\"Safe\":0.9610}', 'None', 'None', 'Accessible', NULL, 'Completed', NULL, '2024-07-24 12:20:00', '2026-03-15 23:04:48');

-- --------------------------------------------------------

--
-- Table structure for table `model_registry`
--

CREATE TABLE `model_registry` (
  `model_id` int(10) UNSIGNED NOT NULL,
  `model_name` varchar(120) NOT NULL COMMENT 'e.g. resnet50-equiaid-v1',
  `model_version` varchar(30) NOT NULL COMMENT 'semver: 1.0.0, 1.1.0',
  `architecture` varchar(60) NOT NULL DEFAULT 'ResNet-50',
  `framework` varchar(60) DEFAULT 'PyTorch' COMMENT 'PyTorch, TensorFlow, etc.',
  `training_dataset_size` int(10) UNSIGNED DEFAULT NULL COMMENT 'Number of labeled images used',
  `training_date` date DEFAULT NULL,
  `training_duration_min` int(10) UNSIGNED DEFAULT NULL,
  `num_classes` tinyint(3) UNSIGNED DEFAULT 4,
  `input_image_size` varchar(20) DEFAULT '224x224',
  `batch_size` smallint(5) UNSIGNED DEFAULT 32,
  `epochs_trained` smallint(5) UNSIGNED DEFAULT NULL,
  `learning_rate` decimal(8,6) DEFAULT NULL,
  `class_labels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '["Flooded","Damaged","Accessible","Safe"]' CHECK (json_valid(`class_labels`)),
  `accuracy` decimal(5,4) DEFAULT NULL COMMENT '0–1',
  `precision_macro` decimal(5,4) DEFAULT NULL,
  `recall_macro` decimal(5,4) DEFAULT NULL,
  `f1_score_macro` decimal(5,4) DEFAULT NULL,
  `auc_roc` decimal(5,4) DEFAULT NULL,
  `confusion_matrix` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`confusion_matrix`)),
  `weights_path` varchar(350) DEFAULT NULL COMMENT 'Path to .pth / .h5 weights file',
  `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Only one active at a time',
  `deployed_at` datetime DEFAULT NULL,
  `retired_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ResNet-50 model version registry — reproducibility & comparison';

--
-- Dumping data for table `model_registry`
--

INSERT INTO `model_registry` (`model_id`, `model_name`, `model_version`, `architecture`, `framework`, `training_dataset_size`, `training_date`, `training_duration_min`, `num_classes`, `input_image_size`, `batch_size`, `epochs_trained`, `learning_rate`, `class_labels`, `accuracy`, `precision_macro`, `recall_macro`, `f1_score_macro`, `auc_roc`, `confusion_matrix`, `weights_path`, `is_active`, `deployed_at`, `retired_at`, `notes`, `created_by`, `created_at`) VALUES
(1, 'resnet50-equiaid', '1.0.0', 'ResNet-50', 'PyTorch', 320, '2024-06-01', NULL, 4, '224x224', 32, NULL, NULL, '[\"Flooded\",\"Damaged\",\"Accessible\",\"Safe\"]', 0.8210, 0.8055, 0.7980, 0.8016, NULL, NULL, NULL, 1, '2024-06-15 08:00:00', NULL, NULL, 1, '2026-03-15 23:04:48'),
(2, 'resnet50-equiaid', '1.1.0', 'ResNet-50', 'PyTorch', 480, '2024-07-20', NULL, 4, '224x224', 32, NULL, NULL, '[\"Flooded\",\"Damaged\",\"Accessible\",\"Safe\"]', 0.8670, 0.8512, 0.8440, 0.8476, NULL, NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-03-15 23:04:48');

-- --------------------------------------------------------

--
-- Table structure for table `prediction_results`
--

CREATE TABLE `prediction_results` (
  `prediction_id` int(10) UNSIGNED NOT NULL,
  `street_id` int(10) UNSIGNED NOT NULL,
  `model_id` int(10) UNSIGNED NOT NULL,
  `feature_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK → street_features snapshot used',
  `analysis_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK → image_analysis_queue result used',
  `event_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Linked disaster event if prediction triggered by event',
  `vulnerability_score` decimal(5,2) NOT NULL COMMENT '0.00–100.00',
  `risk_level` enum('RED','ORANGE','YELLOW','GREEN') NOT NULL,
  `needs_welfare` enum('Yes','Moderate','No') NOT NULL,
  `welfare_priority` tinyint(3) UNSIGNED DEFAULT NULL COMMENT '1=highest … 4=lowest',
  `image_contribution_pct` decimal(5,2) DEFAULT NULL COMMENT '% of score from image analysis',
  `demographic_contribution_pct` decimal(5,2) DEFAULT NULL,
  `historical_contribution_pct` decimal(5,2) DEFAULT NULL,
  `model_confidence` decimal(5,4) DEFAULT NULL COMMENT '0–1 overall prediction confidence',
  `prediction_note` text DEFAULT NULL COMMENT 'System-generated explanation',
  `trigger_type` enum('Manual','Scheduled','Event-Triggered','Image-Upload') NOT NULL DEFAULT 'Manual',
  `triggered_by` int(10) UNSIGNED DEFAULT NULL,
  `predicted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Final vulnerability predictions — combines image + demographic + history';

--
-- Dumping data for table `prediction_results`
--

INSERT INTO `prediction_results` (`prediction_id`, `street_id`, `model_id`, `feature_id`, `analysis_id`, `event_id`, `vulnerability_score`, `risk_level`, `needs_welfare`, `welfare_priority`, `image_contribution_pct`, `demographic_contribution_pct`, `historical_contribution_pct`, `model_confidence`, `prediction_note`, `trigger_type`, `triggered_by`, `predicted_at`) VALUES
(1, 1, 2, 1, 1, 1, 87.00, 'RED', 'Yes', 1, 45.00, 35.00, 20.00, 0.9210, NULL, 'Event-Triggered', 1, '2024-07-24 13:00:00'),
(2, 2, 2, 2, 4, 1, 72.00, 'ORANGE', 'Yes', 2, 30.00, 40.00, 30.00, 0.7120, NULL, 'Event-Triggered', 1, '2024-07-24 13:00:00'),
(3, 3, 2, 3, NULL, NULL, 45.00, 'YELLOW', 'Moderate', 3, 0.00, 60.00, 40.00, 0.7840, NULL, 'Scheduled', 1, '2024-07-24 13:00:00'),
(4, 4, 2, 4, 5, NULL, 15.00, 'GREEN', 'No', 4, 20.00, 50.00, 30.00, 0.9610, NULL, 'Scheduled', 1, '2024-07-24 13:00:00'),
(5, 5, 2, 5, NULL, NULL, 32.00, 'GREEN', 'No', 4, 0.00, 55.00, 45.00, 0.8230, NULL, 'Scheduled', 1, '2024-07-24 13:00:00'),
(6, 6, 2, 6, 2, 1, 91.00, 'RED', 'Yes', 1, 50.00, 30.00, 20.00, 0.9540, NULL, 'Event-Triggered', 1, '2024-07-24 13:00:00'),
(7, 7, 2, 7, 3, 1, 85.00, 'RED', 'Yes', 1, 42.00, 35.00, 23.00, 0.8730, NULL, 'Event-Triggered', 1, '2024-07-24 13:00:00'),
(8, 8, 2, 8, NULL, 1, 62.00, 'ORANGE', 'Moderate', 2, 0.00, 45.00, 55.00, 0.7640, NULL, 'Event-Triggered', 1, '2024-07-24 13:00:00'),
(9, 9, 2, 9, NULL, NULL, 25.00, 'GREEN', 'No', 4, 0.00, 60.00, 40.00, 0.8910, NULL, 'Scheduled', 1, '2024-07-24 13:00:00'),
(10, 10, 2, 10, NULL, NULL, 12.00, 'GREEN', 'No', 4, 0.00, 65.00, 35.00, 0.9120, NULL, 'Scheduled', 1, '2024-07-24 13:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `report_exports`
--

CREATE TABLE `report_exports` (
  `export_id` int(10) UNSIGNED NOT NULL,
  `report_type` enum('Welfare Allocation','Disaster Impact','Street Vulnerability','Resource Distribution','Progress Monitoring') NOT NULL,
  `format` enum('PDF','Excel','CSV') NOT NULL DEFAULT 'PDF',
  `file_path` varchar(350) NOT NULL,
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Filters/date ranges used to generate' CHECK (json_valid(`parameters`)),
  `generated_by` int(10) UNSIGNED NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log of generated reports — Feature 8';

-- --------------------------------------------------------

--
-- Table structure for table `resident_reports`
--

CREATE TABLE `resident_reports` (
  `report_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `street_id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED DEFAULT NULL,
  `report_type` enum('Flood','Damage','Blocked Road','Fire','Medical Emergency','Other') NOT NULL,
  `severity` enum('Low','Moderate','Severe') DEFAULT 'Moderate',
  `description` text DEFAULT NULL,
  `image_path` varchar(350) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL COMMENT 'GPS from mobile if available',
  `longitude` decimal(10,7) DEFAULT NULL,
  `status` enum('Pending','Verified','In Progress','Resolved','Dismissed') NOT NULL DEFAULT 'Pending',
  `verified_by` int(10) UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Community incident reports';

--
-- Dumping data for table `resident_reports`
--

INSERT INTO `resident_reports` (`report_id`, `user_id`, `street_id`, `event_id`, `report_type`, `severity`, `description`, `image_path`, `latitude`, `longitude`, `status`, `verified_by`, `verified_at`, `resolution_notes`, `created_at`, `updated_at`) VALUES
(1, 4, 1, 1, 'Flood', 'Severe', 'Water reached floor level. Needs evacuation.', NULL, NULL, NULL, 'Verified', 2, '2024-07-24 21:00:00', NULL, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(2, 6, 6, 1, 'Flood', 'Severe', 'Rizal Ave completely submerged. Cars floating.', NULL, NULL, NULL, 'Verified', 2, '2024-07-24 21:30:00', NULL, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(3, 4, 7, 1, 'Damage', 'Severe', 'Two houses collapsed. Six families need shelter.', NULL, NULL, NULL, 'Verified', 2, '2024-07-24 22:00:00', NULL, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(4, 4, 3, 1, 'Blocked Road', 'Moderate', 'Fallen tree blocking Mabini St.', NULL, NULL, NULL, 'Resolved', 2, '2024-07-25 07:00:00', NULL, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(5, 6, 8, 1, 'Flood', 'Moderate', 'Luna St flood not receded. No clean water.', NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(6, 7, 4, NULL, 'Flood', 'Moderate', 'asdadasda', 'uploads/reports/2026/03/rpt_7_1774073660_bcd649bc.jpg', 14.7473750, 121.0468670, 'Pending', NULL, NULL, NULL, '2026-03-21 14:14:20', '2026-03-21 14:14:20'),
(7, 7, 10, NULL, 'Flood', 'Moderate', 'asdasd', 'uploads/reports/2026/03/rpt_7_1774074809_a01cd90e.jpg', 14.7473550, 121.0468430, 'Dismissed', 1, '2026-04-08 16:28:32', NULL, '2026-03-21 14:33:29', '2026-04-08 22:31:45');

-- --------------------------------------------------------

--
-- Table structure for table `resources`
--

CREATE TABLE `resources` (
  `resource_id` int(10) UNSIGNED NOT NULL,
  `resource_name` varchar(150) NOT NULL,
  `category` enum('Food','Medical','Water','Shelter','Transport','Other') NOT NULL DEFAULT 'Other',
  `unit` varchar(30) NOT NULL DEFAULT 'pcs' COMMENT 'pcs, liters, kits, trips',
  `unit_cost` decimal(10,2) DEFAULT NULL COMMENT 'Cost per unit (₱)',
  `qty_available` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `qty_reserved` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `qty_distributed` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `restock_threshold` int(10) UNSIGNED DEFAULT 50 COMMENT 'Alert when qty_available falls below',
  `supplier` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Welfare resource inventory — Feature 7';

--
-- Dumping data for table `resources`
--

INSERT INTO `resources` (`resource_id`, `resource_name`, `category`, `unit`, `unit_cost`, `qty_available`, `qty_reserved`, `qty_distributed`, `restock_threshold`, `supplier`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'Food Packs', 'Food', 'pack', 350.00, 2000, 0, 630, 50, NULL, NULL, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(2, 'Medicine Kits', 'Medical', 'kit', 820.00, 500, 0, 50, 50, NULL, NULL, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(3, 'Water Supply', 'Water', 'liter', 15.00, 10000, 0, 1800, 50, NULL, NULL, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(4, 'Shelter Kits', 'Shelter', 'kit', 4500.00, 150, 0, 30, 50, NULL, NULL, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(5, 'Evacuation Van', 'Transport', 'trip', 1200.00, 12, 0, 8, 50, NULL, NULL, '2026-03-15 23:04:48', '2026-03-15 23:04:48');

-- --------------------------------------------------------

--
-- Table structure for table `resource_distributions`
--

CREATE TABLE `resource_distributions` (
  `dist_id` int(10) UNSIGNED NOT NULL,
  `street_id` int(10) UNSIGNED NOT NULL,
  `resource_id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Linked disaster event if applicable',
  `qty_distributed` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `unit_cost_at_time` decimal(10,2) DEFAULT NULL COMMENT 'Snapshot of cost at distribution time',
  `total_cost` decimal(12,2) GENERATED ALWAYS AS (`qty_distributed` * coalesce(`unit_cost_at_time`,0)) STORED,
  `distributed_by` int(10) UNSIGNED DEFAULT NULL,
  `distributed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `recipient_count` int(10) UNSIGNED DEFAULT 0 COMMENT 'Number of households served',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Resource distribution log per street — Feature 7';

--
-- Dumping data for table `resource_distributions`
--

INSERT INTO `resource_distributions` (`dist_id`, `street_id`, `resource_id`, `event_id`, `qty_distributed`, `unit_cost_at_time`, `distributed_by`, `distributed_at`, `recipient_count`, `notes`) VALUES
(1, 1, 1, 1, 200, 350.00, 2, '2026-03-15 23:04:48', 98, NULL),
(2, 6, 1, 1, 250, 350.00, 2, '2026-03-15 23:04:48', 140, NULL),
(3, 7, 1, 1, 180, 350.00, 2, '2026-03-15 23:04:48', 118, NULL),
(4, 1, 4, 1, 30, 4500.00, 2, '2026-03-15 23:04:48', 30, NULL),
(5, 6, 2, 1, 50, 820.00, 2, '2026-03-15 23:04:48', 50, NULL),
(6, 8, 3, 1, 1800, 15.00, 2, '2026-03-15 23:04:48', 68, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `streets`
--

CREATE TABLE `streets` (
  `street_id` int(10) UNSIGNED NOT NULL,
  `zone_id` int(10) UNSIGNED NOT NULL,
  `street_name` varchar(150) NOT NULL,
  `barangay` varchar(100) NOT NULL DEFAULT 'Bagong Silang',
  `city` varchar(100) NOT NULL DEFAULT 'Caloocan City',
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `geom_polyline` text DEFAULT NULL COMMENT 'GeoJSON LineString for Leaflet.js',
  `current_risk_level` enum('RED','ORANGE','YELLOW','GREEN') DEFAULT NULL,
  `current_vuln_score` decimal(5,2) DEFAULT NULL COMMENT '0.00–100.00',
  `needs_welfare` enum('Yes','Moderate','No') DEFAULT NULL,
  `last_predicted_at` datetime DEFAULT NULL,
  `total_population` int(10) UNSIGNED DEFAULT 0,
  `total_households` int(10) UNSIGNED DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Streets — core geographic unit; risk state cached here for fast map rendering';

--
-- Dumping data for table `streets`
--

INSERT INTO `streets` (`street_id`, `zone_id`, `street_name`, `barangay`, `city`, `latitude`, `longitude`, `geom_polyline`, `current_risk_level`, `current_vuln_score`, `needs_welfare`, `last_predicted_at`, `total_population`, `total_households`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Sampaguita St', 'Bagong Silang', 'Caloocan City', 14.7421030, 120.9836540, NULL, 'RED', 79.49, 'Yes', '2026-04-08 22:38:20', 520, 112, 1, '2026-03-15 23:04:48', '2026-04-08 22:38:20'),
(2, 1, 'Narra St', 'Bagong Silang', 'Caloocan City', 14.7425110, 120.9840220, NULL, 'ORANGE', 53.84, 'Moderate', '2026-04-08 22:38:20', 410, 87, 1, '2026-03-15 23:04:48', '2026-04-08 22:38:20'),
(3, 2, 'Mabini St', 'Bagong Silang', 'Caloocan City', 14.7430600, 120.9845670, NULL, 'GREEN', 28.91, 'No', '2026-04-08 22:38:20', 680, 145, 1, '2026-03-15 23:04:48', '2026-04-08 22:38:20'),
(4, 2, 'Acacia St', 'Bagong Silang', 'Caloocan City', 14.7435200, 120.9849300, NULL, 'GREEN', 12.31, 'No', '2026-04-08 22:38:20', 375, 80, 1, '2026-03-15 23:04:48', '2026-04-08 22:38:20'),
(5, 2, 'Mahogany St', 'Bagong Silang', 'Caloocan City', 14.7440800, 120.9853120, NULL, 'GREEN', 28.98, 'No', '2026-04-08 22:38:20', 490, 104, 1, '2026-03-15 23:04:48', '2026-04-08 22:38:20'),
(6, 3, 'Rizal Ave', 'Bagong Silang', 'Caloocan City', 14.7415500, 120.9829760, NULL, 'RED', 94.48, 'Yes', '2026-04-08 22:38:20', 730, 158, 1, '2026-03-15 23:04:48', '2026-04-08 22:38:20'),
(7, 3, 'Bonifacio St', 'Bagong Silang', 'Caloocan City', 14.7410300, 120.9824500, NULL, 'RED', 85.53, 'Yes', '2026-04-08 22:38:20', 610, 131, 1, '2026-03-15 23:04:48', '2026-04-08 22:38:20'),
(8, 3, 'Luna St', 'Bagong Silang', 'Caloocan City', 14.7405700, 120.9819300, NULL, 'ORANGE', 64.83, 'Yes', '2026-04-08 22:38:20', 550, 118, 1, '2026-03-15 23:04:48', '2026-04-08 22:38:20'),
(9, 4, 'Del Pilar St', 'Bagong Silang', 'Caloocan City', 14.7450100, 120.9860400, NULL, 'GREEN', 7.14, 'No', '2026-04-08 22:38:20', 320, 68, 1, '2026-03-15 23:04:48', '2026-04-08 22:38:20'),
(10, 4, 'Aguinaldo St', 'Bagong Silang', 'Caloocan City', 14.7455900, 120.9865900, NULL, 'GREEN', 5.18, 'No', '2026-04-08 22:38:20', 280, 59, 1, '2026-03-15 23:04:48', '2026-04-08 22:38:20');

-- --------------------------------------------------------

--
-- Table structure for table `street_features`
--

CREATE TABLE `street_features` (
  `feature_id` int(10) UNSIGNED NOT NULL,
  `street_id` int(10) UNSIGNED NOT NULL,
  `snapshot_date` date NOT NULL COMMENT 'Date this feature set was computed',
  `poverty_rate_pct` decimal(5,2) DEFAULT NULL,
  `fourps_households` int(10) UNSIGNED DEFAULT 0,
  `vulnerable_population` int(10) UNSIGNED DEFAULT 0 COMMENT 'PWD + senior + pregnant + children',
  `informal_settlers_pct` decimal(5,2) DEFAULT NULL,
  `population_density` decimal(10,2) DEFAULT NULL COMMENT 'persons/km² derived from streets + population',
  `drainage_score` tinyint(3) UNSIGNED DEFAULT 0 COMMENT '0=None,1=Open,2=Closed,3=Underground',
  `road_surface_score` tinyint(3) UNSIGNED DEFAULT 0 COMMENT '0=Unpaved…3=Concrete',
  `elevation_m` decimal(7,2) DEFAULT NULL,
  `dist_to_waterway_m` decimal(8,2) DEFAULT NULL,
  `flood_frequency` tinyint(3) UNSIGNED DEFAULT 0,
  `avg_flood_height_m` decimal(5,2) DEFAULT NULL,
  `total_typhoon_impacts` tinyint(3) UNSIGNED DEFAULT 0 COMMENT 'Count of past severe impacts',
  `last_flood_severity` tinyint(3) UNSIGNED DEFAULT 0 COMMENT '0=None…3=Severely Flooded',
  `days_since_last_flood` int(10) UNSIGNED DEFAULT NULL,
  `latest_image_id` int(10) UNSIGNED DEFAULT NULL,
  `image_flood_score` decimal(5,4) DEFAULT NULL COMMENT 'ResNet softmax probability: flooded class',
  `image_damage_score` decimal(5,4) DEFAULT NULL COMMENT 'ResNet softmax probability: damaged class',
  `image_safe_score` decimal(5,4) DEFAULT NULL COMMENT 'ResNet softmax probability: safe class',
  `composite_risk_score` decimal(5,2) DEFAULT NULL COMMENT 'Weighted composite pre-model score',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ML feature vectors per street per date — input to prediction model';

--
-- Dumping data for table `street_features`
--

INSERT INTO `street_features` (`feature_id`, `street_id`, `snapshot_date`, `poverty_rate_pct`, `fourps_households`, `vulnerable_population`, `informal_settlers_pct`, `population_density`, `drainage_score`, `road_surface_score`, `elevation_m`, `dist_to_waterway_m`, `flood_frequency`, `avg_flood_height_m`, `total_typhoon_impacts`, `last_flood_severity`, `days_since_last_flood`, `latest_image_id`, `image_flood_score`, `image_damage_score`, `image_safe_score`, `composite_risk_score`, `created_at`) VALUES
(1, 1, '2024-07-24', 62.50, 38, 48, 75.00, NULL, 1, 2, 4.20, 120.00, 5, 1.60, 4, 3, NULL, 1, 0.9210, 0.0510, 0.0100, 86.50, '2026-03-15 23:04:48'),
(2, 2, '2024-07-24', 48.30, 25, 28, 55.00, NULL, 1, 2, 5.10, 200.00, 3, 0.90, 2, 1, NULL, 4, 0.1850, 0.0890, 0.0140, 68.20, '2026-03-15 23:04:48'),
(3, 3, '2024-07-24', 35.00, 22, 22, 30.00, NULL, 2, 3, 6.80, 450.00, 1, 0.30, 1, 0, NULL, NULL, NULL, NULL, NULL, 42.10, '2026-03-15 23:04:48'),
(4, 4, '2024-07-24', 18.50, 8, 13, 12.00, NULL, 2, 3, 8.50, 620.00, 0, NULL, 0, 0, NULL, 5, 0.0120, 0.0160, 0.9610, 14.30, '2026-03-15 23:04:48'),
(5, 5, '2024-07-24', 28.00, 15, 19, 22.00, NULL, 1, 2, 7.20, 380.00, 1, 0.40, 0, 0, NULL, NULL, NULL, NULL, NULL, 30.80, '2026-03-15 23:04:48'),
(6, 6, '2024-07-24', 70.20, 55, 64, 82.00, NULL, 0, 0, 2.80, 55.00, 6, 2.10, 5, 3, NULL, 2, 0.9540, 0.0310, 0.0060, 91.40, '2026-03-15 23:04:48'),
(7, 7, '2024-07-24', 65.00, 48, 58, 78.00, NULL, 1, 0, 3.20, 80.00, 5, 1.95, 4, 3, NULL, 3, 0.0900, 0.8730, 0.0120, 84.20, '2026-03-15 23:04:48'),
(8, 8, '2024-07-24', 58.00, 40, 42, 68.00, NULL, 1, 1, 3.80, 110.00, 4, 0.75, 3, 2, NULL, NULL, NULL, NULL, NULL, 60.70, '2026-03-15 23:04:48'),
(9, 9, '2024-07-24', 15.00, 6, 11, 8.00, NULL, 3, 3, 12.00, 900.00, 0, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 22.40, '2026-03-15 23:04:48'),
(10, 10, '2024-07-24', 12.00, 4, 9, 5.00, NULL, 3, 3, 14.50, 1200.00, 0, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 11.90, '2026-03-15 23:04:48');

-- --------------------------------------------------------

--
-- Table structure for table `typhoon_events`
--

CREATE TABLE `typhoon_events` (
  `event_id` int(10) UNSIGNED NOT NULL,
  `event_name` varchar(120) NOT NULL COMMENT 'e.g. Typhoon Egay',
  `local_name` varchar(120) DEFAULT NULL,
  `category` tinyint(3) UNSIGNED DEFAULT NULL COMMENT '1-5 PAGASA scale',
  `landfall_date` date DEFAULT NULL,
  `date_started` date NOT NULL,
  `date_ended` date DEFAULT NULL,
  `wind_speed_kph` decimal(6,2) DEFAULT NULL,
  `status` enum('Active','Passed','Monitoring') NOT NULL DEFAULT 'Monitoring',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Named typhoon / disaster events';

--
-- Dumping data for table `typhoon_events`
--

INSERT INTO `typhoon_events` (`event_id`, `event_name`, `local_name`, `category`, `landfall_date`, `date_started`, `date_ended`, `wind_speed_kph`, `status`, `notes`, `created_by`, `created_at`) VALUES
(1, 'Typhoon Egay', 'Egay', 3, NULL, '2024-07-23', '2024-07-25', 185.00, 'Passed', NULL, 1, '2026-03-15 23:04:48'),
(2, 'Typhoon Falcon', 'Falcon', 2, NULL, '2024-08-10', NULL, 140.00, 'Active', NULL, 1, '2026-03-15 23:04:48');

-- --------------------------------------------------------

--
-- Table structure for table `typhoon_street_impacts`
--

CREATE TABLE `typhoon_street_impacts` (
  `impact_id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `street_id` int(10) UNSIGNED NOT NULL,
  `flood_status` enum('None','Flooded','Severely Flooded') NOT NULL DEFAULT 'None',
  `damage_status` enum('None','Minor Damage','Moderate Damage','Severely Damaged') NOT NULL DEFAULT 'None',
  `flood_height_m` decimal(5,2) DEFAULT NULL COMMENT 'In meters',
  `road_accessible` tinyint(1) DEFAULT 1,
  `affected_households` int(10) UNSIGNED DEFAULT 0,
  `affected_persons` int(10) UNSIGNED DEFAULT 0,
  `report_source` enum('AI','Staff','Resident','PAGASA') NOT NULL DEFAULT 'Staff',
  `notes` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `date_recorded` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Street-level typhoon impacts — Feature 4';

--
-- Dumping data for table `typhoon_street_impacts`
--

INSERT INTO `typhoon_street_impacts` (`impact_id`, `event_id`, `street_id`, `flood_status`, `damage_status`, `flood_height_m`, `road_accessible`, `affected_households`, `affected_persons`, `report_source`, `notes`, `recorded_by`, `date_recorded`) VALUES
(1, 1, 1, 'Severely Flooded', 'Moderate Damage', 1.80, 0, 98, 452, 'AI', NULL, 2, '2024-07-24 18:00:00'),
(2, 1, 6, 'Severely Flooded', 'Moderate Damage', 2.10, 0, 140, 628, 'AI', NULL, 2, '2024-07-24 18:30:00'),
(3, 1, 7, 'Flooded', 'Severely Damaged', 1.95, 0, 118, 540, 'Staff', NULL, 2, '2024-07-24 19:00:00'),
(4, 1, 2, 'Flooded', 'Minor Damage', 0.90, 1, 52, 238, 'Staff', NULL, 2, '2024-07-24 19:30:00'),
(5, 1, 8, 'Flooded', 'Moderate Damage', 0.75, 1, 68, 312, 'Resident', NULL, 2, '2024-07-24 20:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `uploaded_images`
--

CREATE TABLE `uploaded_images` (
  `image_id` int(10) UNSIGNED NOT NULL,
  `street_id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL COMMENT 'Renamed, sanitized filename on server',
  `file_path` varchar(350) NOT NULL COMMENT 'Relative path: uploads/streets/YYYY/MM/filename.jpg',
  `file_size_kb` int(10) UNSIGNED DEFAULT NULL,
  `mime_type` varchar(60) DEFAULT 'image/jpeg',
  `width_px` int(10) UNSIGNED DEFAULT NULL,
  `height_px` int(10) UNSIGNED DEFAULT NULL,
  `capture_date` datetime DEFAULT NULL COMMENT 'When photo was actually taken',
  `capture_lat` decimal(10,7) DEFAULT NULL COMMENT 'GPS from EXIF if available',
  `capture_lng` decimal(10,7) DEFAULT NULL,
  `capture_device` varchar(120) DEFAULT NULL,
  `is_processed` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = ResNet-50 has run on this image',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Street image uploads — Feature 8; awaits ResNet-50 analysis';

--
-- Dumping data for table `uploaded_images`
--

INSERT INTO `uploaded_images` (`image_id`, `street_id`, `event_id`, `uploaded_by`, `original_filename`, `stored_filename`, `file_path`, `file_size_kb`, `mime_type`, `width_px`, `height_px`, `capture_date`, `capture_lat`, `capture_lng`, `capture_device`, `is_processed`, `is_active`, `notes`, `uploaded_at`) VALUES
(1, 1, 1, 2, 'sampaguita_flood_01.jpg', 'st1_evt1_20240724_001.jpg', 'uploads/streets/2024/07/st1_evt1_20240724_001.jpg', NULL, 'image/jpeg', NULL, NULL, '2024-07-24 09:00:00', NULL, NULL, NULL, 1, 1, NULL, '2026-03-15 23:04:48'),
(2, 6, 1, 2, 'rizal_flood_01.jpg', 'st6_evt1_20240724_001.jpg', 'uploads/streets/2024/07/st6_evt1_20240724_001.jpg', NULL, 'image/jpeg', NULL, NULL, '2024-07-24 09:30:00', NULL, NULL, NULL, 1, 1, NULL, '2026-03-15 23:04:48'),
(3, 7, 1, 2, 'bonifacio_damage_01.jpg', 'st7_evt1_20240724_001.jpg', 'uploads/streets/2024/07/st7_evt1_20240724_001.jpg', NULL, 'image/jpeg', NULL, NULL, '2024-07-24 10:00:00', NULL, NULL, NULL, 1, 1, NULL, '2026-03-15 23:04:48'),
(4, 2, 1, 2, 'narra_partial_01.jpg', 'st2_evt1_20240724_001.jpg', 'uploads/streets/2024/07/st2_evt1_20240724_001.jpg', NULL, 'image/jpeg', NULL, NULL, '2024-07-24 10:30:00', NULL, NULL, NULL, 1, 1, NULL, '2026-03-15 23:04:48'),
(5, 4, NULL, 2, 'acacia_normal_01.jpg', 'st4_20240724_001.jpg', 'uploads/streets/2024/07/st4_20240724_001.jpg', NULL, 'image/jpeg', NULL, NULL, '2024-07-24 11:00:00', NULL, NULL, NULL, 1, 1, NULL, '2026-03-15 23:04:48'),
(6, 4, NULL, 7, 'flood_6.jpg', 'rpt_7_1774073660_bcd649bc.jpg', 'uploads/reports/2026/03/rpt_7_1774073660_bcd649bc.jpg', NULL, 'image/jpeg', NULL, NULL, NULL, 14.7473750, 121.0468670, NULL, 0, 1, NULL, '2026-03-21 14:14:20'),
(7, 10, NULL, 7, 'flood_6.jpg', 'rpt_7_1774074809_a01cd90e.jpg', 'uploads/reports/2026/03/rpt_7_1774074809_a01cd90e.jpg', NULL, 'image/jpeg', NULL, NULL, NULL, 14.7473550, 121.0468430, NULL, 0, 1, NULL, '2026-03-21 14:33:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'bcrypt hash',
  `role` enum('superadmin','admin','staff','dswd_officer','labeler','resident') NOT NULL DEFAULT 'resident',
  `phone_number` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System users across all roles';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone_number`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Maria Santos', 'admin@equiaid.gov.ph', '$2y$10$WXqKgebQWKzwJvLiXyeMq.mbcsnqr5ERLf2wemg/6/Qj1pbpPpPd6', 'admin', '09171234567', 1, '2026-04-09 15:35:37', '2026-03-15 23:04:48', '2026-04-09 15:35:37'),
(2, 'Juan dela Cruz', 'staff1@equiaid.gov.ph', '$2y$10$WXqKgebQWKzwJvLiXyeMq.mbcsnqr5ERLf2wemg/6/Qj1pbpPpPd6', 'staff', '09181234567', 1, NULL, '2026-03-15 23:04:48', '2026-03-18 19:42:39'),
(3, 'Rosa Reyes', 'dswd@equiaid.gov.ph', '$2y$10$WXqKgebQWKzwJvLiXyeMq.mbcsnqr5ERLf2wemg/6/Qj1pbpPpPd6', 'dswd_officer', '09191234567', 1, NULL, '2026-03-15 23:04:48', '2026-03-18 19:42:39'),
(4, 'Pedro Gonzales', 'resident1@gmail.com', '$2y$10$WXqKgebQWKzwJvLiXyeMq.mbcsnqr5ERLf2wemg/6/Qj1pbpPpPd6', 'resident', '09201234567', 1, NULL, '2026-03-15 23:04:48', '2026-03-18 19:42:39'),
(5, 'Elena Villanueva', 'labeler1@equiaid.gov.ph', '$2y$10$WXqKgebQWKzwJvLiXyeMq.mbcsnqr5ERLf2wemg/6/Qj1pbpPpPd6', 'labeler', '09211234567', 1, NULL, '2026-03-15 23:04:48', '2026-03-18 19:42:39'),
(6, 'Carlos Bautista', 'resident2@gmail.com', '$2y$10$WXqKgebQWKzwJvLiXyeMq.mbcsnqr5ERLf2wemg/6/Qj1pbpPpPd6', 'resident', '09221234567', 1, NULL, '2026-03-15 23:04:48', '2026-03-18 19:42:39'),
(7, 'Juan Carlo David', 'juancarlodavid14@gmail.com', '$2y$10$WXqKgebQWKzwJvLiXyeMq.mbcsnqr5ERLf2wemg/6/Qj1pbpPpPd6', 'resident', '+639565535401', 1, '2026-04-09 18:36:57', '2026-03-16 20:03:00', '2026-04-09 19:24:09');

-- --------------------------------------------------------

--
-- Table structure for table `welfare_action_plans`
--

CREATE TABLE `welfare_action_plans` (
  `plan_id` int(10) UNSIGNED NOT NULL,
  `street_id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED DEFAULT NULL,
  `assistance_type` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Planned','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Planned',
  `vuln_score_before` decimal(5,2) DEFAULT NULL COMMENT 'Score before intervention',
  `vuln_score_after` decimal(5,2) DEFAULT NULL COMMENT 'Score after intervention (updated post-reassessment)',
  `risk_level_before` enum('RED','ORANGE','YELLOW','GREEN') DEFAULT NULL,
  `risk_level_after` enum('RED','ORANGE','YELLOW','GREEN') DEFAULT NULL,
  `planned_date` date DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Welfare action plans with before/after impact tracking — Features 6 & 9';

--
-- Dumping data for table `welfare_action_plans`
--

INSERT INTO `welfare_action_plans` (`plan_id`, `street_id`, `event_id`, `assistance_type`, `description`, `status`, `vuln_score_before`, `vuln_score_after`, `risk_level_before`, `risk_level_after`, `planned_date`, `started_at`, `completed_at`, `assigned_to`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Food Distribution', '200 food packs for 98 households along Sampaguita St.', 'Completed', 87.00, NULL, 'RED', NULL, '2024-07-25', '2024-07-25 14:00:00', '2024-07-25 18:00:00', 2, 1, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(2, 6, 1, 'Food Distribution', '250 food packs for 140 households along Rizal Ave.', 'Completed', 91.00, NULL, 'RED', NULL, '2024-07-25', '2024-07-25 15:00:00', '2024-07-25 19:00:00', 2, 1, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(3, 7, 1, 'Medical Assistance', 'Medical team deployed to Bonifacio St for injuries/trauma.', 'Completed', 85.00, NULL, 'RED', NULL, '2024-07-26', '2024-07-26 08:00:00', '2024-07-26 17:00:00', 2, 1, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(4, 8, 1, 'Water Supply', 'Clean water distribution — 1,800 liters to Luna St.', 'Completed', 62.00, NULL, 'ORANGE', NULL, '2024-07-26', '2024-07-26 09:00:00', '2024-07-26 14:00:00', 2, 1, '2026-03-15 23:04:48', '2026-03-15 23:04:48'),
(5, 1, 1, 'Shelter Repair', '__EXT__:{\"priority\":\"Medium\",\"needs\":[],\"steps\":[],\"beneficiary_type\":\"street\",\"beneficiary_name\":\"\",\"target_date\":\"\",\"remarks\":\"\"}||Repair of 30 damaged houses along Sampaguita St.', 'Ongoing', 87.00, NULL, 'RED', NULL, '2024-07-27', '2024-07-27 07:00:00', NULL, 2, 1, '2026-03-15 23:04:48', '2026-04-07 20:48:20'),
(6, 2, 1, 'Food Distribution', 'Follow-up distribution for 52 affected HH in Narra St.', 'Ongoing', 72.00, NULL, 'ORANGE', NULL, '2024-07-27', '2024-07-27 10:00:00', NULL, 2, 1, '2026-03-15 23:04:48', '2026-03-15 23:04:48');

-- --------------------------------------------------------

--
-- Table structure for table `zones`
--

CREATE TABLE `zones` (
  `zone_id` int(10) UNSIGNED NOT NULL,
  `zone_name` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Barangay zones / puroks';

--
-- Dumping data for table `zones`
--

INSERT INTO `zones` (`zone_id`, `zone_name`, `description`, `created_at`) VALUES
(1, 'Zone 1', 'Northern residential area', '2026-03-15 23:04:48'),
(2, 'Zone 2', 'Central commercial and residential', '2026-03-15 23:04:48'),
(3, 'Zone 3', 'Southern low-lying area — most flood-prone', '2026-03-15 23:04:48'),
(4, 'Zone 4', 'Eastern hillside, moderate elevation', '2026-03-15 23:04:48');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_al_user` (`user_id`),
  ADD KEY `idx_al_module` (`module`),
  ADD KEY `idx_al_created` (`created_at`);

--
-- Indexes for table `analytics_snapshots`
--
ALTER TABLE `analytics_snapshots`
  ADD PRIMARY KEY (`snap_id`),
  ADD UNIQUE KEY `uq_snap_date_type` (`snapshot_date`,`snapshot_type`,`event_id`),
  ADD KEY `idx_as_date` (`snapshot_date`),
  ADD KEY `idx_as_type` (`snapshot_type`),
  ADD KEY `fk_as_event` (`event_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `idx_an_category` (`category`),
  ADD KEY `idx_an_priority` (`priority`),
  ADD KEY `idx_an_pinned` (`is_pinned`),
  ADD KEY `idx_an_active` (`is_active`),
  ADD KEY `idx_an_event` (`event_id`),
  ADD KEY `idx_an_date` (`created_at`),
  ADD KEY `fk_an_author` (`author_id`);

--
-- Indexes for table `budget_allocations`
--
ALTER TABLE `budget_allocations`
  ADD PRIMARY KEY (`alloc_id`),
  ADD KEY `idx_ba_street` (`street_id`),
  ADD KEY `idx_ba_risk` (`risk_level`),
  ADD KEY `idx_ba_event` (`event_id`),
  ADD KEY `idx_ba_fiscal` (`fiscal_period`),
  ADD KEY `fk_ba_approver` (`approved_by`);

--
-- Indexes for table `demographic_indicators`
--
ALTER TABLE `demographic_indicators`
  ADD PRIMARY KEY (`demo_id`),
  ADD KEY `idx_di_street` (`street_id`),
  ADD KEY `idx_di_date` (`survey_date`),
  ADD KEY `fk_di_encoder` (`encoded_by`);

--
-- Indexes for table `evacuation_centers`
--
ALTER TABLE `evacuation_centers`
  ADD PRIMARY KEY (`center_id`),
  ADD KEY `idx_ec_zone` (`zone_id`),
  ADD KEY `idx_ec_coords` (`latitude`,`longitude`);

--
-- Indexes for table `ground_truth_labels`
--
ALTER TABLE `ground_truth_labels`
  ADD PRIMARY KEY (`label_id`),
  ADD UNIQUE KEY `uq_gtl_image` (`image_id`),
  ADD KEY `idx_gtl_street` (`street_id`),
  ADD KEY `idx_gtl_risk` (`risk_level`),
  ADD KEY `idx_gtl_welfare` (`needs_welfare`),
  ADD KEY `idx_gtl_labeler` (`labeled_by`),
  ADD KEY `idx_gtl_qa` (`is_qa_passed`),
  ADD KEY `fk_gtl_verifier` (`verified_by`);

--
-- Indexes for table `image_analysis_queue`
--
ALTER TABLE `image_analysis_queue`
  ADD PRIMARY KEY (`analysis_id`),
  ADD KEY `idx_iaq_image` (`image_id`),
  ADD KEY `idx_iaq_model` (`model_id`),
  ADD KEY `idx_iaq_status` (`status`),
  ADD KEY `idx_iaq_class` (`predicted_class`);

--
-- Indexes for table `model_registry`
--
ALTER TABLE `model_registry`
  ADD PRIMARY KEY (`model_id`),
  ADD UNIQUE KEY `uq_model_version` (`model_name`,`model_version`),
  ADD KEY `idx_mr_active` (`is_active`),
  ADD KEY `fk_mr_user` (`created_by`);

--
-- Indexes for table `prediction_results`
--
ALTER TABLE `prediction_results`
  ADD PRIMARY KEY (`prediction_id`),
  ADD KEY `idx_pr_street` (`street_id`),
  ADD KEY `idx_pr_model` (`model_id`),
  ADD KEY `idx_pr_risk` (`risk_level`),
  ADD KEY `idx_pr_welfare` (`needs_welfare`),
  ADD KEY `idx_pr_date` (`predicted_at`),
  ADD KEY `idx_pr_event` (`event_id`),
  ADD KEY `fk_pr_features` (`feature_id`),
  ADD KEY `fk_pr_analysis` (`analysis_id`),
  ADD KEY `fk_pr_user` (`triggered_by`);

--
-- Indexes for table `report_exports`
--
ALTER TABLE `report_exports`
  ADD PRIMARY KEY (`export_id`),
  ADD KEY `idx_re_type` (`report_type`),
  ADD KEY `idx_re_user` (`generated_by`);

--
-- Indexes for table `resident_reports`
--
ALTER TABLE `resident_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `idx_rr_user` (`user_id`),
  ADD KEY `idx_rr_street` (`street_id`),
  ADD KEY `idx_rr_status` (`status`),
  ADD KEY `idx_rr_type` (`report_type`),
  ADD KEY `fk_rr_event` (`event_id`),
  ADD KEY `fk_rr_verifier` (`verified_by`);

--
-- Indexes for table `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`resource_id`),
  ADD UNIQUE KEY `uq_resource_name` (`resource_name`),
  ADD KEY `idx_res_category` (`category`);

--
-- Indexes for table `resource_distributions`
--
ALTER TABLE `resource_distributions`
  ADD PRIMARY KEY (`dist_id`),
  ADD KEY `idx_rd_street` (`street_id`),
  ADD KEY `idx_rd_resource` (`resource_id`),
  ADD KEY `idx_rd_event` (`event_id`),
  ADD KEY `idx_rd_date` (`distributed_at`),
  ADD KEY `fk_rd_user` (`distributed_by`);

--
-- Indexes for table `streets`
--
ALTER TABLE `streets`
  ADD PRIMARY KEY (`street_id`),
  ADD KEY `idx_st_zone` (`zone_id`),
  ADD KEY `idx_st_risk` (`current_risk_level`),
  ADD KEY `idx_st_coords` (`latitude`,`longitude`),
  ADD KEY `idx_st_welfare` (`needs_welfare`);

--
-- Indexes for table `street_features`
--
ALTER TABLE `street_features`
  ADD PRIMARY KEY (`feature_id`),
  ADD UNIQUE KEY `uq_sf_street_date` (`street_id`,`snapshot_date`),
  ADD KEY `idx_sf_date` (`snapshot_date`),
  ADD KEY `idx_sf_image` (`latest_image_id`);

--
-- Indexes for table `typhoon_events`
--
ALTER TABLE `typhoon_events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `idx_te_status` (`status`),
  ADD KEY `idx_te_date` (`date_started`),
  ADD KEY `fk_te_user` (`created_by`);

--
-- Indexes for table `typhoon_street_impacts`
--
ALTER TABLE `typhoon_street_impacts`
  ADD PRIMARY KEY (`impact_id`),
  ADD UNIQUE KEY `uq_event_street` (`event_id`,`street_id`),
  ADD KEY `idx_tsi_street` (`street_id`),
  ADD KEY `idx_tsi_event` (`event_id`),
  ADD KEY `idx_tsi_flood` (`flood_status`),
  ADD KEY `fk_tsi_user` (`recorded_by`);

--
-- Indexes for table `uploaded_images`
--
ALTER TABLE `uploaded_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `idx_img_street` (`street_id`),
  ADD KEY `idx_img_event` (`event_id`),
  ADD KEY `idx_img_user` (`uploaded_by`),
  ADD KEY `idx_img_proc` (`is_processed`),
  ADD KEY `idx_img_date` (`uploaded_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_active` (`is_active`);

--
-- Indexes for table `welfare_action_plans`
--
ALTER TABLE `welfare_action_plans`
  ADD PRIMARY KEY (`plan_id`),
  ADD KEY `idx_wap_street` (`street_id`),
  ADD KEY `idx_wap_status` (`status`),
  ADD KEY `idx_wap_event` (`event_id`),
  ADD KEY `idx_wap_date` (`planned_date`),
  ADD KEY `fk_wap_assigned` (`assigned_to`),
  ADD KEY `fk_wap_created` (`created_by`);

--
-- Indexes for table `zones`
--
ALTER TABLE `zones`
  ADD PRIMARY KEY (`zone_id`),
  ADD UNIQUE KEY `uq_zone_name` (`zone_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `analytics_snapshots`
--
ALTER TABLE `analytics_snapshots`
  MODIFY `snap_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `budget_allocations`
--
ALTER TABLE `budget_allocations`
  MODIFY `alloc_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `demographic_indicators`
--
ALTER TABLE `demographic_indicators`
  MODIFY `demo_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `evacuation_centers`
--
ALTER TABLE `evacuation_centers`
  MODIFY `center_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ground_truth_labels`
--
ALTER TABLE `ground_truth_labels`
  MODIFY `label_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `image_analysis_queue`
--
ALTER TABLE `image_analysis_queue`
  MODIFY `analysis_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `model_registry`
--
ALTER TABLE `model_registry`
  MODIFY `model_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `prediction_results`
--
ALTER TABLE `prediction_results`
  MODIFY `prediction_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `report_exports`
--
ALTER TABLE `report_exports`
  MODIFY `export_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resident_reports`
--
ALTER TABLE `resident_reports`
  MODIFY `report_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `resources`
--
ALTER TABLE `resources`
  MODIFY `resource_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `resource_distributions`
--
ALTER TABLE `resource_distributions`
  MODIFY `dist_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `streets`
--
ALTER TABLE `streets`
  MODIFY `street_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `street_features`
--
ALTER TABLE `street_features`
  MODIFY `feature_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `typhoon_events`
--
ALTER TABLE `typhoon_events`
  MODIFY `event_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `typhoon_street_impacts`
--
ALTER TABLE `typhoon_street_impacts`
  MODIFY `impact_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `uploaded_images`
--
ALTER TABLE `uploaded_images`
  MODIFY `image_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `welfare_action_plans`
--
ALTER TABLE `welfare_action_plans`
  MODIFY `plan_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `zones`
--
ALTER TABLE `zones`
  MODIFY `zone_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `analytics_snapshots`
--
ALTER TABLE `analytics_snapshots`
  ADD CONSTRAINT `fk_as_event` FOREIGN KEY (`event_id`) REFERENCES `typhoon_events` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_an_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_an_event` FOREIGN KEY (`event_id`) REFERENCES `typhoon_events` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `budget_allocations`
--
ALTER TABLE `budget_allocations`
  ADD CONSTRAINT `fk_ba_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ba_event` FOREIGN KEY (`event_id`) REFERENCES `typhoon_events` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ba_street` FOREIGN KEY (`street_id`) REFERENCES `streets` (`street_id`) ON UPDATE CASCADE;

--
-- Constraints for table `demographic_indicators`
--
ALTER TABLE `demographic_indicators`
  ADD CONSTRAINT `fk_di_encoder` FOREIGN KEY (`encoded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_di_street` FOREIGN KEY (`street_id`) REFERENCES `streets` (`street_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `evacuation_centers`
--
ALTER TABLE `evacuation_centers`
  ADD CONSTRAINT `fk_ec_zone` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`zone_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `ground_truth_labels`
--
ALTER TABLE `ground_truth_labels`
  ADD CONSTRAINT `fk_gtl_image` FOREIGN KEY (`image_id`) REFERENCES `uploaded_images` (`image_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gtl_labeler` FOREIGN KEY (`labeled_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gtl_street` FOREIGN KEY (`street_id`) REFERENCES `streets` (`street_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gtl_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `image_analysis_queue`
--
ALTER TABLE `image_analysis_queue`
  ADD CONSTRAINT `fk_iaq_image` FOREIGN KEY (`image_id`) REFERENCES `uploaded_images` (`image_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_iaq_model` FOREIGN KEY (`model_id`) REFERENCES `model_registry` (`model_id`) ON UPDATE CASCADE;

--
-- Constraints for table `model_registry`
--
ALTER TABLE `model_registry`
  ADD CONSTRAINT `fk_mr_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `prediction_results`
--
ALTER TABLE `prediction_results`
  ADD CONSTRAINT `fk_pr_analysis` FOREIGN KEY (`analysis_id`) REFERENCES `image_analysis_queue` (`analysis_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pr_event` FOREIGN KEY (`event_id`) REFERENCES `typhoon_events` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pr_features` FOREIGN KEY (`feature_id`) REFERENCES `street_features` (`feature_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pr_model` FOREIGN KEY (`model_id`) REFERENCES `model_registry` (`model_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pr_street` FOREIGN KEY (`street_id`) REFERENCES `streets` (`street_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pr_user` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `report_exports`
--
ALTER TABLE `report_exports`
  ADD CONSTRAINT `fk_re_user` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `resident_reports`
--
ALTER TABLE `resident_reports`
  ADD CONSTRAINT `fk_rr_event` FOREIGN KEY (`event_id`) REFERENCES `typhoon_events` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rr_street` FOREIGN KEY (`street_id`) REFERENCES `streets` (`street_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rr_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `resource_distributions`
--
ALTER TABLE `resource_distributions`
  ADD CONSTRAINT `fk_rd_event` FOREIGN KEY (`event_id`) REFERENCES `typhoon_events` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rd_resource` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`resource_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rd_street` FOREIGN KEY (`street_id`) REFERENCES `streets` (`street_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rd_user` FOREIGN KEY (`distributed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `streets`
--
ALTER TABLE `streets`
  ADD CONSTRAINT `fk_st_zone` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`zone_id`) ON UPDATE CASCADE;

--
-- Constraints for table `street_features`
--
ALTER TABLE `street_features`
  ADD CONSTRAINT `fk_sf_image` FOREIGN KEY (`latest_image_id`) REFERENCES `uploaded_images` (`image_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sf_street` FOREIGN KEY (`street_id`) REFERENCES `streets` (`street_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `typhoon_events`
--
ALTER TABLE `typhoon_events`
  ADD CONSTRAINT `fk_te_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `typhoon_street_impacts`
--
ALTER TABLE `typhoon_street_impacts`
  ADD CONSTRAINT `fk_tsi_event` FOREIGN KEY (`event_id`) REFERENCES `typhoon_events` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tsi_street` FOREIGN KEY (`street_id`) REFERENCES `streets` (`street_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tsi_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `uploaded_images`
--
ALTER TABLE `uploaded_images`
  ADD CONSTRAINT `fk_img_event` FOREIGN KEY (`event_id`) REFERENCES `typhoon_events` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_img_street` FOREIGN KEY (`street_id`) REFERENCES `streets` (`street_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_img_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `welfare_action_plans`
--
ALTER TABLE `welfare_action_plans`
  ADD CONSTRAINT `fk_wap_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_wap_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_wap_event` FOREIGN KEY (`event_id`) REFERENCES `typhoon_events` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_wap_street` FOREIGN KEY (`street_id`) REFERENCES `streets` (`street_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
