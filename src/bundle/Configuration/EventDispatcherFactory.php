<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Configuration;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\EventDispatcher;
use Doctrine\Migrations\Events;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Ibexa\Bundle\DoctrineMigrations\EventListener\DryRunListener;
use Ibexa\Bundle\DoctrineMigrations\EventListener\TableCollationCheckListener;
use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;

/**
 * Builds the Ibexa-only DependencyFactory's event dispatcher (registered with its setDefinition()), with
 * {@see DryRunListener} and {@see TableCollationCheckListener} on it.
 *
 * Doctrine's own dispatcher goes through the connection's event manager, where a "doctrine.event_listener" tag would
 * reach. DBAL 4 connections have none, and a DependencyFactory made from a connection then gets an empty manager of its
 * own, which no tag reaches; so the listeners are added here instead.
 */
final class EventDispatcherFactory
{
    private DryRunListener $dryRunListener;

    private MigrationRunContext $migrationRunContext;

    public function __construct(
        DryRunListener $dryRunListener,
        MigrationRunContext $migrationRunContext
    ) {
        $this->dryRunListener = $dryRunListener;
        $this->migrationRunContext = $migrationRunContext;
    }

    public function create(DependencyFactory $dependencyFactory): EventDispatcher
    {
        $connection = $dependencyFactory->getConnection();
        // anything else subscribed to the connection's event manager (DBAL < 4) still gets the events
        // @phpstan-ignore function.alreadyNarrowedType (always true with the DBAL this branch is analysed with)
        $eventManager = method_exists(Connection::class, 'getEventManager')
            ? $connection->getEventManager()
            : new EventManager();
        $eventManager->addEventListener([Events::onMigrationsVersionExecuting], $this->dryRunListener);
        $eventManager->addEventListener(
            [Events::onMigrationsMigrating],
            new TableCollationCheckListener($this->migrationRunContext, self::getMetadataTableName($dependencyFactory))
        );

        return new EventDispatcher($connection, $eventManager);
    }

    private static function getMetadataTableName(DependencyFactory $dependencyFactory): string
    {
        $storageConfiguration = $dependencyFactory->getConfiguration()->getMetadataStorageConfiguration();

        return $storageConfiguration instanceof TableMetadataStorageConfiguration
            ? $storageConfiguration->getTableName()
            : (new TableMetadataStorageConfiguration())->getTableName();
    }
}
