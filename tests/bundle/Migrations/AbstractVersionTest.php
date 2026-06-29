<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\AbortMigration;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\ConcreteAbstractVersion;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AbstractVersionTest extends TestCase
{
    public function testUpDispatchesToUpForMysqlOnMySQLPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock($this->getMysqlPlatformClass()));

        $migration->up($this->createMock(Schema::class));

        self::assertTrue($migration->wasMysqlCalled());
        self::assertFalse($migration->wasPostgresCalled());
    }

    public function testUpDispatchesToUpForPostgresqlOnPostgresPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(PostgreSQL100Platform::class));

        $migration->up($this->createMock(Schema::class));

        self::assertFalse($migration->wasMysqlCalled());
        self::assertTrue($migration->wasPostgresCalled());
    }

    public function testUpAbortsOnUnsupportedPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(AbstractPlatform::class));

        $this->expectException(AbortMigration::class);
        $migration->up($this->createMock(Schema::class));
    }

    public function testEnsureDatabasePlatformAbortsOnUnsupportedPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(AbstractPlatform::class));

        $this->expectException(AbortMigration::class);
        $migration->up($this->createMock(Schema::class));
    }

    private function buildMigration(AbstractPlatform $platform): ConcreteAbstractVersion
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        return new ConcreteAbstractVersion($connection, new NullLogger());
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private function getMysqlPlatformClass(): string
    {
        // DBAL 3 uses MySQLPlatform; DBAL 2 uses MySqlPlatform
        return class_exists('Doctrine\\DBAL\\Platforms\\MySQLPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\MySQLPlatform'
            : 'Doctrine\\DBAL\\Platforms\\MySqlPlatform';
    }
}
