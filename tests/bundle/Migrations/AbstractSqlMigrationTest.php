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
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\AbortMigration;
use Doctrine\Migrations\Exception\SkipMigration;
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

    public function testPostUpSkipsAMigrationWithoutSqlInADryRun(): void
    {
        $context = new MigrationRunContext();
        $context->setDryRun(true);
        $migration = $this->buildMigration($this->createMock(self::getMysqlPlatformClass()), $context);

        $this->expectException(SkipMigration::class);
        $this->expectExceptionMessage('Nothing to do against the schema from before this dry run');

        $migration->postUp(new Schema());
    }

    #[DataProvider('provideRunsThatKeepTheMigration')]
    public function testPostUpKeepsTheMigration(
        bool $dryRun,
        bool $withSql
    ): void {
        $context = new MigrationRunContext();
        $context->setDryRun($dryRun);
        $migration = $this->buildMigration($this->createMock(self::getMysqlPlatformClass()), $context);
        if ($withSql) {
            $migration->addSqlFilePublic(__DIR__ . '/../Fixtures/sql/statements.sql');
        }

        $migration->postUp(new Schema());

        self::assertCount($withSql ? 2 : 0, $this->getQueuedStatements($migration));
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function provideRunsThatKeepTheMigration(): iterable
    {
        yield 'dry run with SQL' => [true, true];
        yield 'real run without SQL' => [false, false];
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

    /**
     * @param class-string<AbstractPlatform> $platformClass
     * @param array<string, scalar|null> $tableOptions
     */
    #[DataProvider('provideTableOptions')]
    public function testAddSqlFileAppliesConfiguredTableOptions(
        string $platformClass,
        array $tableOptions,
        string $expectedTableOptions
    ): void {
        $migration = $this->buildMigration($this->createMock($platformClass), new MigrationRunContext($tableOptions));

        $migration->addSqlFilePublic(__DIR__ . '/../Fixtures/sql/create-table.sql');

        self::assertSame(
            [
                "CREATE TABLE one (\n    id INT NOT NULL,\n    PRIMARY KEY(id)\n) $expectedTableOptions ENGINE = InnoDB;",
                'ALTER TABLE one ADD name VARCHAR(190) NOT NULL;',
            ],
            $this->getQueuedStatements($migration),
        );
    }

    /**
     * @return iterable<string, array{class-string<AbstractPlatform>, array<string, scalar|null>, string}>
     */
    public static function provideTableOptions(): iterable
    {
        $fileOptions = 'DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_520_ci`';

        yield 'MySQL, charset and collate' => [
            self::getMysqlPlatformClass(),
            ['charset' => 'utf8mb3', 'collate' => 'utf8mb3_general_ci'],
            'DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_general_ci`',
        ];
        yield 'MariaDB, charset and collate' => [
            self::getMariadbPlatformClass(),
            ['charset' => 'utf8mb3', 'collate' => 'utf8mb3_general_ci'],
            'DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_general_ci`',
        ];
        yield 'MySQL, collation preferred over collate' => [
            self::getMysqlPlatformClass(),
            ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_bin', 'collation' => 'utf8mb4_general_ci'],
            'DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci`',
        ];
        yield 'MySQL, collation only' => [
            self::getMysqlPlatformClass(),
            ['collate' => 'utf8mb3_general_ci'],
            'COLLATE `utf8mb3_general_ci`',
        ];
        yield 'MariaDB, collation only without a charset prefix' => [
            self::getMariadbPlatformClass(),
            ['collation' => 'uca1400_ai_ci'],
            'COLLATE `uca1400_ai_ci`',
        ];
        yield 'MySQL, charset only' => [
            self::getMysqlPlatformClass(),
            ['charset' => 'utf8mb3'],
            'DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci`',
        ];
        yield 'MySQL, nothing configured' => [self::getMysqlPlatformClass(), [], $fileOptions];
        yield 'MySQL, empty values' => [self::getMysqlPlatformClass(), ['charset' => '', 'collate' => ''], $fileOptions];
        yield 'PostgreSQL' => [self::getPostgresqlPlatformClass(), ['collate' => 'utf8mb4_general_ci'], $fileOptions];
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

    private function buildMigration(
        AbstractPlatform $platform,
        ?MigrationRunContext $migrationRunContext = null
    ): ConcreteAbstractSqlMigration {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        return new ConcreteAbstractSqlMigration($connection, new NullLogger(), $migrationRunContext ?? new MigrationRunContext());
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
