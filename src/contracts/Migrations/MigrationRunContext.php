<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Migrations;

/**
 * What Doctrine Migrations doesn't tell a migration about its run: whether it executes the migration in a dry run
 * (the bundle's DryRunListener records it here for {@see AbstractSqlMigration::isDryRun()}), and the table charset
 * and collation configured in "ibexa_doctrine_schema.tables.options" (for {@see AbstractSqlMigration::addSqlFile()}).
 *
 * @internal for internal use by Ibexa 1st party packages
 */
final class MigrationRunContext
{
    private const DEFAULT_MYSQL_TABLE_CHARSET = 'utf8mb4';

    private const DEFAULT_MYSQL_TABLE_COLLATION = 'utf8mb4_unicode_520_ci';

    private bool $dryRun = false;

    private ?string $tableCharset;

    private ?string $tableCollation;

    /**
     * @param array<string, scalar|null> $tableOptions the "ibexa.schema.default_table_options" parameter
     */
    public function __construct(array $tableOptions = [])
    {
        $this->tableCharset = self::getTableOption($tableOptions, 'charset');
        // DBAL 2 reads "collate", DBAL 4 only "collation"
        $this->tableCollation = self::getTableOption($tableOptions, 'collation')
            ?? self::getTableOption($tableOptions, 'collate');
    }

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    /**
     * The charset MySQL and MariaDB tables created by {@see AbstractSqlMigration::addSqlFile()} get, null when only a
     * collation is configured and the server takes the charset from it.
     */
    public function getMysqlTableCharset(): ?string
    {
        if ($this->tableCharset !== null) {
            return $this->tableCharset;
        }

        return $this->tableCollation === null ? self::DEFAULT_MYSQL_TABLE_CHARSET : null;
    }

    /**
     * The collation MySQL and MariaDB tables created by {@see AbstractSqlMigration::addSqlFile()} get: like DBAL, a
     * charset configured without a collation gets the charset's "_unicode_ci" one.
     */
    public function getMysqlTableCollation(): string
    {
        if ($this->tableCollation !== null) {
            return $this->tableCollation;
        }

        return $this->tableCharset !== null ? $this->tableCharset . '_unicode_ci' : self::DEFAULT_MYSQL_TABLE_COLLATION;
    }

    /**
     * @param array<string, scalar|null> $tableOptions
     */
    private static function getTableOption(
        array $tableOptions,
        string $name
    ): ?string {
        $value = $tableOptions[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
