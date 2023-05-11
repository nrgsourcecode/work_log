DROP DATABASE IF EXISTS work_log;
CREATE DATABASE work_log;
USE work_log;

CREATE TABLE `activities` (
    `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) UNIQUE KEY,
    `is_billable` BOOLEAN DEFAULT 0
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

INSERT INTO
    `activities` (`name`, `is_billable`)
VALUES
    ('Free Time', false),
	('Web Development', true),
	('Development related research', true),
	('Project Management', true),
	('Database Development', true),
	('Consultations', true),
	('Android Development', true),
	('Desktop Development', true),
	('Testing', true);

CREATE TABLE `clients` (
    `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) UNIQUE KEY
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

INSERT INTO
    `clients` (`name`)
VALUES
    ('Redrox Industrial Inc.'),
	('Dean Bjelica'),
	('NRG SOURCE CODE');

CREATE TABLE `projects` (
    `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `client_id` INTEGER NOT NULL,
    `name` VARCHAR(100),
    `hourly_rate` DECIMAL(10, 4),
    UNIQUE KEY (`client_id`, `name`),
    FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

INSERT INTO
    `projects` (`client_id`, `name`, `hourly_rate`)
VALUES
    (1, 'Redrox ERP', 10),
	(2, 'Antalex Projects', 16.25),
	(3, 'Želim Brak', 0),
	(3, 'NRG SOURCE CODE Website', 0),
	(3, 'Work Log', 0),
    (3, 'Shy Naturals', 0),
    (3, 'Sledeći Polazak', 0),
    (3, 'Dame Biraju', 0);

CREATE TABLE `tasks` (
    `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `project_id` INTEGER NOT NULL,
    `name` VARCHAR(255),
    `description` VARCHAR(1024),
    UNIQUE KEY (`project_id`, `name`),
    FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

CREATE TABLE `applications` (
    `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `path` VARCHAR(255) UNIQUE KEY
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

CREATE TABLE `patterns` (
    `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `application_path`  VARCHAR(255),
    `activity_id` INTEGER NULL,
    `project_id` INTEGER NULL,
    `task_id` INTEGER NULL,
    `window_title` VARCHAR(1024),
    `file_path` VARCHAR(1024),
    `window_url` VARCHAR(1024),
    `override_matched_details` BOOLEAN DEFAULT 0,
    `sort_order` TINYINT(4),
    FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

INSERT INTO
    `patterns` (`application_path`, `activity_id`, `project_id`, `task_id`, `window_title`, `file_path`, `window_url`, `override_matched_details`, `sort_order`)
VALUES
    ('*teamviewer*', 8, NULL, NULL, NULL, NULL, NULL, false, NULL),
	('*signal-desktop*', 6, 1, NULL, NULL, NULL, NULL, false, NULL),
	('*dbeaver-ce*', 5, NULL, NULL, NULL, NULL, NULL, false, NULL),
	('*skypeforlinux*', 6, NULL, NULL, NULL, NULL, NULL, false, NULL),
	('*android-studio*', 7, NULL, NULL, NULL, NULL, NULL, false, NULL),
	('*code/code*', 2, NULL, NULL, NULL, NULL, NULL, false, 100),
	('*code/code*', 2, 1, NULL, NULL, '*/www/redrox*', NULL, false, NULL),
	('*code/code*', 2, 3, NULL, NULL, '*/www/zelimbrak*', NULL, false, NULL),
	('*code/code*', 2, 4, NULL, NULL, '*/www/nrgsourcecode*', NULL, false, NULL),
	('*code/code*', 2, 5, NULL, NULL, '*/www/work_log*', NULL, false, NULL),
	('/usr/bin/nautilus', 1, NULL, NULL, NULL, NULL, NULL, false, NULL),
	(NULL, 1, NULL, NULL, NULL, NULL, '*linkedin.com*', true, NULL),
	(NULL, 1, NULL, NULL, NULL, NULL, '*facebook*', true, NULL),
	(NULL, 1, NULL, NULL, NULL, NULL, '*chrome://newtab/*', true, NULL),
	(NULL, 1, NULL, NULL, NULL, NULL, '*youtube.com*', true, NULL),
	(NULL, 1, NULL, NULL, NULL, NULL, '*mail.google.com*', true, NULL),
	(NULL, 9, 1, NULL, NULL, NULL, '*redrox.local*', false, NULL),
	(NULL, 4, 1, NULL, NULL, NULL, '*nrgsourcecode.atlassian.net*', false, NULL);

CREATE TABLE `window_details` (
    `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `application_id` INTEGER NULL,
    `activity_id` INTEGER NULL,
    `project_id` INTEGER NULL,
    `task_id` INTEGER NULL,
    `window_title` VARCHAR(1024),
    `file_path` VARCHAR(1024),
    `window_url` VARCHAR(1024),
    FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

CREATE TABLE `activity_log` (
    `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `window_detail_id` INTEGER NOT NULL,
    `date`  DATE DEFAULT (CURRENT_DATE),
    `seconds` DECIMAL(10, 3) DEFAULT 0,
    UNIQUE KEY (`window_detail_id`, `date`),
    FOREIGN KEY (`window_detail_id`) REFERENCES `window_details` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

CREATE OR REPLACE VIEW base_view_detailed AS
SELECT
    al.date AS date,
    wd.project_id AS project_id,
    al.seconds,
    IF(wd.activity_id = 10, 0, al.seconds) AS seconds_in_the_office,
    IF(p.client_id <> 3, al.seconds, 0) AS seconds_worked,
    IF(wd.project_id <> 12 OR wd.activity_id = 2 OR wd.activity_id = 9, al.seconds, 0) AS billable_seconds,
    IF(wd.project_id <> 12 OR wd.activity_id = 2 OR wd.activity_id = 9, al.seconds / 3600 * p.hourly_rate, 0) AS euros_earned
FROM
    window_details AS wd INNER JOIN
    activity_log AS al ON wd.id = al.window_detail_id LEFT JOIN
    projects AS p ON wd.project_id = p.id;

CREATE OR REPLACE VIEW base_view_summary AS
SELECT
    bvd.`date`,
	FLOOR(SUM(bvd.seconds_in_the_office)) AS seconds_in_the_office,
	FLOOR(SUM(bvd.seconds)) AS seconds_total,
	FLOOR(SUM(IF(bvd.project_id = 1, bvd.seconds, 0))) AS seconds_redrox,
	FLOOR(SUM(IF(bvd.project_id = 2, bvd.seconds, 0))) AS seconds_dean,
	FLOOR(SUM(IF(bvd.project_id = 12, bvd.billable_seconds, 0))) AS seconds_richard_coding,
	FLOOR(SUM(IF(bvd.project_id = 12, bvd.seconds - bvd.billable_seconds, 0))) AS seconds_richard_research,
	FLOOR(SUM(IF(bvd.project_id = 12, bvd.seconds, 0))) AS seconds_richard,
	FLOOR(SUM(bvd.seconds_worked)) AS seconds_worked,
	SUM(IF(bvd.project_id = 1, bvd.euros_earned, 0)) AS euros_earned_redrox,
	SUM(IF(bvd.project_id = 2, bvd.euros_earned, 0)) AS euros_earned_dean,
	SUM(IF(bvd.project_id = 12, bvd.euros_earned, 0)) AS euros_earned_richard,
	SUM(bvd.euros_earned) AS euros_earned_total
FROM
    base_view_detailed bvd
GROUP BY
    bvd.`date`;
