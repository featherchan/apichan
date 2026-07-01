-- Plugin Migration: apichan - apichan-sources
-- Description: Store external panel source connections for Apichan
-- Created: 2026-06-30 01:00:00
-- Plugin-specific migration for apichan
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
