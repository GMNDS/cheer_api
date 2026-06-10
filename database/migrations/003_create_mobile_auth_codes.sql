CREATE TABLE `mobile_auth_codes` (
  `id` int PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `code_hash` char(64) UNIQUE NOT NULL,
  `profile_json` text NOT NULL,
  `tokens_json` text NOT NULL,
  `expires_at` timestamp NOT NULL,
  `consumed_at` timestamp NULL,
  `created_at` timestamp NOT NULL
);

CREATE INDEX `idx_mobile_auth_codes_expires_at` ON `mobile_auth_codes` (`expires_at`);
