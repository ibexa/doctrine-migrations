<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Migrations\Postgres;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\SkipMigration;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\ConcretePostgresVersion;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AbstractPostgresVersionTest extends TestCase
{
    public function testUpCallsDoUpOnPostgreSQLPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(PostgreSQLPlatform::class));

        $migration->up($this->createMock(Schema::class));

        self::assertTrue($migration->wasDoUpCalled());
    }

    public function testUpSkipsOnNonPostgreSQLPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(AbstractPlatform::class));

        $this->expectException(SkipMigration::class);
        $migration->up($this->createMock(Schema::class));
    }

    public function testDownCallsDoDownOnPostgreSQLPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(PostgreSQLPlatform::class));

        $migration->down($this->createMock(Schema::class));

        self::assertTrue($migration->wasDoDownCalled());
    }

    public function testDownSkipsOnNonPostgreSQLPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(AbstractPlatform::class));

        $this->expectException(SkipMigration::class);
        $migration->down($this->createMock(Schema::class));
    }

    public function testDoUpIsNotCalledWhenPlatformDoesNotMatch(): void
    {
        $migration = $this->buildMigration($this->createMock(AbstractPlatform::class));

        try {
            $migration->up($this->createMock(Schema::class));
        } catch (SkipMigration) {
        }

        self::assertFalse($migration->wasDoUpCalled());
    }

    private function buildMigration(AbstractPlatform $platform): ConcretePostgresVersion
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        return new ConcretePostgresVersion($connection, new NullLogger());
    }
}
