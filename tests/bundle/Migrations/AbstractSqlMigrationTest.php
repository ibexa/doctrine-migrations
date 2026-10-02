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
use Doctrine\Migrations\Exception\AbortMigration;
use Ibexa\Contracts\DoctrineMigrations\Migrations\SqlPlatform;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\ConcreteAbstractSqlMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AbstractSqlMigrationTest extends TestCase
{
    /**
     * @param class-string<AbstractPlatform> $platformClass
     */
    #[DataProvider('providePlatformByPlatformClass')]
    public function testPlatformCheckIsTrueOnlyForItsOwnPlatform(
        string $platformClass,
        SqlPlatform $expectedPlatform
    ): void {
        $migration = $this->buildMigration(self::createStub($platformClass));

        self::assertSame($expectedPlatform === SqlPlatform::MYSQL, $migration->isMySQLPublic());
        self::assertSame($expectedPlatform === SqlPlatform::MARIADB, $migration->isMariaDBPublic());
        self::assertSame($expectedPlatform === SqlPlatform::POSTGRESQL, $migration->isPostgreSQLPublic());
        self::assertSame($expectedPlatform === SqlPlatform::SQLITE, $migration->isSqlitePublic());
    }

    /**
     * @return iterable<string, array{class-string<AbstractPlatform>, SqlPlatform}>
     */
    public static function providePlatformByPlatformClass(): iterable
    {
        yield 'MySQL' => [MySQLPlatform::class, SqlPlatform::MYSQL];
        yield 'MariaDB' => [MariaDBPlatform::class, SqlPlatform::MARIADB];
        yield 'PostgreSQL' => [PostgreSQLPlatform::class, SqlPlatform::POSTGRESQL];
        yield 'SQLite' => [SQLitePlatform::class, SqlPlatform::SQLITE];
    }

    public function testIsPlatformReturnsFalseForUnsupportedPlatform(): void
    {
        $migration = $this->buildMigration(self::createStub(AbstractPlatform::class));

        self::assertFalse($migration->isPlatformPublic(SqlPlatform::MYSQL));
        self::assertFalse($migration->isPlatformPublic(SqlPlatform::MARIADB));
        self::assertFalse($migration->isPlatformPublic(SqlPlatform::POSTGRESQL));
        self::assertFalse($migration->isPlatformPublic(SqlPlatform::SQLITE));
    }

    /**
     * @param class-string<AbstractPlatform> $platformClass
     */
    #[DataProvider('provideTransactionalByPlatform')]
    public function testIsTransactionalExceptOnMysqlAndMariadb(
        string $platformClass,
        bool $expectedTransactional
    ): void {
        $migration = $this->buildMigration(self::createStub($platformClass));

        self::assertSame($expectedTransactional, $migration->isTransactional());
    }

    /**
     * @return iterable<string, array{class-string<AbstractPlatform>, bool}>
     */
    public static function provideTransactionalByPlatform(): iterable
    {
        yield 'MySQL' => [MySQLPlatform::class, false];
        yield 'MariaDB' => [MariaDBPlatform::class, false];
        yield 'PostgreSQL' => [PostgreSQLPlatform::class, true];
        yield 'SQLite' => [SQLitePlatform::class, true];
    }

    public function testAddSqlFileQueuesEachNonEmptyStatement(): void
    {
        $migration = $this->buildMigration(self::createStub(MySQLPlatform::class));

        $migration->addSqlFilePublic(__DIR__ . '/../Fixtures/sql/statements.sql');

        self::assertSame(
            ['SELECT 1 FROM one;', 'SELECT 2 FROM two;'],
            $this->getQueuedStatements($migration),
        );
    }

    public function testAddSqlFileSupportsCustomDelimiter(): void
    {
        $migration = $this->buildMigration(self::createStub(MySQLPlatform::class));

        $migration->addSqlFilePublic(__DIR__ . '/../Fixtures/sql/custom-delimiter-statements.sql', '-- @@');

        self::assertSame(
            ['SELECT 1 FROM one;', 'SELECT 2 FROM two;'],
            $this->getQueuedStatements($migration),
        );
    }

    public function testAddSqlFileThrowsWhenFileDoesNotExist(): void
    {
        $migration = $this->buildMigration(self::createStub(MySQLPlatform::class));

        $this->expectException(\RuntimeException::class);

        $migration->addSqlFilePublic(__DIR__ . '/../Fixtures/sql/does-not-exist.sql');
    }

    public function testAbortIfUnsupportedPlatformDoesNotThrowWhenPlatformIsSupported(): void
    {
        $migration = $this->buildMigration(self::createStub(PostgreSQLPlatform::class));

        $migration->abortIfUnsupportedPlatformPublic(SqlPlatform::MYSQL, SqlPlatform::POSTGRESQL, SqlPlatform::SQLITE);

        self::assertSame([], $this->getQueuedStatements($migration));
    }

    public function testAbortIfUnsupportedPlatformThrowsWhenPlatformIsNotInGivenList(): void
    {
        $migration = $this->buildMigration(self::createStub(PostgreSQLPlatform::class));

        $this->expectException(AbortMigration::class);
        $this->expectExceptionMessage('Unsupported database platform. This migration only supports: mysql, sqlite.');

        $migration->abortIfUnsupportedPlatformPublic(SqlPlatform::MYSQL, SqlPlatform::SQLITE);
    }

    public function testAbortIfUnsupportedPlatformDoesNotTreatMariadbAsMysql(): void
    {
        $migration = $this->buildMigration(self::createStub(MariaDBPlatform::class));

        $this->expectException(AbortMigration::class);
        $this->expectExceptionMessage('Unsupported database platform. This migration only supports: mysql, postgresql, sqlite.');

        $migration->abortIfUnsupportedPlatformPublic(SqlPlatform::MYSQL, SqlPlatform::POSTGRESQL, SqlPlatform::SQLITE);
    }

    public function testAbortIfUnsupportedPlatformDoesNotThrowWhenMariadbIsListed(): void
    {
        $migration = $this->buildMigration(self::createStub(MariaDBPlatform::class));

        $migration->abortIfUnsupportedPlatformPublic(SqlPlatform::MYSQL, SqlPlatform::MARIADB);

        self::assertSame([], $this->getQueuedStatements($migration));
    }

    public function testAbortIfUnsupportedPlatformThrowsWhenPlatformIsCompletelyUnsupported(): void
    {
        $migration = $this->buildMigration(self::createStub(AbstractPlatform::class));

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
        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        return new ConcreteAbstractSqlMigration($connection, new NullLogger());
    }
}
