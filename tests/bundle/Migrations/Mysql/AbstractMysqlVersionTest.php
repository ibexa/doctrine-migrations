<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Migrations\Mysql;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\SkipMigration;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\ConcreteMysqlVersion;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AbstractMysqlVersionTest extends TestCase
{
    public function testUpCallsDoUpOnMySQLPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(MySQLPlatform::class));

        $migration->up($this->createMock(Schema::class));

        self::assertTrue($migration->wasDoUpCalled());
    }

    public function testUpSkipsOnNonMySQLPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(AbstractPlatform::class));

        $this->expectException(SkipMigration::class);
        $migration->up($this->createMock(Schema::class));
    }

    public function testDownCallsDoDownOnMySQLPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(MySQLPlatform::class));

        $migration->down($this->createMock(Schema::class));

        self::assertTrue($migration->wasDoDownCalled());
    }

    public function testDownSkipsOnNonMySQLPlatform(): void
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

    private function buildMigration(AbstractPlatform $platform): ConcreteMysqlVersion
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        return new ConcreteMysqlVersion($connection, new NullLogger());
    }
}
