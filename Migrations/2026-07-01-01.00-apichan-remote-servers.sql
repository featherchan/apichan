-- Plugin Migration: apichan - apichan-remote-servers
-- Description: Store pinned remote servers for Apichan remote control feature
-- Created: 2026-07-01 01:00:00
--
-- Plain, idempotent statements only (no DELIMITER / stored procedures —
-- FeatherPanel executes migrations via PDO). Ensures the sources table
-- this depends on exists first (FK target), then creates this table.

CREATE TABLE
    IF NOT EXISTS `featherpanel_apichan_sources` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `type` ENUM ('pterodactyl', 'featherpanel', 'pelican', 'calagopus') NOT NULL DEFAULT 'pterodactyl',
        `url` VARCHAR(2048) NOT NULL,
        `api_key` TEXT NOT NULL COMMENT 'AES-256-CBC encrypted API key',
        `timeout` INT NOT NULL DEFAULT 15,
        `created_by` INT NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted` ENUM ('false', 'true') NOT NULL DEFAULT 'false',
        FOREIGN KEY (`created_by`) REFERENCES `featherpanel_users` (`id`) ON DELETE CASCADE,
        PRIMARY KEY (`id`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

CREATE TABLE
    IF NOT EXISTS `featherpanel_apichan_remote_servers` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `user_id` INT NOT NULL,
        `source_id` INT NOT NULL,
        `remote_server_id` VARCHAR(255) NOT NULL,
        `remote_server_identifier` VARCHAR(255) DEFAULT NULL,
        `name` VARCHAR(255) NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`user_id`) REFERENCES `featherpanel_users` (`id`) ON DELETE CASCADE,
        FOREIGN KEY (`source_id`) REFERENCES `featherpanel_apichan_sources` (`id`) ON DELETE CASCADE
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;
