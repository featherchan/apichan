-- Plugin Migration: apichan - add-calagopus
-- Description: Add calagopus to the source type enum
-- Created: 2026-06-30 02:00:00
--
-- Safe/idempotent version: only alters the column if the table exists
-- and the enum does not already contain 'calagopus'. This lets the
-- same migration run cleanly on other FeatherPanel-based panels where
-- migration order or existing schema state may differ.

DROP PROCEDURE IF EXISTS `apichan_migrate_add_calagopus`;

DELIMITER $$

CREATE PROCEDURE `apichan_migrate_add_calagopus`()
BEGIN
    DECLARE table_exists INT DEFAULT 0;
    DECLARE column_type TEXT DEFAULT '';

    SELECT COUNT(*) INTO table_exists
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'featherpanel_apichan_sources';

    IF table_exists = 0 THEN
        -- Base table not present yet (e.g. migration order differs on
        -- another panel). Create it here so this migration can safely
        -- "inject" the full schema on its own.
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
    ELSE
        SELECT COLUMN_TYPE INTO column_type
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'featherpanel_apichan_sources'
          AND COLUMN_NAME = 'type';

        IF column_type NOT LIKE '%calagopus%' THEN
            ALTER TABLE `featherpanel_apichan_sources`
                MODIFY COLUMN `type` ENUM('pterodactyl', 'featherpanel', 'pelican', 'calagopus') NOT NULL DEFAULT 'pterodactyl';
        END IF;
    END IF;
END$$

DELIMITER ;

CALL `apichan_migrate_add_calagopus`();

DROP PROCEDURE IF EXISTS `apichan_migrate_add_calagopus`;
