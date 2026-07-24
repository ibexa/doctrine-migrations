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
use Doctrine\Migrations\Exception\AbortMigration;
use Ibexa\DoctrineMigrations\Migration\SqlPlatform;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\ConcreteAbstractSqlMigration;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AbstractSqlMigrationTest extends TestCase
{
    public function testIsMySQLIsTrueOnlyForMysqlPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock($this->getMysqlPlatformClass()));

        self::assertTrue($migration->isMySQLPublic());
        self::assertFalse($migration->isPostgreSQLPublic());
        self::assertFalse($migration->isSqlitePublic());
    }

    public function testIsPostgreSQLIsTrueOnlyForPostgresqlPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock($this->getPostgresqlPlatformClass()));

        self::assertTrue($migration->isPostgreSQLPublic());
        self::assertFalse($migration->isMySQLPublic());
        self::assertFalse($migration->isSqlitePublic());
    }

    public function testIsSqliteIsTrueOnlyForSqlitePlatform(): void
    {
        $migration = $this->buildMigration($this->createMock($this->getSqlitePlatformClass()));

        self::assertTrue($migration->isSqlitePublic());
        self::assertFalse($migration->isMySQLPublic());
        self::assertFalse($migration->isPostgreSQLPublic());
    }

    public function testIsPlatformReturnsFalseForUnsupportedPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(AbstractPlatform::class));

        self::assertFalse($migration->isPlatformPublic(SqlPlatform::MYSQL));
        self::assertFalse($migration->isPlatformPublic(SqlPlatform::POSTGRESQL));
        self::assertFalse($migration->isPlatformPublic(SqlPlatform::SQLITE));
    }

    public function testAddSqlFileQueuesEachNonEmptyStatement(): void
    {
        $migration = $this->buildMigration($this->createMock($this->getMysqlPlatformClass()));

        $migration->addSqlFilePublic(__DIR__ . '/../Fixtures/sql/statements.sql');

        self::assertSame(
            ['SELECT 1 FROM one;', 'SELECT 2 FROM two;'],
            $this->getQueuedStatements($migration),
        );
    }

    public function testAddSqlFileSupportsCustomDelimiter(): void
    {
        $migration = $this->buildMigration($this->createMock($this->getMysqlPlatformClass()));

        $migration->addSqlFilePublic(__DIR__ . '/../Fixtures/sql/custom-delimiter-statements.sql', '-- @@');

        self::assertSame(
            ['SELECT 1 FROM one;', 'SELECT 2 FROM two;'],
            $this->getQueuedStatements($migration),
        );
    }

    public function testAddSqlFileThrowsWhenFileDoesNotExist(): void
    {
        $migration = $this->buildMigration($this->createMock($this->getMysqlPlatformClass()));

        $this->expectException(\RuntimeException::class);

        $migration->addSqlFilePublic(__DIR__ . '/../Fixtures/sql/does-not-exist.sql');
    }

    public function testAbortIfUnsupportedPlatformDoesNotThrowWhenPlatformIsSupported(): void
    {
        $migration = $this->buildMigration($this->createMock($this->getPostgresqlPlatformClass()));

        $migration->abortIfUnsupportedPlatformPublic(SqlPlatform::MYSQL, SqlPlatform::POSTGRESQL, SqlPlatform::SQLITE);

        self::assertSame([], $this->getQueuedStatements($migration));
    }

    public function testAbortIfUnsupportedPlatformThrowsWhenPlatformIsNotInGivenList(): void
    {
        $migration = $this->buildMigration($this->createMock($this->getPostgresqlPlatformClass()));

        $this->expectException(AbortMigration::class);
        $this->expectExceptionMessage('Unsupported database platform. This migration only supports: mysql, sqlite.');

        $migration->abortIfUnsupportedPlatformPublic(SqlPlatform::MYSQL, SqlPlatform::SQLITE);
    }

    public function testAbortIfUnsupportedPlatformThrowsWhenPlatformIsCompletelyUnsupported(): void
    {
        $migration = $this->buildMigration($this->createMock(AbstractPlatform::class));

        $this->expectException(AbortMigration::class);

        $migration->abortIfUnsupportedPlatformPublic(SqlPlatform::MYSQL, SqlPlatform::POSTGRESQL, SqlPlatform::SQLITE);
    }

    /**
     * @return list<string>
     */
    private function getQueuedStatements(ConcreteAbstractSqlMigration $migration): array
    {
        return array_values(array_map(
            static fn ($query): string => $query->getStatement(),
            $migration->getSql(),
        ));
    }

    private function buildMigration(AbstractPlatform $platform): ConcreteAbstractSqlMigration
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        return new ConcreteAbstractSqlMigration($connection, new NullLogger());
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
