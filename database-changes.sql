ALTER TABLE admins
  ADD `reset_token` VARCHAR(64) NULL DEFAULT NULL,
  ADD `token_expiry` DATETIME NULL DEFAULT NULL,
  ADD UNIQUE (`reset_token`);