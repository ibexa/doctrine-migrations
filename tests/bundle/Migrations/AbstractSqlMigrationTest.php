<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\Exception\AbortMigration;
use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;
use Ibexa\Contracts\DoctrineMigrations\Migrations\SqlPlatform;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\ConcreteAbstractSqlMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AbstractSqlMigrationTest extends TestCase
{
    public function testIsMySQLIsTrueOnlyForMysqlPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(self::getMysqlPlatformClass()));

        self::assertTrue($migration->isMySQLPublic());
        self::assertFalse($migration->isMariaDBPublic());
        self::assertFalse($migration->isPostgreSQLPublic());
        self::assertFalse($migration->isSqlitePublic());
    }

    public function testIsMariaDBIsTrueOnlyForMariadbPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(self::getMariadbPlatformClass()));

        self::assertTrue($migration->isMariaDBPublic());
        self::assertFalse($migration->isMySQLPublic());
        self::assertFalse($migration->isPostgreSQLPublic());
        self::assertFalse($migration->isSqlitePublic());
    }

    public function testIsPostgreSQLIsTrueOnlyForPostgresqlPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(self::getPostgresqlPlatformClass()));

        self::assertTrue($migration->isPostgreSQLPublic());
        self::assertFalse($migration->isMySQLPublic());
        self::assertFalse($migration->isMariaDBPublic());
        self::assertFalse($migration->isSqlitePublic());
    }

    public function testIsSqliteIsTrueOnlyForSqlitePlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(self::getSqlitePlatformClass()));

        self::assertTrue($migration->isSqlitePublic());
        self::assertFalse($migration->isMySQLPublic());
        self::assertFalse($migration->isMariaDBPublic());
        self::assertFalse($migration->isPostgreSQLPublic());
    }

    public function testIsPlatformReturnsFalseForUnsupportedPlatform(): void
    {
        $migration = $this->buildMigration($this->createMock(AbstractPlatform::class));

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
        $migration = $this->buildMigration($this->createMock($platformClass));

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
        $migration = $this->buildMigration($this->createMock(self::getMysqlPlatformClass()));

        $migration->addSqlFilePublic(__DIR__ . '/../Fixtures/sql/statements.sql');

        self::assertSame(
            ['SELECT 1 FROM one;', 'SELECT 2 FROM two;'],
            $this->getQueuedStatements($migration),
        );
    }

    public function testAddSqlFileSupportsCustomDelimiter(): void
    {
        $migration = $this->buildMigration($this->createMock(self::getMysqlPlatformClass()));

        $migration->addSqlFilePublic(__DIR__ . '/../Fixtures/sql/custom-delimiter-statements.sql', '-- @@');

        self::assertSame(
            ['SELECT 1 FROM one;', 'SELECT 2 FROM two;'],
            $this->getQueuedStatements($migration),
        );
    }

    public function testAddSqlFileThrowsWhenFileDoesNotExist(): void
    {
        $migration = $this->buildMigration($this->createMock(self::getMysqlPlatformClass()));

        $this->expectException(\RuntimeException::class);

        $migration->addSqlFilePublic(__DIR__ . '/../Fixtures/sql/does-not-exist.sql');
    }

    public function testAbortIfUnsupportedPlatformDoesNotThrowWhenPlatformIsSupported(): void
    {
        $migration = $this->buildMigration($this->createMock(self::getPostgresqlPlatformClass()));

        $migration->abortIfUnsupportedPlatformPublic(SqlPlatform::MYSQL, SqlPlatform::POSTGRESQL, SqlPlatform::SQLITE);

        self::assertSame([], $this->getQueuedStatements($migration));
    }

    public function testAbortIfUnsupportedPlatformThrowsWhenPlatformIsNotInGivenList(): void
    {
        $migration = $this->buildMigration($this->createMock(self::getPostgresqlPlatformClass()));

        $this->expectException(AbortMigration::class);
        $this->expectExceptionMessage('Unsupported database platform. This migration only supports: mysql, sqlite.');

        $migration->abortIfUnsupportedPlatformPublic(SqlPlatform::MYSQL, SqlPlatform::SQLITE);
    }

    public function testAbortIfUnsupportedPlatformDoesNotTreatMariadbAsMysql(): void
    {
        $migration = $this->buildMigration($this->createMock(self::getMariadbPlatformClass()));

        $this->expectException(AbortMigration::class);
        $this->expectExceptionMessage('Unsupported database platform. This migration only supports: mysql, postgresql, sqlite.');

        $migration->abortIfUnsupportedPlatformPublic(SqlPlatform::MYSQL, SqlPlatform::POSTGRESQL, SqlPlatform::SQLITE);
    }

    public function testAbortIfUnsupportedPlatformDoesNotThrowWhenMariadbIsListed(): void
    {
        $migration = $this->buildMigration($this->createMock(self::getMariadbPlatformClass()));

        $migration->abortIfUnsupportedPlatformPublic(SqlPlatform::MYSQL, SqlPlatform::MARIADB);

        self::assertSame([], $this->getQueuedStatements($migration));
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

    /**
     * @param list<mixed> $params
     * @param list<mixed> $types
     */
    #[DataProvider('provideStatementsWithParameters')]
    public function testWritesParametersAsLiteralsInADryRun(
        AbstractPlatform $platform,
        string $sql,
        array $params,
        array $types,
        string $expectedSql
    ): void {
        $migration = $this->buildMigrationWithContext($platform, true, false);

        $migration->addSqlPublic($sql, $params, $types);

        $query = $migration->getSql()[0];
        self::assertSame($expectedSql, $query->getStatement());
        self::assertSame([], $query->getParameters());
    }

    /**
     * @return iterable<string, array{AbstractPlatform, string, list<mixed>, list<mixed>, string}>
     */
    public static function provideStatementsWithParameters(): iterable
    {
        yield 'string and integer' => [
            new MySQLPlatform(),
            'UPDATE ibexa_taxonomy_entry SET names = ? WHERE id = ?',
            ['{"eng-GB":"O\'Brien"}', 5],
            [ParameterType::STRING, ParameterType::INTEGER],
            'UPDATE ibexa_taxonomy_entry SET names = \'{"eng-GB":"O\'\'Brien"}\' WHERE id = 5',
        ];
        yield 'no types given' => [
            new PostgreSQLPlatform(),
            'UPDATE t SET a = ?',
            ['x'],
            [],
            'UPDATE t SET a = \'x\'',
        ];
        yield 'question mark inside a string literal' => [
            new PostgreSQLPlatform(),
            'UPDATE t SET a = ? WHERE b = \'?\'',
            ['x'],
            [ParameterType::STRING],
            'UPDATE t SET a = \'x\' WHERE b = \'?\'',
        ];
        yield 'null' => [
            new MySQLPlatform(),
            'UPDATE t SET a = ? WHERE id = ?',
            [null, 1],
            [ParameterType::STRING, ParameterType::INTEGER],
            'UPDATE t SET a = NULL WHERE id = 1',
        ];
        yield 'Doctrine type' => [
            new PostgreSQLPlatform(),
            'UPDATE t SET a = ?',
            [['eng-GB' => 'Name']],
            [Types::JSON],
            'UPDATE t SET a = \'{"eng-GB":"Name"}\'',
        ];
    }

    public function testWritesParametersAsLiteralsWhenARealRunWritesItsSql(): void
    {
        $migration = $this->buildMigrationWithContext(new MySQLPlatform(), false, true);

        $migration->addSqlPublic('UPDATE t SET a = ? WHERE id = ?', ['x', 5], [ParameterType::STRING, ParameterType::INTEGER]);

        $query = $migration->getSql()[0];
        self::assertSame('UPDATE t SET a = \'x\' WHERE id = 5', $query->getStatement());
        self::assertSame([], $query->getParameters());
    }

    public function testWritesNamedParametersAsLiterals(): void
    {
        $migration = $this->buildMigrationWithContext(new MySQLPlatform(), true, false);

        $migration->addSqlPublic('UPDATE t SET a = :value WHERE id = :id', ['id' => 3, 'value' => 'x'], ['id' => ParameterType::INTEGER]);

        self::assertSame('UPDATE t SET a = \'x\' WHERE id = 3', $migration->getSql()[0]->getStatement());
    }

    public function testKeepsParametersBoundWhenTheRunDoesNotWriteItsSql(): void
    {
        $migration = $this->buildMigrationWithContext(new MySQLPlatform(), false, false);

        $migration->addSqlPublic('UPDATE t SET a = ? WHERE id = ?', ['x', 5], [ParameterType::STRING, ParameterType::INTEGER]);

        $query = $migration->getSql()[0];
        self::assertSame('UPDATE t SET a = ? WHERE id = ?', $query->getStatement());
        self::assertSame(['x', 5], $query->getParameters());
    }

    public function testKeepsParametersBoundWithoutALiteralForm(): void
    {
        $migration = $this->buildMigrationWithContext(new MySQLPlatform(), true, false);

        $migration->addSqlPublic('UPDATE t SET a = ? WHERE id = ?', ['binary', 5], [ParameterType::LARGE_OBJECT, ParameterType::INTEGER]);

        $query = $migration->getSql()[0];
        self::assertSame('UPDATE t SET a = ? WHERE id = ?', $query->getStatement());
        self::assertSame(['binary', 5], $query->getParameters());
    }

    private function buildMigrationWithContext(
        AbstractPlatform $platform,
        bool $dryRun,
        bool $writesSql
    ): ConcreteAbstractSqlMigration {
        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('quote')->willReturnCallback(
            static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'"
        );

        $context = new MigrationRunContext();
        $context->setDryRun($dryRun);
        $context->setWritesSql($writesSql);

        return new ConcreteAbstractSqlMigration($connection, new NullLogger(), $context);
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
    private static function getMysqlPlatformClass(): string
    {
        return MySQLPlatform::class;
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private static function getMariadbPlatformClass(): string
    {
        return MariaDBPlatform::class;
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private static function getPostgresqlPlatformClass(): string
    {
        return PostgreSQLPlatform::class;
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private static function getSqlitePlatformClass(): string
    {
        return SqlitePlatform::class;
    }
}
