<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineMigrations\Migration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Migrations\Event\MigrationsVersionEventArgs;
use Doctrine\Migrations\Metadata\ExecutedMigrationsList;
use Doctrine\Migrations\Metadata\MigrationPlan;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Query\Query;
use Doctrine\Migrations\QueryWriter;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\ExecutionResult;
use Doctrine\Migrations\Version\Version;
use Ibexa\DoctrineMigrations\Migration\RecordedMigrations;
use Ibexa\DoctrineMigrations\Migration\RecordedMigrationsQueryWriter;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\ConcreteAbstractSqlMigration;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class RecordedMigrationsQueryWriterTest extends TestCase
{
    private const INSERT_RECORDED = "INSERT INTO doctrine_migration_versions (version, executed_at, execution_time) VALUES ('Recorded', '2026-10-27 12:00:00', 0)";

    /**
     * @dataProvider provideRuns
     *
     * @param list<string> $expectedStatements
     */
    public function testAddsTheVersionRowsOfRecordedMigrations(
        bool $dryRun,
        bool $storageWritesRows,
        array $expectedStatements
    ): void {
        $recordedMigrations = new RecordedMigrations();
        $recordedMigrations->onMigrationsMigrating();
        $this->executeVersion($recordedMigrations, 'Recorded', $dryRun);

        $written = $this->write(
            $recordedMigrations,
            $storageWritesRows ? $this->createStorageWritingRows() : $this->createStorage(),
            ['Recorded' => [new Query('UPDATE t SET a = 1')], 'Skipped' => []]
        );

        self::assertSame(['Recorded' => $expectedStatements, 'Skipped' => []], $written);
    }

    /**
     * @return iterable<string, array{bool, bool, list<string>}>
     */
    public static function provideRuns(): iterable
    {
        $table = ["-- Doctrine Migrations' metadata table, for a database that has none yet", self::createMetadataTableSql()];
        $built = ['UPDATE t SET a = 1', '-- Version Recorded update table metadata', self::INSERT_RECORDED];

        yield 'real run, Doctrine Migrations before 3.6' => [false, false, array_merge($table, $built)];
        yield 'dry run, Doctrine Migrations before 3.6' => [true, false, $built];
        yield 'real run, Doctrine Migrations 3.6 or later' => [false, true, array_merge($table, ['UPDATE t SET a = 1', 'ROW Recorded'])];
        yield 'dry run, Doctrine Migrations 3.6 or later (Doctrine adds them)' => [true, true, ['UPDATE t SET a = 1']];
    }

    private function executeVersion(
        RecordedMigrations $recordedMigrations,
        string $version,
        bool $dryRun
    ): void {
        $plan = new MigrationPlan(
            new Version($version),
            new ConcreteAbstractSqlMigration($this->createStub(Connection::class), new NullLogger()),
            Direction::UP
        );
        $plan->markAsExecuted(new ExecutionResult(new Version($version), Direction::UP, new DateTimeImmutable('2026-10-27 12:00:00')));

        $recordedMigrations->onMigrationsVersionExecuted(new MigrationsVersionEventArgs(
            $this->createStub(Connection::class),
            $plan,
            (new MigratorConfiguration())->setDryRun($dryRun)
        ));
    }

    /**
     * @param array<string, Query[]> $queriesByVersion
     *
     * @return array<string, list<string>> the statements the inner writer got, by version
     */
    private function write(
        RecordedMigrations $recordedMigrations,
        MetadataStorage $metadataStorage,
        array $queriesByVersion
    ): array {
        /** @var array<string, list<string>> $written */
        $written = [];
        $innerWriter = $this->createMock(QueryWriter::class);
        $innerWriter->expects(self::once())
            ->method('write')
            ->willReturnCallback(
                static function (
                    string $path,
                    string $direction,
                    array $queries
                ) use (&$written): bool {
                    foreach ($queries as $version => $versionQueries) {
                        self::assertIsString($version);
                        self::assertIsArray($versionQueries);
                        $written[$version] = [];
                        foreach ($versionQueries as $query) {
                            self::assertInstanceOf(Query::class, $query);
                            $written[$version][] = $query->getStatement();
                        }
                    }

                    return true;
                }
            );

        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager->method(method_exists(AbstractSchemaManager::class, 'introspectTable') ? 'introspectTable' : 'listTableDetails')
            ->willReturn(self::createMetadataTable());
        $connection = $this->createStub(Connection::class);
        $connection->method(method_exists(Connection::class, 'createSchemaManager') ? 'createSchemaManager' : 'getSchemaManager')
            ->willReturn($schemaManager);
        $connection->method('getDatabasePlatform')->willReturn(self::createPlatform());
        $connection->method('quote')->willReturnCallback(
            static function (string $value): string {
                return "'" . str_replace("'", "''", $value) . "'";
            }
        );

        (new RecordedMigrationsQueryWriter(
            $innerWriter,
            $metadataStorage,
            new TableMetadataStorageConfiguration(),
            $connection,
            $recordedMigrations
        ))->write('file.sql', Direction::UP, $queriesByVersion);

        return $written;
    }

    private static function createMetadataTable(): Table
    {
        $table = new Table('doctrine_migration_versions');
        $table->addColumn('version', 'string', ['length' => 191]);
        $table->setPrimaryKey(['version']);

        return $table;
    }

    private static function createMetadataTableSql(): string
    {
        return 'CREATE TABLE IF NOT EXISTS ' . substr(self::createPlatform()->getCreateTableSQL(self::createMetadataTable())[0], strlen('CREATE TABLE '));
    }

    private static function createPlatform(): AbstractPlatform
    {
        // DBAL 2 uses MySqlPlatform; DBAL 3 renamed it to MySQLPlatform
        $class = class_exists('Doctrine\\DBAL\\Platforms\\MySqlPlatform') ? 'Doctrine\\DBAL\\Platforms\\MySqlPlatform' : 'Doctrine\\DBAL\\Platforms\\MySQLPlatform';

        return new $class();
    }

    private function createStorage(): MetadataStorage
    {
        return new class() implements MetadataStorage {
            public function ensureInitialized(): void {}

            public function getExecutedMigrations(): ExecutedMigrationsList
            {
                return new ExecutedMigrationsList([]);
            }

            public function complete(ExecutionResult $migration): void {}

            public function reset(): void {}
        };
    }

    private function createStorageWritingRows(): MetadataStorage
    {
        return new class() implements MetadataStorage {
            public function ensureInitialized(): void {}

            public function getExecutedMigrations(): ExecutedMigrationsList
            {
                return new ExecutedMigrationsList([]);
            }

            public function complete(ExecutionResult $migration): void {}

            public function reset(): void {}

            /**
             * @return iterable<Query>
             */
            public function getSql(ExecutionResult $result): iterable
            {
                yield new Query('ROW ' . $result->getVersion());
            }
        };
    }
}
