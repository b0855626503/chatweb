ALTER TABLE `dumbovip_wallet`.`batch_user` DROP INDEX `batch_unique`, ADD UNIQUE `batch_unique` (`game_code`, `prefix`, `batch_start`, `batch_stop`, `freecredit`) USING BTREE;
