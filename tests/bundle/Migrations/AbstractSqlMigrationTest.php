<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Migrations\Exception\AbortMigration;
use Ibexa\Contracts\DoctrineMigrations\Migrations\SqlPlatform;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\ConcreteAbstractSqlMigration;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AbstractSqlMigrationTest extends TestCase
{
    /**
     * @dataProvider providePlatformByPlatformClass
     *
     * @param class-string<AbstractPlatform> $platformClass
     */
    public function testPlatformCheckIsTrueOnlyForItsOwnPlatform(
        string $platformClass,
        string $expectedPlatform
    ): void {
        $migration = $this->buildMigration($this->createStub($platformClass));

        self::assertSame($expectedPlatform === SqlPlatform::MYSQL, $migration->isMySQLPublic());
        self::assertSame($expectedPlatform === SqlPlatform::MARIADB, $migration->isMariaDBPublic());
        self::assertSame($expectedPlatform === SqlPlatform::POSTGRESQL, $migration->isPostgreSQLPublic());
        self::assertSame($expectedPlatform === SqlPlatform::SQLITE, $migration->isSqlitePublic());
    }

    /**
     * @return iterable<string, array{class-string<AbstractPlatform>, string}>
     */
    public static function providePlatformByPlatformClass(): iterable
    {
        yield 'MySQL' => [self::getMysqlPlatformClass(), SqlPlatform::MYSQL];
        yield 'MariaDB' => [self::getMariadbPlatformClass(), SqlPlatform::MARIADB];
        yield 'PostgreSQL' => [self::getPostgresqlPlatformClass(), SqlPlatform::POSTGRESQL];
        yield 'SQLite' => [self::getSqlitePlatformClass(), SqlPlatform::SQLITE];
    }

    public function testIsPlatformReturnsFalseForUnsupportedPlatform(): void
    {
        $migration = $this->buildMigration($this->createStub(AbstractPlatform::class));

        self::assertFalse($migration->isPlatformPublic(SqlPlatform::MYSQL));
        self::assertFalse($migration->isPlatformPublic(SqlPlatform::MARIADB));
        self::assertFalse($migration->isPlatformPublic(SqlPlatform::POSTGRESQL));
        self::assertFalse($migration->isPlatformPublic(SqlPlatform::SQLITE));
    }

    /**
     * @dataProvider provideTransactionalByPlatform
     *
     * @param class-string<AbstractPlatform> $platformClass
     */
    public function testIsTransactionalExceptOnMysqlAndMariadb(
        string $platformClass,
        bool $expectedTransactional
    ): void {
        $migration = $this->buildMigration($this->createStub($platformClass));

        self::assertSame($expectedTransactional, $migration->isTransactional());
    }

    /**
     * @return iterable<string, array{class-string<AbstractPlatform>, bool}>
     */
    public static function provideTransactionalByPlatform(): iterable
    {
        yield 'MySQL' => [self::getMysqlPlatformClass(), false];
        yield 'MariaDB' => [self::getMariadbPlatformClass(), false];
        yield 'PostgreSQL' => [self::getPostgresqlPlatformClass(), true];
        yield 'SQLite' => [self::getSqlitePlatformClass(), true];
    }

    public function testAddSqlFileQueuesEachNonEmptyStatement(): void
    {
        $migration = $this->buildMigration($this->createStub(self::getMysqlPlatformClass()));

        $migration->addSqlFilePublic(__DIR__ . '/../Fixtures/sql/statements.sql');

        self::assertSame(
            ['SELECT 1 FROM one;', 'SELECT 2 FROM two;'],
            $this->getQueuedStatements($migration),
        );
    }

    public function testAddSqlFileSupportsCustomDelimiter(): void
    {
        $migration = $this->buildMigration($this->createStub(self::getMysqlPlatformClass()));

        $migration->addSqlFilePublic(__DIR__ . '/../Fixtures/sql/custom-delimiter-statements.sql', '-- @@');

        self::assertSame(
            ['SELECT 1 FROM one;', 'SELECT 2 FROM two;'],
            $this->getQueuedStatements($migration),
        );
    }

    public function testAddSqlFileThrowsWhenFileDoesNotExist(): void
    {
        $migration = $this->buildMigration($this->createStub(self::getMysqlPlatformClass()));

        $this->expectException(\RuntimeException::class);

        $migration->addSqlFilePublic(__DIR__ . '/../Fixtures/sql/does-not-exist.sql');
    }

    /**
     * @dataProvider provideSupportedPlatforms
     *
     * @param class-string<AbstractPlatform> $platformClass
     * @param list<string> $supportedPlatforms
     */
    public function testAbortIfUnsupportedPlatformDoesNotThrowWhenPlatformIsSupported(
        string $platformClass,
        array $supportedPlatforms
    ): void {
        $migration = $this->buildMigration($this->createStub($platformClass));

        $migration->abortIfUnsupportedPlatformPublic(...$supportedPlatforms);

        self::assertSame([], $this->getQueuedStatements($migration));
    }

    /**
     * @return iterable<string, array{class-string<AbstractPlatform>, list<string>}>
     */
    public static function provideSupportedPlatforms(): iterable
    {
        yield 'PostgreSQL among MySQL, PostgreSQL and SQLite' => [
            self::getPostgresqlPlatformClass(),
            [SqlPlatform::MYSQL, SqlPlatform::POSTGRESQL, SqlPlatform::SQLITE],
        ];
        yield 'MariaDB listed next to MySQL' => [
            self::getMariadbPlatformClass(),
            [SqlPlatform::MYSQL, SqlPlatform::MARIADB],
        ];
    }

    /**
     * @dataProvider provideUnsupportedPlatforms
     *
     * @param class-string<AbstractPlatform> $platformClass
     * @param list<string> $supportedPlatforms
     */
    public function testAbortIfUnsupportedPlatformThrowsWhenPlatformIsNotSupported(
        string $platformClass,
        array $supportedPlatforms,
        string $expectedSupportedPlatformList
    ): void {
        $migration = $this->buildMigration($this->createStub($platformClass));

        $this->expectException(AbortMigration::class);
        $this->expectExceptionMessage(sprintf(
            'Unsupported database platform. This migration only supports: %s.',
            $expectedSupportedPlatformList
        ));

        $migration->abortIfUnsupportedPlatformPublic(...$supportedPlatforms);
    }

    /**
     * @return iterable<string, array{class-string<AbstractPlatform>, list<string>, string}>
     */
    public static function provideUnsupportedPlatforms(): iterable
    {
        yield 'PostgreSQL not among MySQL and SQLite' => [
            self::getPostgresqlPlatformClass(),
            [SqlPlatform::MYSQL, SqlPlatform::SQLITE],
            'mysql, sqlite',
        ];
        yield 'MariaDB not treated as MySQL' => [
            self::getMariadbPlatformClass(),
            [SqlPlatform::MYSQL, SqlPlatform::POSTGRESQL, SqlPlatform::SQLITE],
            'mysql, postgresql, sqlite',
        ];
        yield 'platform not recognized at all' => [
            AbstractPlatform::class,
            [SqlPlatform::MYSQL, SqlPlatform::POSTGRESQL, SqlPlatform::SQLITE],
            'mysql, postgresql, sqlite',
        ];
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
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        return new ConcreteAbstractSqlMigration($connection, new NullLogger());
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private static function getMysqlPlatformClass(): string
    {
        // DBAL 2 uses MySqlPlatform; DBAL 3 renamed it to MySQLPlatform
        return class_exists('Doctrine\\DBAL\\Platforms\\MySqlPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\MySqlPlatform'
            : 'Doctrine\\DBAL\\Platforms\\MySQLPlatform';
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private static function getMariadbPlatformClass(): string
    {
        // DBAL >= 3.3 has a MariaDBPlatform base class; earlier releases only MariaDb1027Platform
        foreach (['Doctrine\\DBAL\\Platforms\\MariaDBPlatform', 'Doctrine\\DBAL\\Platforms\\MariaDb1027Platform'] as $class) {
            if (is_subclass_of($class, AbstractPlatform::class)) {
                return $class;
            }
        }

        self::fail('The installed Doctrine DBAL has no MariaDB platform class.');
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private static function getPostgresqlPlatformClass(): string
    {
        // DBAL 2 uses PostgreSqlPlatform; DBAL 3 renamed it to PostgreSQLPlatform
        return class_exists('Doctrine\\DBAL\\Platforms\\PostgreSqlPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\PostgreSqlPlatform'
            : 'Doctrine\\DBAL\\Platforms\\PostgreSQLPlatform';
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private static function getSqlitePlatformClass(): string
    {
        // Earlier DBAL releases use SqlitePlatform; later DBAL 3 releases renamed it to SQLitePlatform
        return class_exists('Doctrine\\DBAL\\Platforms\\SqlitePlatform')
            ? 'Doctrine\\DBAL\\Platforms\\SqlitePlatform'
            : 'Doctrine\\DBAL\\Platforms\\SQLitePlatform';
    }
}
