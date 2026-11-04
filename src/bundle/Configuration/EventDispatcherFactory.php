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
use Ibexa\Bundle\DoctrineMigrations\EventListener\DryRunListener;
use Ibexa\Bundle\DoctrineMigrations\EventListener\TableCollationCheckListener;

/**
 * Builds the Ibexa-only DependencyFactory's event dispatcher (registered with its setDefinition()), with
 * {@see DryRunListener} and, unless it's disabled, {@see TableCollationCheckListener} on it.
 *
 * Doctrine's own dispatcher goes through the connection's event manager, where a "doctrine.event_listener" tag would
 * reach. DBAL 4 connections have none, and a DependencyFactory made from a connection then gets an empty manager of its
 * own, which no tag reaches; so the listeners are added here instead.
 */
final class EventDispatcherFactory
{
    private DryRunListener $dryRunListener;

    private ?TableCollationCheckListener $tableCollationCheckListener;

    public function __construct(
        DryRunListener $dryRunListener,
        ?TableCollationCheckListener $tableCollationCheckListener
    ) {
        $this->dryRunListener = $dryRunListener;
        $this->tableCollationCheckListener = $tableCollationCheckListener;
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
        if ($this->tableCollationCheckListener !== null) {
            $eventManager->addEventListener([Events::onMigrationsMigrating], $this->tableCollationCheckListener);
        }

        return new EventDispatcher($connection, $eventManager);
    }
}
