<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Configuration;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Events;
use Doctrine\Migrations\Metadata\MigrationPlanList;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Direction;
use Ibexa\Bundle\DoctrineMigrations\Configuration\EventDispatcherFactory;
use Ibexa\Bundle\DoctrineMigrations\EventListener\DryRunListener;
use Ibexa\Bundle\DoctrineMigrations\EventListener\TableCollationCheckListener;
use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventDispatcherFactoryTest extends TestCase
{
    public function testDispatchesMigratingToTheTableCollationCheck(): void
    {
        $context = new MigrationRunContext(['collate' => 'utf8mb4_general_ci']);
        $connection = $this->buildConnection();
        $connection->expects(self::once())->method('getDatabasePlatform')->willReturn($this->buildMysqlPlatform());
        $connection->expects(self::once())
            ->method('fetchAllKeyValue')
            ->willReturn(['ezuser' => 'utf8mb4_unicode_520_ci']);
        $factory = new EventDispatcherFactory(new DryRunListener($context), new TableCollationCheckListener($context));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(': ezuser (utf8mb4_unicode_520_ci).');

        $factory->create($this->buildDependencyFactory($connection))
            ->dispatchMigrationEvent(Events::onMigrationsMigrating, new MigrationPlanList([], Direction::UP), new MigratorConfiguration());
    }

    public function testDispatchesMigratingWithoutADisabledTableCollationCheck(): void
    {
        $context = new MigrationRunContext(['collate' => 'utf8mb4_general_ci']);
        $connection = $this->buildConnection();
        $connection->expects(self::never())->method('getDatabasePlatform');
        $connection->expects(self::never())->method('fetchAllKeyValue');
        $factory = new EventDispatcherFactory(new DryRunListener($context), null);

        $factory->create($this->buildDependencyFactory($connection))
            ->dispatchMigrationEvent(Events::onMigrationsMigrating, new MigrationPlanList([], Direction::UP), new MigratorConfiguration());
    }

    /**
     * @return Connection&MockObject
     */
    private function buildConnection(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('getEventManager')->willReturn(new EventManager());

        return $connection;
    }

    private function buildMysqlPlatform(): AbstractPlatform
    {
        // DBAL 2 uses MySqlPlatform; DBAL 3 renamed it to MySQLPlatform
        /** @var class-string<AbstractPlatform> $platformClass */
        $platformClass = class_exists('Doctrine\\DBAL\\Platforms\\MySqlPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\MySqlPlatform'
            : 'Doctrine\\DBAL\\Platforms\\MySQLPlatform';

        return new $platformClass();
    }

    private function buildDependencyFactory(Connection $connection): DependencyFactory
    {
        $dependencyFactory = $this->createStub(DependencyFactory::class);
        $dependencyFactory->method('getConnection')->willReturn($connection);

        return $dependencyFactory;
    }
}
