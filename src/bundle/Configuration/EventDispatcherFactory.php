<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Configuration;

use Doctrine\Common\EventManager;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\EventDispatcher;
use Doctrine\Migrations\Events;
use Ibexa\Bundle\DoctrineMigrations\EventListener\DryRunListener;

/**
 * Builds the Ibexa-only DependencyFactory's event dispatcher (registered with its setDefinition()), with
 * {@see DryRunListener} on it.
 *
 * DBAL 4 connections have no event manager, so a DependencyFactory made from a connection dispatches through an empty
 * manager of its own, which no "doctrine.event_listener" tag reaches; the listener is added to it here instead.
 */
final class EventDispatcherFactory
{
    private DryRunListener $dryRunListener;

    public function __construct(DryRunListener $dryRunListener)
    {
        $this->dryRunListener = $dryRunListener;
    }

    public function create(DependencyFactory $dependencyFactory): EventDispatcher
    {
        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::onMigrationsVersionExecuting], $this->dryRunListener);

        return new EventDispatcher($dependencyFactory->getConnection(), $eventManager);
    }
}
