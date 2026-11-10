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
 * Projects using Doctrine ORM may set a "schema_filter" on that connection that hides the Ibexa
 * tables, so that doctrine:schema:update leaves them alone. Doctrine Migrations reads the schema
 * through the same connection, so the Schema each migration gets lacked those tables and the
 * migrations' guards misread it: an install migration ran its CREATE TABLE statements against an
 * existing database, and a migration skipping a missing table did nothing but was recorded as
 * executed.
 */
final class SchemaAssetsFilterSubscriber implements EventSubscriberInterface
{
    private const COMMAND_PREFIX = 'ibexa:doctrine:migrations:';

    private Connection $connection;

    /** @var (callable(string): bool)|null the filter to put back, while it's lifted */
    private $previousFilter;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onCommand',
            ConsoleEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        $name = $command !== null ? $command->getName() : null;
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
