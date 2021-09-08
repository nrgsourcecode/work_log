DROP DATABASE IF EXISTS work_log;
CREATE DATABASE work_log;
USE work_log;

CREATE TABLE `activities` (
    `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) UNIQUE KEY,
    `is_billable` TINYINT(1)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

INSERT INTO `activities` (`name`, `is_billable`) VALUES ('Idle', false), ('Web Development', true), ('Development related research', true), ('Project Management', true), ('Database Development', true), ('Consultations', true);

CREATE TABLE `clients` (
    `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) UNIQUE KEY
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

INSERT INTO `clients` (`name`) VALUES ('Redrox Industrial Inc.'), ('Dean Bjelica'), ('NRG SOURCE CODE');

CREATE TABLE `projects` (
    `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `client_id` INTEGER NOT NULL,
    `name` VARCHAR(100),
    UNIQUE KEY (`client_id`, `name`),
    FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

INSERT INTO `projects` (`name`, `client_id`) VALUES ('Redrox ERP', 1), ('Antalex Projects', 2), ('Želim Brak', 3), ('NRG SOURCE CODE Website', 3), ('Work Log', 3);

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
    `application_id` INTEGER NOT NULL,
    `activity_id` INTEGER NULL,
    `project_id` INTEGER NULL,
    `task_id` INTEGER NULL,
    `window_title` VARCHAR(512),
    `file_path` VARCHAR(512),
    `window_url` VARCHAR(512),
    FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

CREATE TABLE `window_details` (
    `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `application_id` INTEGER NOT NULL,
    `activity_id` INTEGER NULL,
    `project_id` INTEGER NULL,
    `task_id` INTEGER NULL,
    `window_title` VARCHAR(512),
    `file_path` VARCHAR(512),
    `window_url` VARCHAR(512),
    FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

CREATE TABLE `activity_log` (
    `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `window_detail_id` INTEGER NOT NULL,
    `date`  DATE DEFAULT (CURRENT_DATE),
    `seconds` INTEGER DEFAULT 0,
    UNIQUE KEY (`window_detail_id`, `date`),
    FOREIGN KEY (`window_detail_id`) REFERENCES `window_details` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

