<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Migrations;

/**
 * Whether Doctrine Migrations executes the current migration in a dry run. Doctrine tells its event listeners but not
 * the migration, so the bundle's DryRunListener records it here for {@see AbstractSqlMigration::isDryRun()}.
 *
 * @internal for internal use by Ibexa 1st party packages
 */
final class MigrationRunContext
{
    private bool $dryRun = false;

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }
}
