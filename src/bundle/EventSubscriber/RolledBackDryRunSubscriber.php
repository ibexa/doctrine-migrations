<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\Migrations\Event\MigrationsEventArgs;
use Doctrine\Migrations\Events;
use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;

/**
 * Wraps a rolled-back dry run (see {@see DryRunSubscriber}) in one transaction and rolls it back once every migration
 * has run: the migrations execute for real, each one seeing what the ones before it did, and nothing is kept.
 */
final class RolledBackDryRunSubscriber implements EventSubscriber
{
    private MigrationRunContext $migrationRunContext;

    public function __construct(MigrationRunContext $migrationRunContext)
    {
        $this->migrationRunContext = $migrationRunContext;
    }

    public function getSubscribedEvents(): array
    {
        return [Events::onMigrationsMigrating, Events::onMigrationsMigrated];
    }

    public function onMigrationsMigrating(MigrationsEventArgs $args): void
    {
        if ($this->migrationRunContext->isRolledBack()) {
            $args->getConnection()->beginTransaction();
        }
    }

    public function onMigrationsMigrated(MigrationsEventArgs $args): void
    {
        if (!$this->migrationRunContext->isRolledBack()) {
            return;
        }

        // A transactional migration commits its own, nested transaction; anything still open goes back too.
        $connection = $args->getConnection();
        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    }
}
