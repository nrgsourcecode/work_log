DROP DATABASE IF EXISTS work_log;
CREATE DATABASE work_log;
USE work_log;

CREATE TABLE `customers` (
    `id` INTEGER NOT NULL AUTO_INCREMENT, 
    `name` VARCHAR(100),
    PRIMARY KEY (`id`), 
    INDEX (`name`)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;
  
INSERT INTO `customers` (`name`) VALUES ('Redrox Industrial Inc.');

CREATE TABLE `projects` (
    `id` INTEGER NOT NULL AUTO_INCREMENT, 
    `customer_id` INTEGER NOT NULL, 
    `name` VARCHAR(100),
    PRIMARY KEY (`id`), 
    INDEX (`name`)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

INSERT INTO `projects` (`name`, `customer_id`) VALUES ('Redrox ERP', 1);

CREATE TABLE `activities` (
    `id` INTEGER NOT NULL AUTO_INCREMENT, 
    `name` VARCHAR(100),
    `is_billable` TINYINT(1),
    PRIMARY KEY (`id`), 
    INDEX (`name`)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

INSERT INTO `activities` (`name`, `is_billable`) VALUES ('Idle', false), ('Web Development', true), ('Development related research', true), ('Project Management', true);

CREATE TABLE `tasks` (
    `id` INTEGER NOT NULL AUTO_INCREMENT, 
    `name` VARCHAR(100),
    PRIMARY KEY (`id`), 
    INDEX (`name`)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

CREATE TABLE `applications` (
    `id` INTEGER NOT NULL AUTO_INCREMENT, 
    `path` VARCHAR(255),
    PRIMARY KEY (`id`)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

CREATE TABLE `window_details` (
    `id` INTEGER NOT NULL AUTO_INCREMENT, 
    `application_id` INTEGER NOT NULL,
    `activity_id` INTEGER NULL, 
    `project_id` INTEGER NULL, 
    `task_id` INTEGER NULL, 
    `window_title` VARCHAR(1024),
    `file_path` VARCHAR(1024),
    `window_url` VARCHAR(1024),
    PRIMARY KEY (`id`)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

CREATE TABLE `activity_log` (
    `id` INTEGER NOT NULL AUTO_INCREMENT, 
    `window_detail_id` INTEGER NOT NULL,
    `date`  DATE DEFAULT (CURRENT_DATE),
    `seconds` INTEGER DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4;

