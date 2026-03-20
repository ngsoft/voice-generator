CREATE TABLE IF NOT EXISTS `user`
(
    `id`         INT          NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `created_at` DATETIME     NOT NULL DEFAULT current_timestamp(),
    `updated_at` DATETIME     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `data`       LONGTEXT     NOT NULL DEFAULT '{}' CHECK (json_valid(`data`)),
    `enabled`    TINYINT(1)   NOT NULL DEFAULT 1,
    `login`      VARCHAR(180) NOT NULL,
    `password`   LONGTEXT     NOT NULL,
    `full_name`  VARCHAR(255) NULL,
    `roles`      LONGTEXT     NOT NULL DEFAULT '["ROLE_USER"]' CHECK (json_valid(`roles`)),
    UNIQUE (`login`)
);


CREATE TABLE IF NOT EXISTS `access`
(
    `id`           INT         NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `created_at`   DATETIME    NOT NULL DEFAULT current_timestamp(),
    `updated_at`   DATETIME    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `data`         LONGTEXT    NOT NULL DEFAULT '{}' CHECK (json_valid(`data`)),
    `user_id`      INT                  DEFAULT NULL,
    `permission`   TINYINT(1)  NOT NULL DEFAULT 0,
    `access_group` varchar(40) NOT NULL,
    FOREIGN KEY (`user_id`)
        REFERENCES `user` (`id`)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,
    UNIQUE (`permission`, `access_group`, `user_id`)
);
