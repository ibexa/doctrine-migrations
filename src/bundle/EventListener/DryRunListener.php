<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\EventListener;

use Doctrine\Migrations\Event\MigrationsVersionEventArgs;
use Doctrine\Migrations\Events;
use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;

/**
 * Records in {@see MigrationRunContext} whether Doctrine Migrations executes a migration in a dry run. Doctrine
 * dispatches {@see Events::onMigrationsVersionExecuting} before each migration's preUp() / preDown(), with the
 * migrator configuration it runs under, however the migrator was started. EventDispatcherFactory adds it to the
 * Ibexa-only DependencyFactory's event manager.
 */
final class DryRunListener
{
    private MigrationRunContext $migrationRunContext;

    public function __construct(MigrationRunContext $migrationRunContext)
    {
        $this->migrationRunContext = $migrationRunContext;
    }

    public function onMigrationsVersionExecuting(MigrationsVersionEventArgs $args): void
    {
        $this->migrationRunContext->setDryRun($args->getMigratorConfiguration()->isDryRun());
    }
}
