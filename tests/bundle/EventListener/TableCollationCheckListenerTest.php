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
     * @param list<string> $existingCollations
     */
    public function testLetsTheRunGoOn(
        array $tableOptions,
        array $existingCollations
    ): void {
        $this->runListener($tableOptions, $this->buildMysqlConnection($existingCollations));

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{array<string, scalar|null>, list<string>}>
     */
    public static function provideDatabasesMatchingTheNewTables(): iterable
    {
        $configured = ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_general_ci'];

        yield 'empty database' => [$configured, []];
        yield 'same collation' => [$configured, ['utf8mb4_general_ci']];
        yield 'other tables in other collations too' => [$configured, ['latin1_swedish_ci', 'utf8mb4_general_ci']];
        yield 'nothing configured, the SQL files\' collation' => [[], ['utf8mb4_unicode_520_ci']];
        yield 'charset only' => [['charset' => 'utf8mb4'], ['utf8mb4_unicode_ci']];
        yield 'MariaDB charset-independent collation' => [['collation' => 'uca1400_ai_ci'], ['utf8mb4_uca1400_ai_ci']];
        yield 'utf8 alias' => [['charset' => 'utf8', 'collate' => 'utf8_general_ci'], ['utf8mb3_general_ci']];
    }

    /**
     * @dataProvider provideDatabasesNotMatchingTheNewTables
     *
     * @param array<string, scalar|null> $tableOptions
     * @param list<string> $existingCollations
     */
    public function testStopsTheRunBeforeAnyMigration(
        array $tableOptions,
        array $existingCollations,
        string $expectedMessage
    ): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->runListener($tableOptions, $this->buildMysqlConnection($existingCollations));
    }

    /**
     * @return iterable<string, array{array<string, scalar|null>, list<string>, string}>
     */
    public static function provideDatabasesNotMatchingTheNewTables(): iterable
    {
        yield 'other collation configured' => [
            ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_general_ci'],
            ['utf8mb4_unicode_520_ci'],
            'The tables in this database use utf8mb4_unicode_520_ci, but the tables these migrations create would get utf8mb4_general_ci',
        ];
        yield 'nothing configured, tables in the server default' => [
            [],
            ['utf8mb4_0900_ai_ci'],
            'use utf8mb4_0900_ai_ci, but the tables these migrations create would get utf8mb4_unicode_520_ci',
        ];
    }

    public function testChecksOnlyMysqlAndMariadb(): void
    {
        $connection = $this->createMock(Connection::class);
        $platformClass = self::getPostgresqlPlatformClass();
        $connection->method('getDatabasePlatform')->willReturn(new $platformClass());
        $connection->expects(self::never())->method('fetchFirstColumn');

        $this->runListener(['collate' => 'utf8mb4_general_ci'], $connection);
    }

    /**
     * @param list<string> $existingCollations
     */
    private function buildMysqlConnection(array $existingCollations): Connection
    {
        $platformClass = self::getMysqlPlatformClass();

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new $platformClass());
        $connection->expects(self::once())
            ->method('fetchFirstColumn')
            ->with(self::stringContains('information_schema.TABLES'), ['doctrine_migration_versions'])
            ->willReturn($existingCollations);

        return $connection;
    }

    /**
     * @param array<string, scalar|null> $tableOptions
     */
    private function runListener(
        array $tableOptions,
        Connection $connection
    ): void {
        (new TableCollationCheckListener(new MigrationRunContext($tableOptions), 'doctrine_migration_versions'))
            ->onMigrationsMigrating(new MigrationsEventArgs(
                $connection,
                new MigrationPlanList([], Direction::UP),
                new MigratorConfiguration()
            ));
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
