<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\EventSubscriber;

use Ibexa\Contracts\DoctrineMigrations\Migrations\DryRunContext;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Records in {@see DryRunContext} whether an "ibexa:doctrine:migrations:*" command runs with --dry-run.
 */
final class DryRunSubscriber implements EventSubscriberInterface
{
    private const COMMAND_PREFIX = 'ibexa:doctrine:migrations:';

    private DryRunContext $dryRunContext;

    public function __construct(DryRunContext $dryRunContext)
    {
        $this->dryRunContext = $dryRunContext;
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

        $this->dryRunContext->setDryRun(
            $name !== null
            && str_starts_with($name, self::COMMAND_PREFIX)
            && $input->hasOption('dry-run')
            && $input->getOption('dry-run') === true
        );
    }

    public function onTerminate(): void
    {
        $this->dryRunContext->setDryRun(false);
    }
}
