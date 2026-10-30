<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\EventSubscriber;

use Doctrine\DBAL\Connection;
use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformName;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformResolver;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Records in {@see MigrationRunContext} whether an "ibexa:doctrine:migrations:*" command runs with --dry-run or
 * --write-sql.
 *
 * A dry run sees the database as it was before the run, so a migration can't tell what the ones before it would have
 * changed, and its SQL file isn't complete on its own. On PostgreSQL and SQLite, where schema changes are
 * transactional, "ibexa:doctrine:migrations:migrate --dry-run" therefore runs the migrations for real inside a
 * transaction that {@see RolledBackDryRunSubscriber} rolls back at the end: its file is everything the run did.
 */
final class DryRunSubscriber implements EventSubscriberInterface
{
    private const COMMAND_PREFIX = 'ibexa:doctrine:migrations:';

    private const MIGRATE_COMMAND = 'ibexa:doctrine:migrations:migrate';

    public function __construct(
        private readonly MigrationRunContext $migrationRunContext,
        private readonly Connection $connection
    ) {}

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
        $input = $event->getInput();

        $isMigrationsCommand = $name !== null && str_starts_with($name, self::COMMAND_PREFIX);
        $dryRun = $isMigrationsCommand && $input->hasOption('dry-run') && $input->getOption('dry-run') === true;
        // Doctrine's default for --write-sql is false; given without a path, it is null.
        $writesSql = $isMigrationsCommand && $input->hasOption('write-sql') && $input->getOption('write-sql') !== false;

        $platform = $dryRun ? DatabasePlatformResolver::resolveName($this->connection->getDatabasePlatform()) : null;
        if ($name === self::MIGRATE_COMMAND && ($platform === DatabasePlatformName::PostgreSQL || $platform === DatabasePlatformName::SQLite)) {
            // Nothing the run does is kept, so there's nothing to confirm. RolledBackDryRunConfigurationFactory makes it
            // a real run for Doctrine.
            $input->setInteractive(false);
            $event->getOutput()->writeln(sprintf(
                '<comment>Dry run on %s: the migrations run inside a transaction that is rolled back at the end, '
                . 'so each one sees what the ones before it did.</comment>',
                $platform === DatabasePlatformName::PostgreSQL ? 'PostgreSQL' : 'SQLite',
            ));
            $this->migrationRunContext->setRolledBack(true);
            $dryRun = false;
            $writesSql = true;
        } elseif ($name === self::MIGRATE_COMMAND && $dryRun && $writesSql) {
            $event->getOutput()->writeln(
                '<comment>On MySQL and MariaDB a dry run can\'t see what the migrations before each one would change, '
                . 'so this file needs a migrate after it. For a file that is complete on its own, run migrate '
                . '--write-sql against a copy of the database.</comment>'
            );
        }

        $this->migrationRunContext->setDryRun($dryRun);
        $this->migrationRunContext->setWritesSql($writesSql);
    }

    public function onTerminate(): void
    {
        $this->migrationRunContext->setDryRun(false);
        $this->migrationRunContext->setWritesSql(false);
        $this->migrationRunContext->setRolledBack(false);
    }
}
