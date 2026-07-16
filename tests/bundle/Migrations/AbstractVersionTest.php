<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\ConcreteAbstractVersion;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\IrreversibleAbstractVersion;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AbstractVersionTest extends TestCase
{
    public function testUpQueuesOnlyStatementsApplicableToMysql(): void
    {
        $migration = $this->buildMigration($this->createMock($this->getMysqlPlatformClass()));

        $migration->up($this->createMock(Schema::class));

        self::assertSame(
            ['SELECT 1 FROM common;', 'SELECT 1 FROM mysql_only;'],
            $this->getQueuedStatements($migration),
        );
    }

    public function testUpQueuesOnlyStatementsApplicableToPostgresql(): void
    {
        $migration = $this->buildMigration($this->createMock($this->getPostgresqlPlatformClass()));

        $migration->up($this->createMock(Schema::class));

        self::assertSame(
            ['SELECT 1 FROM common;', 'SELECT 1 FROM postgresql_only;'],
            $this->getQueuedStatements($migration),
        );
    }

    public function testUpQueuesOnlyStatementsApplicableToSqlite(): void
    {
        $migration = $this->buildMigration($this->createMock($this->getSqlitePlatformClass()));

        $migration->up($this->createMock(Schema::class));

        self::assertSame(
            ['SELECT 1 FROM common;', 'SELECT 1 FROM sqlite_only;'],
            $this->getQueuedStatements($migration),
        );
    }

    public function testUpQueuesOnlyPlatformAgnosticStatementsOnUnsupportedPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(AbstractPlatform::class));

        $migration->up($this->createMock(Schema::class));

        self::assertSame(['SELECT 1 FROM common;'], $this->getQueuedStatements($migration));
    }

    public function testDownQueuesOnlyStatementsApplicableToMysql(): void
    {
        $migration = $this->buildMigration($this->createMock($this->getMysqlPlatformClass()));

        $migration->down($this->createMock(Schema::class));

        self::assertSame(
            ['SELECT 2 FROM common;', 'SELECT 2 FROM mysql_only;'],
            $this->getQueuedStatements($migration),
        );
    }

    public function testDownQueuesOnlyPlatformAgnosticStatementsOnUnsupportedPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(AbstractPlatform::class));

        $migration->down($this->createMock(Schema::class));

        self::assertSame(['SELECT 2 FROM common;'], $this->getQueuedStatements($migration));
    }

    public function testDownThrowsIrreversibleMigrationExceptionWhenYamlDeclaresNoDownSection(): void
    {
        $migration = new IrreversibleAbstractVersion($this->createMock(Connection::class), new NullLogger());

        $this->expectException(IrreversibleMigration::class);

        $migration->down($this->createMock(Schema::class));
    }

    /**
     * @return list<string>
     */
    private function getQueuedStatements(ConcreteAbstractVersion $migration): array
    {
        return array_values(array_map(
            static fn ($query): string => $query->getStatement(),
            $migration->getSql(),
        ));
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
        // DBAL 2 uses MySqlPlatform; DBAL 3 renamed it to MySQLPlatform
        return class_exists('Doctrine\\DBAL\\Platforms\\MySqlPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\MySqlPlatform'
            : 'Doctrine\\DBAL\\Platforms\\MySQLPlatform';
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private function getPostgresqlPlatformClass(): string
    {
        // DBAL 2 uses PostgreSqlPlatform; DBAL 3 renamed it to PostgreSQLPlatform
        return class_exists('Doctrine\\DBAL\\Platforms\\PostgreSqlPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\PostgreSqlPlatform'
            : 'Doctrine\\DBAL\\Platforms\\PostgreSQLPlatform';
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private function getSqlitePlatformClass(): string
    {
        // Earlier DBAL releases use SqlitePlatform; later DBAL 3 releases renamed it to SQLitePlatform
        return class_exists('Doctrine\\DBAL\\Platforms\\SqlitePlatform')
            ? 'Doctrine\\DBAL\\Platforms\\SqlitePlatform'
            : 'Doctrine\\DBAL\\Platforms\\SQLitePlatform';
    }
}
