ALTER TABLE `batch_user`
    ADD UNIQUE `batch_unique` (`game_code`, `prefix`, `batch_start`, `batch_stop`, `freecredit`) USING BTREE;

ALTER TABLE `bills`
    ADD `amount_request` DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `transfer_type`,
    ADD `amount_limit`   DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `amount_request`;

ALTER TABLE `bills`
    ADD `gameuser_code` INT(11) NOT NULL DEFAULT '0' AFTER `member_code`;

ALTER TABLE
    all_log
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    all_log
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    amphurs
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    amphurs
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bankout_config
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bankout_config
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE
    banks
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    banks
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    banks_account
    CHANGE
        checktime
        checktime
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    banks_account
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    banks_account
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    banks_configs
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    banks_configs
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bank_payment
    CHANGE
        bank_time
        bank_time
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bank_payment
    CHANGE
        date_topup
        date_topup
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bank_payment
    CHANGE
        prochek_date
        prochek_date
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bank_payment
    CHANGE
        date_approve
        date_approve
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bank_payment
    CHANGE
        date_cancel
        date_cancel
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bank_payment
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bank_payment
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    batch_user
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    batch_user
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bills
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bills
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bills_free
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bills_free
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bonus
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bonus_spin
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    bonus_spin
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    configs
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    configs
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    contacts
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    contacts
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    contents
    CHANGE
        date_approve
        date_approve
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    contents
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    contents
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    datadics
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    datadics
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    districts
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    districts
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    geographies
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    geographies
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    leagues
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    leagues
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    emp_permission
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    employees
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    employees
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    employees
    CHANGE
        lastlogin
        lastlogin
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    games
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    games
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    faq
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    faq
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    games_creditlog
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    games_creditlog
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    games_user
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    games_user
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    games_user_free
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    games_user_free
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_session
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_session
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE
    languages
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    languages
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    payments
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    payments
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members
    CHANGE
        lastlogin
        lastlogin
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members
    CHANGE
        session_limit
        session_limit
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members
    CHANGE
        payment_limit
        payment_limit
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members
    CHANGE
        payment_delay
        payment_delay
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE
    logs_type
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    logs_type
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_cashback
    CHANGE
        date_approve
        date_approve
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_cashback
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_cashback
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;


ALTER TABLE
    payments_promotion
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    payments_promotion
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    prefixs
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    prefixs
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_ic
    CHANGE
        date_approve
        date_approve
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_ic
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_ic
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_log
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_log
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_pointlog
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_pointlog
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_transfer
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_transfer
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    promotions_amount
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    promotions_amount
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;


ALTER TABLE
    menus
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    menus
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    payments_log
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    payments_log
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    payments_log_free
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    payments_log_free
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    permissions_type
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    permissions_type
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    payments_waiting
    CHANGE
        date_approve
        date_approve
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    payments_waiting
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    payments_waiting
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    promotions
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    promotions
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE
    positions
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    positions
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    promotions_time
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    promotions_time
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE
    types
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    types
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    refers
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    refers
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_eslot
    CHANGE
        date_join
        date_join
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_eslot
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_eslot
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_918Kaya
    CHANGE
        date_join
        date_join
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_918Kaya
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_918Kaya
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    view_billast_new
    CHANGE
        date_in
        date_in
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_freecredit
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    members_freecredit
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    session_res
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    session_res
    CHANGE
        date_limit
        date_limit
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_avenger
    CHANGE
        date_join
        date_join
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_avenger
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_avenger
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    rewards
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    rewards
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_gclub
    CHANGE
        date_join
        date_join
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_gclub
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_gclub
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_joker
    CHANGE
        date_join
        date_join
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_joker
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_joker
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_pgslot
    CHANGE
        date_join
        date_join
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_pgslot
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_pgslot
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_sagaming
    CHANGE
        date_join
        date_join
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_sagaming
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_sagaming
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_slotx
    CHANGE
        date_join
        date_join
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_slotx
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_slotx
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_slotxo
    CHANGE
        date_join
        date_join
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_slotxo
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_slotxo
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    tasks_permission
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    tasks_permission
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    upload_files
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    upload_files
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    websites
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    websites
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    accounces
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    accounces
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE
    withdraws
    CHANGE
        ckb_date
        ckb_date
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    withdraws
    CHANGE
        date_approve
        date_approve
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    withdraws
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    withdraws
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    permissions
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    permissions
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    permissions
    CHANGE
        deleted_at
        deleted_at
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    country
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    country
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    datafields
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    datafields
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    employees_session
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    employees_session
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE
    users_dreamtech
    CHANGE
        date_join
        date_join
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_dreamtech
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_dreamtech
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE
    temp_index
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    temp_index
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_xe88
    CHANGE
        date_join
        date_join
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_xe88
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_xe88
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    departments
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    departments
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    provinces
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    provinces
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    upload_folders
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;


