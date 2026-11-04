<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Migrations\Event\MigrationsEventArgs;
use Doctrine\Migrations\Metadata\MigrationPlanList;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Direction;
use Ibexa\Bundle\DoctrineMigrations\EventListener\TableCollationCheckListener;
use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TableCollationCheckListenerTest extends TestCase
{
    /**
     * @dataProvider provideDatabasesMatchingTheNewTables
     *
     * @param array<string, scalar|null> $tableOptions
     * @param array<string, string> $existingTables
     */
    public function testLetsTheRunGoOn(
        array $tableOptions,
        array $existingTables
    ): void {
        $this->runListener($tableOptions, $this->buildMysqlConnection($existingTables));

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{array<string, scalar|null>, array<string, string>}>
     */
    public static function provideDatabasesMatchingTheNewTables(): iterable
    {
        $configured = ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_general_ci'];

        yield 'empty database' => [$configured, []];
        yield 'same collation' => [$configured, ['ezuser' => 'utf8mb4_general_ci', 'ibexa_product' => 'utf8mb4_general_ci']];
        yield 'project tables in another collation' => [
            $configured,
            ['ezuser' => 'utf8mb4_general_ci', 'app_order' => 'latin1_swedish_ci', 'doctrine_migration_versions' => 'utf8mb3_unicode_ci'],
        ];
        yield 'only project tables, before an Ibexa install' => [$configured, ['app_order' => 'latin1_swedish_ci']];
        yield 'nothing configured, the SQL files\' collation' => [[], ['ezuser' => 'utf8mb4_unicode_520_ci']];
        yield 'charset only' => [['charset' => 'utf8mb4'], ['ezuser' => 'utf8mb4_unicode_ci']];
        yield 'MariaDB charset-independent collation' => [['collation' => 'uca1400_ai_ci'], ['ezuser' => 'utf8mb4_uca1400_ai_ci']];
        yield 'utf8 alias' => [['charset' => 'utf8', 'collate' => 'utf8_general_ci'], ['ezuser' => 'utf8mb3_general_ci']];
    }

    /**
     * @dataProvider provideDatabasesNotMatchingTheNewTables
     *
     * @param array<string, scalar|null> $tableOptions
     * @param array<string, string> $existingTables
     */
    public function testStopsTheRunBeforeAnyMigration(
        array $tableOptions,
        array $existingTables,
        string $expectedMessage
    ): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->runListener($tableOptions, $this->buildMysqlConnection($existingTables));
    }

    /**
     * @return iterable<string, array{array<string, scalar|null>, array<string, string>, string}>
     */
    public static function provideDatabasesNotMatchingTheNewTables(): iterable
    {
        $configured = ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_general_ci'];

        yield 'other collation configured' => [
            $configured,
            ['ezuser' => 'utf8mb4_unicode_520_ci'],
            'another collation than the utf8mb4_general_ci the tables these migrations create would get, from "ibexa_doctrine_schema.tables.options" (DATABASE_CHARSET and DATABASE_COLLATION in the recipe): ezuser (utf8mb4_unicode_520_ci).',
        ];
        yield 'a project table matching doesn\'t hide it' => [
            $configured,
            ['app_order' => 'utf8mb4_general_ci', 'ezuser' => 'utf8mb4_unicode_520_ci'],
            ': ezuser (utf8mb4_unicode_520_ci).',
        ];
        yield 'one Ibexa table out of line' => [
            $configured,
            ['ezcontentobject' => 'utf8mb4_general_ci', 'ibexa_product' => 'utf8mb4_unicode_520_ci'],
            ': ibexa_product (utf8mb4_unicode_520_ci).',
        ];
        yield 'nothing configured, tables in the server default' => [
            [],
            ['ibexa_user' => 'utf8mb4_0900_ai_ci'],
            'than the utf8mb4_unicode_520_ci',
        ];
        yield 'long list' => [
            $configured,
            ['eza' => 'latin1_swedish_ci', 'ezb' => 'latin1_swedish_ci', 'ezc' => 'latin1_swedish_ci', 'ezd' => 'latin1_swedish_ci', 'eze' => 'latin1_swedish_ci', 'ezf' => 'latin1_swedish_ci', 'ezg' => 'latin1_swedish_ci'],
            'eze (latin1_swedish_ci) and 2 more.',
        ];
    }

    public function testLeavesOutIgnoredTables(): void
    {
        $tables = ['ezuser' => 'utf8mb4_general_ci', 'ezcustom_report' => 'latin1_swedish_ci', 'ibexa_app_log' => 'latin1_swedish_ci'];

        (new TableCollationCheckListener(
            new MigrationRunContext(['collate' => 'utf8mb4_general_ci']),
            ['ezcustom_report', 'ibexa_app_*']
        ))->onMigrationsMigrating($this->buildEventArgs($this->buildMysqlConnection($tables)));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(': ibexa_app_log (latin1_swedish_ci). ');

        (new TableCollationCheckListener(
            new MigrationRunContext(['collate' => 'utf8mb4_general_ci']),
            ['ezcustom_report']
        ))->onMigrationsMigrating($this->buildEventArgs($this->buildMysqlConnection($tables)));
    }

    public function testLeavesOutTablesWithIgnoredPrefixes(): void
    {
        $tables = ['ezuser' => 'utf8mb4_general_ci', 'ezcustom_report' => 'latin1_swedish_ci', 'ezcustom_log' => 'latin1_swedish_ci'];

        (new TableCollationCheckListener(
            new MigrationRunContext(['collate' => 'utf8mb4_general_ci']),
            [],
            ['ezcustom_']
        ))->onMigrationsMigrating($this->buildEventArgs($this->buildMysqlConnection($tables)));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(': ezcustom_report (latin1_swedish_ci), ezcustom_log (latin1_swedish_ci). ');

        (new TableCollationCheckListener(
            new MigrationRunContext(['collate' => 'utf8mb4_general_ci']),
            [],
            ['ezcustom_x']
        ))->onMigrationsMigrating($this->buildEventArgs($this->buildMysqlConnection($tables)));
    }

    public function testChecksOnlyMysqlAndMariadb(): void
    {
        $connection = $this->createMock(Connection::class);
        $platformClass = self::getPostgresqlPlatformClass();
        $connection->method('getDatabasePlatform')->willReturn(new $platformClass());
        $connection->expects(self::never())->method('fetchAllKeyValue');

        $this->runListener(['collate' => 'utf8mb4_general_ci'], $connection);
    }

    /**
     * @param array<string, string> $existingTables
     */
    private function buildMysqlConnection(array $existingTables): Connection
    {
        $platformClass = self::getMysqlPlatformClass();

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new $platformClass());
        $connection->expects(self::once())
            ->method('fetchAllKeyValue')
            ->with(self::stringContains('information_schema.TABLES'))
            ->willReturn($existingTables);

        return $connection;
    }

    /**
     * @param array<string, scalar|null> $tableOptions
     */
    private function runListener(
        array $tableOptions,
        Connection $connection
    ): void {
        (new TableCollationCheckListener(new MigrationRunContext($tableOptions)))
            ->onMigrationsMigrating($this->buildEventArgs($connection));
    }

    private function buildEventArgs(Connection $connection): MigrationsEventArgs
    {
        return new MigrationsEventArgs($connection, new MigrationPlanList([], Direction::UP), new MigratorConfiguration());
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
    private static function getPostgresqlPlatformClass(): string
    {
        // DBAL 2 uses PostgreSqlPlatform; DBAL 3 renamed it to PostgreSQLPlatform
        return class_exists('Doctrine\\DBAL\\Platforms\\PostgreSqlPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\PostgreSqlPlatform'
            : 'Doctrine\\DBAL\\Platforms\\PostgreSQLPlatform';
    }
}
