<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Ibexa\Bundle\DoctrineMigrations\Migrations\DatabasePlatformResolver;
use Ibexa\DoctrineMigrations\Migration\SqlPlatform;
use PHPUnit\Framework\TestCase;

final class DatabasePlatformResolverTest extends TestCase
{
    public function testResolveReturnsMysqlIdentifier(): void
    {
        self::assertSame(SqlPlatform::MYSQL, DatabasePlatformResolver::resolve($this->buildConnection($this->getMysqlPlatformClass())));
    }

    public function testResolveReturnsPostgresqlIdentifier(): void
    {
        self::assertSame(SqlPlatform::POSTGRESQL, DatabasePlatformResolver::resolve($this->buildConnection($this->getPostgresqlPlatformClass())));
    }

    public function testResolveReturnsSqliteIdentifier(): void
    {
        self::assertSame(SqlPlatform::SQLITE, DatabasePlatformResolver::resolve($this->buildConnection($this->getSqlitePlatformClass())));
    }

    public function testResolveReturnsNullForUnsupportedPlatform(): void
    {
        self::assertNull(DatabasePlatformResolver::resolve($this->buildConnection(AbstractPlatform::class)));
    }

    /**
     * @param class-string<AbstractPlatform> $platformClass
     */
    private function buildConnection(string $platformClass): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($this->createMock($platformClass));

        return $connection;
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