ALTER TABLE
    upload_folders
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_biobet
    CHANGE
        date_join
        date_join
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_biobet
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    users_biobet
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    withdraws_free
    CHANGE
        ckb_date
        ckb_date
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    withdraws_free
    CHANGE
        date_approve
        date_approve
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    withdraws_free
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    withdraws_free
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    tasks
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    tasks
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE
    spins
    CHANGE
        date_create
        date_create
            TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE
    spins
    CHANGE
        date_update
        date_update
            TIMESTAMP NULL DEFAULT NULL;



ALTER TABLE `bills_free`
    ADD `gameuser_code` INT(11) NOT NULL DEFAULT '0' AFTER `member_code`;

ALTER TABLE `bonus_spin`
    ADD `reward_type` VARCHAR(10) NOT NULL DEFAULT 'WALLET' AFTER `bonus_name`;

ALTER TABLE `bonus_spin`
    ADD `amount` DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `reward_type`;


ALTER TABLE `configs`
    ADD notice varchar(191) null;
ALTER TABLE `configs`
    ADD mintransfer_pro decimal(10, 2) default 0.00 null;
ALTER TABLE `configs`
    ADD pro_wallet enum ('Y', 'N') default 'Y' not null;
ALTER TABLE `configs`
    ADD reward_open enum ('Y', 'N') default 'N' null;
ALTER TABLE `configs`
    ADD point_open enum ('Y', 'N') default 'Y' null;
ALTER TABLE `configs`
    ADD diamond_open enum ('Y', 'N') default 'Y' null;
ALTER TABLE `configs`
    ADD points decimal(10, 2) default 0.00 null;
ALTER TABLE `configs`
    ADD diamonds decimal(10, 2) default 0.00 null;
ALTER TABLE `configs`
    ADD logo varchar(100) default '' not null;
ALTER TABLE `configs`
    ADD favicon varchar(100) default '' not null;
ALTER TABLE `configs`
    ADD title varchar(191) default '' not null;
ALTER TABLE `configs`
    ADD description tinytext not null;
ALTER TABLE `configs`
    ADD multigame_open enum ('Y', 'N') default 'Y' not null;
ALTER TABLE `configs`
    ADD freecredit_open enum ('Y', 'N') default 'Y' not null;
ALTER TABLE `configs`
    ADD freecredit_all enum ('Y', 'N') default 'Y' not null;
ALTER TABLE `configs`
    ADD sitename varchar(100) default '' not null;
ALTER TABLE `configs`
    ADD admin_navbar_color varchar(100) default 'navbar-white navbar-light' not null;
ALTER TABLE `configs`
    ADD admin_brand_color varchar(100) default 'navbar-gray-dark' not null;
ALTER TABLE `configs`
    ADD admin_darkmode_open enum ('Y', 'N') default 'N' not null;
ALTER TABLE `configs`
    ADD wallet_navbar_color varchar(100) default '#6f0000' not null;
ALTER TABLE `configs`
    ADD wallet_body_stop_color varchar(100) default '#6f0000' not null;
ALTER TABLE `configs`
    ADD wallet_body_start_color varchar(100) default '#200122' not null;
ALTER TABLE `configs`
    ADD wallet_footer_color varchar(100) default '#6f0000' not null;
ALTER TABLE `configs`
    ADD wallet_footer_active varchar(191) default '#ffc937' not null;
ALTER TABLE `configs`
    ADD wallet_footer_exchange varchar(191) default '#6f0000' not null;

ALTER TABLE bank_payment
    ADD topup_by varchar(191) null;
ALTER TABLE bank_payment
    ADD ip_topup varchar(100) not null;


