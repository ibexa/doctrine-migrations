<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineMigrations\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
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
    public function testAddsTheVersionRowsOfMigrationsARealRunRecorded(): void
    {
        $recordedMigrations = new RecordedMigrations();
        $recordedMigrations->onMigrationsMigrating();
        $this->executeVersion($recordedMigrations, 'Recorded', false);

        $written = $this->write($recordedMigrations, ['Recorded' => [new Query('UPDATE t SET a = 1')], 'Skipped' => []]);

        self::assertSame(
            ['Recorded' => [
                "-- Doctrine Migrations' metadata table, for a database that has none yet",
                'CREATE TABLE IF NOT EXISTS doctrine_migration_versions (version VARCHAR(191) NOT NULL, PRIMARY KEY(version)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB',
                'UPDATE t SET a = 1',
                'INSERT Recorded',
            ], 'Skipped' => []],
            $written
        );
    }

    public function testAddsNothingToADryRunsFile(): void
    {
        $recordedMigrations = new RecordedMigrations();
        $recordedMigrations->onMigrationsMigrating();
        $this->executeVersion($recordedMigrations, 'Recorded', true);

        $written = $this->write($recordedMigrations, ['Recorded' => [new Query('UPDATE t SET a = 1')]]);

        self::assertSame(['Recorded' => ['UPDATE t SET a = 1']], $written);
    }

    private function executeVersion(
        RecordedMigrations $recordedMigrations,
        string $version,
        bool $dryRun
    ): void {
        $plan = new MigrationPlan(
            new Version($version),
            new ConcreteAbstractSqlMigration(self::createStub(Connection::class), new NullLogger()),
            Direction::UP
        );
        $plan->markAsExecuted(new ExecutionResult(new Version($version)));

        $recordedMigrations->onMigrationsVersionExecuted(new MigrationsVersionEventArgs(
            self::createStub(Connection::class),
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
        array $queriesByVersion
    ): array {
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

        $table = new Table('doctrine_migration_versions');
        $table->addColumn('version', 'string', ['length' => 191]);
        $table->setPrimaryKey(['version']);
        $schemaManager = self::createStub(AbstractSchemaManager::class);
        $schemaManager->method('introspectTable')->willReturn($table);
        $connection = self::createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());

        (new RecordedMigrationsQueryWriter(
            $innerWriter,
            new class() implements MetadataStorage {
                public function ensureInitialized(): void {}

                public function getExecutedMigrations(): ExecutedMigrationsList
                {
                    return new ExecutedMigrationsList([]);
                }

                public function complete(ExecutionResult $result): void {}

                public function reset(): void {}

                /**
                 * @return iterable<Query>
                 */
                public function getSql(ExecutionResult $result): iterable
                {
                    yield new Query('INSERT ' . $result->getVersion());
                }
            },
            new TableMetadataStorageConfiguration(),
            $connection,
            $recordedMigrations
        ))->write('file.sql', Direction::UP, $queriesByVersion);

        return $written;
    }
}
