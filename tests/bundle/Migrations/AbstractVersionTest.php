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
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\ConcreteAbstractVersion;
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
        // DBAL 3 uses MySQLPlatform; DBAL 2 uses MySqlPlatform
        return class_exists('Doctrine\\DBAL\\Platforms\\MySQLPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\MySQLPlatform'
            : 'Doctrine\\DBAL\\Platforms\\MySqlPlatform';
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private function getPostgresqlPlatformClass(): string
    {
        // DBAL 3 uses PostgreSQLPlatform; DBAL 2 uses PostgreSqlPlatform
        return class_exists('Doctrine\\DBAL\\Platforms\\PostgreSQLPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\PostgreSQLPlatform'
            : 'Doctrine\\DBAL\\Platforms\\PostgreSqlPlatform';
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private function getSqlitePlatformClass(): string
    {
        // Later DBAL 3 releases use SQLitePlatform; earlier ones use SqlitePlatform
        return class_exists('Doctrine\\DBAL\\Platforms\\SQLitePlatform')
            ? 'Doctrine\\DBAL\\Platforms\\SQLitePlatform'
            : 'Doctrine\\DBAL\\Platforms\\SqlitePlatform';
    }
}
