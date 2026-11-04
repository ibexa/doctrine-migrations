<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Migrations;

/**
 * Whether Doctrine Migrations executes the current migration in a dry run, and whether the running
 * "ibexa:doctrine:migrations:migrate --dry-run" executes for real inside a transaction that is rolled back
 * (PostgreSQL, SQLite). Doctrine doesn't tell a migration, so the bundle's DryRunListener and console
 * DryRunSubscriber record them here.
 *
 * @internal for internal use by Ibexa 1st party packages
 */
final class MigrationRunContext
{
    private bool $dryRun = false;

    private bool $rolledBack = false;

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
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
