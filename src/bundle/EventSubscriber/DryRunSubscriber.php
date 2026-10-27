<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\EventSubscriber;

use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Records in {@see MigrationRunContext} whether an "ibexa:doctrine:migrations:*" command runs with --dry-run or
 * --write-sql.
 */
final class DryRunSubscriber implements EventSubscriberInterface
{
    private const COMMAND_PREFIX = 'ibexa:doctrine:migrations:';

    private MigrationRunContext $migrationRunContext;

    public function __construct(MigrationRunContext $migrationRunContext)
    {
        $this->migrationRunContext = $migrationRunContext;
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
        $input = $event->getInput();

        $isMigrationsCommand = $name !== null && str_starts_with($name, self::COMMAND_PREFIX);

        $this->migrationRunContext->setDryRun(
            $isMigrationsCommand
            && $input->hasOption('dry-run')
            && $input->getOption('dry-run') === true
        );
        // Doctrine's default for --write-sql is false; given without a path, it is null.
        $this->migrationRunContext->setWritesSql(
            $isMigrationsCommand
            && $input->hasOption('write-sql')
            && $input->getOption('write-sql') !== false
        );
    }

    public function onTerminate(): void
    {
        $this->migrationRunContext->setDryRun(false);
        $this->migrationRunContext->setWritesSql(false);
    }
}
