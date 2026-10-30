<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Migrations;

/**
 * Whether the running "ibexa:doctrine:migrations:*" command was given --dry-run or --write-sql, and whether its dry
 * run executes for real inside a transaction that is rolled back (PostgreSQL, SQLite). Doctrine Migrations doesn't
 * tell a migration, so the bundle's console subscriber records it here for {@see AbstractSqlMigration}.
 *
 * @internal for internal use by Ibexa 1st party packages
 */
final class MigrationRunContext
{
    private bool $dryRun = false;

    private bool $writesSql = false;

    private bool $rolledBack = false;

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function setWritesSql(bool $writesSql): void
    {
        $this->writesSql = $writesSql;
    }

    public function writesSql(): bool
    {
        return $this->writesSql;
    }

    /**
     * A rolled-back dry run executes the migrations, so {@see isDryRun()} is false for them.
     */
    public function setRolledBack(bool $rolledBack): void
    {
        $this->rolledBack = $rolledBack;
    }

    public function isRolledBack(): bool
    {
        return $this->rolledBack;
    }
}
