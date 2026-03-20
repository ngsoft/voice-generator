CREATE TABLE IF NOT EXISTS `user`
(
    `id`         INTEGER PRIMARY KEY AUTOINCREMENT,
    `created_at` TEXT    NOT NULL DEFAULT (datetime(CURRENT_TIMESTAMP, 'localtime')),
    `updated_at` TEXT    NOT NULL DEFAULT (datetime(CURRENT_TIMESTAMP, 'localtime')),
    `data`       TEXT    NOT NULL DEFAULT '{}',
    `enabled`    BOOLEAN NOT NULL DEFAULT TRUE,
    `login`      TEXT    NOT NULL,
    `password`   TEXT    NOT NULL,
    `full_name`  TEXT    NULL,
    `roles`      TEXT    NOT NULL DEFAULT '["ROLE_USER"]',
    UNIQUE (`login`)
);

CREATE TRIGGER IF NOT EXISTS `user_updated_at`
    AFTER UPDATE
    ON `user`
    WHEN new.data <> old.data
        OR new.enabled <> old.enabled
        OR new.login <> old.login
        OR new.password <> old.password
        OR new.full_name <> old.full_name
        OR new.roles <> old.roles
BEGIN
    UPDATE user
    SET updated_at = datetime(CURRENT_TIMESTAMP, 'localtime')
    WHERE id = New.id;
END;


CREATE TABLE IF NOT EXISTS `access`
(
    `id`           INTEGER PRIMARY KEY AUTOINCREMENT,
    `created_at`   TEXT    NOT NULL DEFAULT (datetime(CURRENT_TIMESTAMP, 'localtime')),
    `updated_at`   TEXT    NOT NULL DEFAULT (datetime(CURRENT_TIMESTAMP, 'localtime')),
    `data`         TEXT    NOT NULL DEFAULT '{}',
    `user_id`      INTEGER          DEFAULT NULL,
    `permission`   INTEGER NOT NULL DEFAULT 0,
    `access_group` TEXT    NOT NULL,
    FOREIGN KEY (`user_id`)
        REFERENCES `user` (`id`)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,
    UNIQUE (`permission`, `access_group`, `user_id`)
);



CREATE TRIGGER IF NOT EXISTS `access_updated_at`
    AFTER UPDATE
    ON `access`
    WHEN new.data <> old.data
        OR new.permission <> old.permission
        OR new.access_group <> old.access_group
        OR new.user_id <> old.user_id
BEGIN
    UPDATE user
    SET updated_at = datetime(CURRENT_TIMESTAMP, 'localtime')
    WHERE id = New.id;
END;
