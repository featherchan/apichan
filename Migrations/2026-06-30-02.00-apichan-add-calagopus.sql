-- Plugin Migration: apichan - add-calagopus
-- Description: Add calagopus to the source type enum
-- Created: 2026-06-30 02:00:00
--
-- Plain, idempotent statements only (no DELIMITER / stored procedures —
-- FeatherPanel executes migrations via PDO, which does not support the
-- MySQL CLI's DELIMITER syntax or multi-statement procedure blocks).
--
-- Ensures the base table exists first (in case migration order differs
-- on another FeatherPanel-based panel), then safely (re)applies the
-- enum change. MODIFY COLUMN is safe to run even if 'calagopus' is
-- already present — it just re-declares the same column definition.

CREATE TABLE
    IF NOT EXISTS `featherpanel_apichan_sources` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `type` ENUM ('pterodactyl', 'featherpanel', 'pelican') NOT NULL DEFAULT 'pterodactyl',
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

ALTER TABLE `featherpanel_apichan_sources`
    MODIFY COLUMN `type` ENUM('pterodactyl', 'featherpanel', 'pelican', 'calagopus') NOT NULL DEFAULT 'pterodactyl';