DROP TABLE IF EXISTS `daily_stat`;
CREATE TABLE `daily_stat`
(
    `code`                   int(11) UNSIGNED NOT NULL,
    `date`                   date                                               DEFAULT NULL,
    `member_all`             int(11)          NOT NULL                          DEFAULT 0,
    `member_new`             int(11)          NOT NULL                          DEFAULT 0,
    `member_new_refill`      int(11)          NOT NULL                          DEFAULT 0,
    `member_all_refill`      int(11)          NOT NULL                          DEFAULT 0,
    `deposit_count`          int(11)          NOT NULL                          DEFAULT 0,
    `deposit_sum`            decimal(10, 2)   NOT NULL                          DEFAULT 0.00,
    `withdraw_count`         int(11)          NOT NULL                          DEFAULT 0,
    `withdraw_sum`           decimal(10, 2)   NOT NULL                          DEFAULT 0.00,
    `member_new_list`        longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`member_new_list`)),
    `member_new_refill_list` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`member_new_refill_list`)),
    `created_at`             timestamp        NULL                              DEFAULT NULL,
    `updated_at`             timestamp        NULL                              DEFAULT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `daily_stat`
--
ALTER TABLE `daily_stat`
    ADD PRIMARY KEY (`code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `daily_stat`
--
ALTER TABLE `daily_stat`
    MODIFY `code` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

ALTER TABLE `employees`
    ADD `google2fa_secret` VARCHAR(191) NULL     DEFAULT NULL AFTER `lastlogin`,
    ADD `google2fa_enable` TINYINT(1)   NOT NULL DEFAULT '0' AFTER `google2fa_secret`;

ALTER TABLE `games_user`
    ADD `bill_code`      INT(11)        NOT NULL DEFAULT '0' AFTER `date_update`,
    ADD `pro_code`       INT(11)        NOT NULL DEFAULT '0' AFTER `bill_code`,
    ADD `amount`         DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `pro_code`,
    ADD `bonus`          DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `amount`,
    ADD `turnpro`        DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `bonus`,
    ADD `amount_balance` DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `turnpro`,
    ADD `withdraw_limit` DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `amount_balance`;

ALTER TABLE `members_credit_log`
    ADD `bank_code` INT(11) NOT NULL DEFAULT '0' AFTER `game_code`,
    ADD `pro_code`  INT(11) NOT NULL DEFAULT '0' AFTER `bank_code`;

ALTER TABLE `members_credit_log`
    ADD `refer_code`  INT(11)     NOT NULL DEFAULT '0' AFTER `credit_type`,
    ADD `refer_table` VARCHAR(10) NOT NULL AFTER `refer_code`;

ALTER TABLE `members_credit_log`
    ADD `amount`         DECIMAL(10, 0) NOT NULL DEFAULT '0.00' AFTER `refer_table`,
    ADD `bonus`          DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `amount`,
    ADD `total`          DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `bonus`,
    ADD `balance_before` DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `total`,
    ADD `balance_after`  DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `balance_before`;

ALTER TABLE `members_credit_log`
    ADD `credit_bonus` DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `credit`,
    ADD `credit_total` DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `credit_bonus`,
    ADD `credit_after` DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `credit_total`;


SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `medusa168_wallet`
--

-- --------------------------------------------------------

--
-- Table structure for table `members_diamondlog`
--

DROP TABLE IF EXISTS `members_diamondlog`;
CREATE TABLE `members_diamondlog`
(
    `code`            int(11) UNSIGNED NOT NULL,
    `member_code`     int(11)          NOT NULL DEFAULT 0,
    `gameuser_code`   int(11)          NOT NULL DEFAULT 0,
    `game_code`       int(11)          NOT NULL DEFAULT 0,
    `diamond_type`    enum ('D','W')   NOT NULL DEFAULT 'D',
    `diamond`         decimal(10, 2)   NOT NULL DEFAULT 0.00,
    `diamond_amount`  decimal(10, 2)   NOT NULL DEFAULT 0.00,
    `diamond_before`  decimal(10, 2)   NOT NULL DEFAULT 0.00,
    `diamond_balance` decimal(10, 2)   NOT NULL DEFAULT 0.00,
    `ip`              varchar(30)      NOT NULL DEFAULT '',
    `auto`            enum ('Y','N')   NOT NULL DEFAULT 'N',
    `remark`          tinytext         NOT NULL,
    `enable`          enum ('Y','N')   NOT NULL DEFAULT 'Y',
    `emp_code`        int(11)          NOT NULL DEFAULT 0,
    `user_create`     varchar(100)     NOT NULL DEFAULT '',
    `user_update`     varchar(100)     NOT NULL DEFAULT '',
    `date_create`     timestamp        NULL     DEFAULT NULL,
    `date_update`     timestamp        NULL     DEFAULT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `members_diamondlog`
--
ALTER TABLE `members_diamondlog`
    ADD PRIMARY KEY (`code`),
    ADD KEY `member_code` (`member_code`),
    ADD KEY `emp_code` (`emp_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `members_diamondlog`
--
ALTER TABLE `members_diamondlog`
    MODIFY `code` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `medusa168_wallet`
--

-- --------------------------------------------------------

--
-- Table structure for table `members_reward_logs`
--

CREATE TABLE `members_reward_logs`
(
    `code`          int(11) UNSIGNED NOT NULL,
    `member_code`   int(11)          NOT NULL DEFAULT 0,
    `reward_code`   int(11)          NOT NULL DEFAULT 0,
    `point`         decimal(10, 2)   NOT NULL DEFAULT 0.00,
    `point_amount`  decimal(10, 2)   NOT NULL DEFAULT 0.00,
    `point_before`  decimal(10, 2)   NOT NULL DEFAULT 0.00,
    `point_balance` decimal(10, 2)   NOT NULL DEFAULT 0.00,
    `ip`            varchar(30)      NOT NULL DEFAULT '',
    `remark`        text                      DEFAULT NULL,
    `approve`       tinyint(1)       NOT NULL DEFAULT 0,
    `date_approve`  timestamp        NULL     DEFAULT NULL,
    `ip_admin`      varchar(30)               DEFAULT NULL,
    `enable`        enum ('Y','N')   NOT NULL DEFAULT 'Y',
    `emp_code`      int(11)          NOT NULL DEFAULT 0,
    `user_create`   varchar(100)     NOT NULL DEFAULT '',
    `user_update`   varchar(100)     NOT NULL DEFAULT '',
    `date_create`   timestamp        NULL     DEFAULT NULL,
    `date_update`   timestamp        NULL     DEFAULT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `members_reward_logs`
--
ALTER TABLE `members_reward_logs`
    ADD PRIMARY KEY (`code`),
    ADD KEY `member_code` (`member_code`),
    ADD KEY `emp_code` (`emp_code`),
    ADD KEY `reward_code` (`reward_code`);

--
-- AUTO_INCREMENT for dumped tables

ALTER TABLE `promotions`
    ADD `withdraw_limit` DECIMAL(10, 2) NOT NULL DEFAULT '0.00' AFTER `date_update`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `medusa168_wallet`
--

-- --------------------------------------------------------

--
-- Table structure for table `promotions_content`
--

CREATE TABLE `promotions_content`
(
    `code`        int(11)        NOT NULL,
    `name_th`     varchar(100)   NOT NULL DEFAULT '',
    `sort`        int(11)        NOT NULL DEFAULT 0,
    `content`     longtext       NOT NULL,
    `filepic`     varchar(255)   NOT NULL DEFAULT '',
    `enable`      enum ('Y','N') NOT NULL DEFAULT 'Y',
    `user_create` varchar(100)   NOT NULL,
    `user_update` varchar(100)   NOT NULL,
    `date_create` datetime       NOT NULL DEFAULT '0000-00-00 00:00:00',
    `date_update` datetime       NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `promotions_content`
--
ALTER TABLE `promotions_content`
    ADD PRIMARY KEY (`code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `promotions_content`
--
ALTER TABLE `promotions_content`
    MODIFY `code` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

ALTER TABLE `spins`
    ADD `types` VARCHAR(10) NOT NULL DEFAULT 'WALLET' AFTER `name`;


ALTER TABLE `employees`
    ADD password varchar(191) null;
ALTER TABLE `employees`
    ADD role_id int unsigned not null;
ALTER TABLE `employees`
    ADD lastlogin timestamp null;
ALTER TABLE `employees`
    ADD google2fa_secret varchar(191) null;
ALTER TABLE `employees`
    ADD google2fa_enable tinyint(1) default 0 not null;

ALTER TABLE `games_user`
    ADD bill_code int default 0 not null;
ALTER TABLE `games_user`
    ADD pro_code int default 0 not null;
ALTER TABLE `games_user`
    ADD amount decimal(10, 2) default 0.00 not null;
ALTER TABLE `games_user`
    ADD bonus decimal(10, 2) default 0.00 not null;
ALTER TABLE `games_user`
    ADD turnpro decimal(10, 2) default 0.00 not null;
ALTER TABLE `games_user`
    ADD amount_balance decimal(10, 2) default 0.00 not null;

ALTER TABLE `members`
    ADD password varchar(191) null;
ALTER TABLE `members`
    ADD last_seen timestamp null;
