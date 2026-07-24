<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
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
        return MySQLPlatform::class;
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private function getPostgresqlPlatformClass(): string
    {
        return PostgreSQLPlatform::class;
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private function getSqlitePlatformClass(): string
    {
        return SqlitePlatform::class;
    }
}
