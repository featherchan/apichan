-- Plugin Migration: apichan - apichan-remote-servers
-- Description: Store pinned remote servers for Apichan remote control feature
-- Created: 2026-07-01 01:00:00
--
-- Safe/idempotent version: ensures the `featherpanel_apichan_sources`
-- table this depends on exists before creating the remote_servers table,
-- so this migration can self-inject cleanly even if run out of order
-- or on a different FeatherPanel-based panel.

DROP PROCEDURE IF EXISTS `apichan_migrate_remote_servers`;

DELIMITER $$

CREATE PROCEDURE `apichan_migrate_remote_servers`()
BEGIN
    DECLARE sources_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO sources_exists
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'featherpanel_apichan_sources';

    IF sources_exists = 0 THEN
        CREATE TABLE `featherpanel_apichan_sources` (
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
    END IF;

    CREATE TABLE IF NOT EXISTS `featherpanel_apichan_remote_servers` (
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
END$$

DELIMITER ;

CALL `apichan_migrate_remote_servers`();

DROP PROCEDURE IF EXISTS `apichan_migrate_remote_servers`;
