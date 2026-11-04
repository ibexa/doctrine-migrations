<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Migrations;

/**
 * What Doctrine Migrations doesn't tell a migration about its run: whether the running "ibexa:doctrine:migrations:*"
 * command was given --dry-run (the bundle's console subscriber records it here for
 * {@see AbstractSqlMigration::isDryRun()}), and the table charset and collation configured in
 * "ibexa_doctrine_schema.tables.options" (for {@see AbstractSqlMigration::addSqlFile()}).
 *
 * @internal for internal use by Ibexa 1st party packages
 */
final class MigrationRunContext
{
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

    public function getTableCharset(): ?string
    {
        return $this->tableCharset;
    }

    public function getTableCollation(): ?string
    {
        return $this->tableCollation;
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
