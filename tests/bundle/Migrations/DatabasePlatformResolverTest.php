<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Ibexa\Bundle\DoctrineMigrations\Migrations\DatabasePlatformResolver;
use Ibexa\DoctrineMigrations\Migration\SqlPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatabasePlatformResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<AbstractPlatform>}>
     */
    public static function provideMysqlPlatformClasses(): iterable
    {
        yield 'MySQL' => [MySQLPlatform::class];
        yield 'MariaDB' => [MariaDBPlatform::class];
    }

    /**
     * @param class-string<AbstractPlatform> $platformClass
     */
    #[DataProvider('provideMysqlPlatformClasses')]
    public function testResolveReturnsMysqlIdentifier(string $platformClass): void
    {
        self::assertSame(SqlPlatform::MYSQL, DatabasePlatformResolver::resolve($this->buildConnection($platformClass)));
    }

    public function testResolveReturnsPostgresqlIdentifier(): void
    {
        self::assertSame(SqlPlatform::POSTGRESQL, DatabasePlatformResolver::resolve($this->buildConnection(PostgreSQLPlatform::class)));
    }

    public function testResolveReturnsSqliteIdentifier(): void
    {
        self::assertSame(SqlPlatform::SQLITE, DatabasePlatformResolver::resolve($this->buildConnection(SQLitePlatform::class)));
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
        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(self::createStub($platformClass));

        return $connection;
    }
}
