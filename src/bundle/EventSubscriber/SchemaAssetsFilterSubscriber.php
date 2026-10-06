<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\EventSubscriber;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Lifts the Ibexa connection's schema assets filter while an "ibexa:doctrine:migrations:*" command
 * runs.
 *
 * ibexa/core narrows that connection down to the tables its ORM entities map
 * (ManagedTablesSchemaAssetFilter), so that doctrine:schema:update leaves the rest of the schema
 * alone. Doctrine Migrations reads the schema through the same connection, so it couldn't see its
 * own "doctrine_migration_versions" table and tried to create it again on every run, failing once
 * it existed, and the Schema each migration gets lacked every table no entity maps. The installer
 * lifts the filter around its own migrations run already (SchemaAssetsFilterBypassInterface).
 */
final class SchemaAssetsFilterSubscriber implements EventSubscriberInterface
{
    private const string COMMAND_PREFIX = 'ibexa:doctrine:migrations:';

    /** @var callable|null the filter to put back, while it's lifted */
    private $previousFilter;

    public function __construct(private readonly Connection $connection) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onCommand',
            ConsoleEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $name = $event->getCommand()?->getName();
        if ($name === null || !str_starts_with($name, self::COMMAND_PREFIX)) {
            return;
        }

        $configuration = $this->connection->getConfiguration();
        $this->previousFilter = $configuration->getSchemaAssetsFilter();
        $configuration->setSchemaAssetsFilter(static fn (): bool => true);
    }

    public function onTerminate(): void
    {
        if ($this->previousFilter === null) {
            return;
        }

        $this->connection->getConfiguration()->setSchemaAssetsFilter($this->previousFilter);
        $this->previousFilter = null;
    }
}
