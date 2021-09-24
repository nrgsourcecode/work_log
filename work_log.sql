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
    UNIQUE KEY (`client_id`, `name`),
    FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

INSERT INTO
    `projects` (`name`, `client_id`)
VALUES
    ('Redrox ERP', 1),
	('Antalex Projects', 2),
	('Želim Brak', 3),
	('NRG SOURCE CODE Website', 3),
	('Work Log', 3);

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

