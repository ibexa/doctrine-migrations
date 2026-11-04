<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\EventListener;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Event\MigrationsVersionEventArgs;
use Doctrine\Migrations\Events;
use Doctrine\Migrations\Metadata\MigrationPlan;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\Version;
use Ibexa\Bundle\DoctrineMigrations\EventListener\DryRunListener;
use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;
use PHPUnit\Framework\TestCase;

final class DryRunListenerTest extends TestCase
{
    public function testRecordsTheDryRunOfEachMigration(): void
    {
        $context = new MigrationRunContext();
        $eventManager = new EventManager();
        // the way the "doctrine.event_listener" tag registers it
        $eventManager->addEventListener([Events::onMigrationsVersionExecuting], new DryRunListener($context));

        $eventManager->dispatchEvent(Events::onMigrationsVersionExecuting, $this->buildEventArgs(true));
        self::assertTrue($context->isDryRun());

        $eventManager->dispatchEvent(Events::onMigrationsVersionExecuting, $this->buildEventArgs(false));
        self::assertFalse($context->isDryRun());
    }

    private function buildEventArgs(bool $dryRun): MigrationsVersionEventArgs
    {
        return new MigrationsVersionEventArgs(
            $this->createMock(Connection::class),
            new MigrationPlan(new Version('Test'), $this->createMock(AbstractMigration::class), Direction::UP),
            (new MigratorConfiguration())->setDryRun($dryRun),
        );
    }
}
