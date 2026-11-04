CREATE TABLE one (
    id INT NOT NULL,
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_520_ci` ENGINE = InnoDB;
-- ibexa:sql-statement-separator
ALTER TABLE one ADD name VARCHAR(190) NOT NULL;
