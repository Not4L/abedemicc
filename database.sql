-- Struktur database Abedemic.
-- Import di phpMyAdmin (Laragon) sebelum memakai aplikasinya.

CREATE DATABASE IF NOT EXISTS `abedemic`
	DEFAULT CHARACTER SET utf8mb4
	COLLATE utf8mb4_unicode_ci;

USE `abedemic`;

DROP TABLE IF EXISTS `chat_messages`;
DROP TABLE IF EXISTS `chat_conversations`;
DROP TABLE IF EXISTS `summaries`;
DROP TABLE IF EXISTS `quizzes`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
	`id` int unsigned NOT NULL AUTO_INCREMENT,
	`username` varchar(30) NOT NULL,
	`email` varchar(100) NOT NULL,
	`password_hash` varchar(255) NOT NULL,
	`language` enum('id','en') NOT NULL DEFAULT 'id',
	`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `username` (`username`),
	UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Satu baris = satu obrolan. Judul diambil dari pertanyaan pertama siswa.
CREATE TABLE `chat_conversations` (
	`id` int unsigned NOT NULL AUTO_INCREMENT,
	`user_id` int unsigned NOT NULL,
	`title` varchar(150) NOT NULL,
	`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `user_id` (`user_id`),
	CONSTRAINT `chat_conversations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chat_messages` (
	`id` int unsigned NOT NULL AUTO_INCREMENT,
	`user_id` int unsigned NOT NULL,
	`conversation_id` int unsigned DEFAULT NULL,
	`role` enum('user','ai') NOT NULL,
	`message` text NOT NULL,
	`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `user_id` (`user_id`),
	KEY `chat_messages_conversation` (`conversation_id`),
	CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `summaries` (
	`id` int unsigned NOT NULL AUTO_INCREMENT,
	`user_id` int unsigned NOT NULL,
	`source_label` varchar(150) NOT NULL,
	`content` mediumtext NOT NULL,
	`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `user_id` (`user_id`),
	CONSTRAINT `summaries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `quizzes` (
	`id` int unsigned NOT NULL AUTO_INCREMENT,
	`user_id` int unsigned NOT NULL,
	`source_label` varchar(150) NOT NULL,
	`questions` json NOT NULL,
	`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `user_id` (`user_id`),
	CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
