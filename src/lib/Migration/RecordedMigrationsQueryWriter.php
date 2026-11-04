<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineMigrations\Migration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Query\Query;
use Doctrine\Migrations\QueryWriter;
use Doctrine\Migrations\Version\ExecutionResult;

/**
 * Adds the version rows of the migrations a run recorded to its --write-sql file, and to a real run's file the metadata
 * table itself, so the file brings another copy of the database to the same state on its own. Doctrine Migrations writes them into a dry run's file only, and only from
 * 3.6 on (TableMetadataStorage::getSql()); this branch supports 3.1 and later, so the rows are built here when the
 * metadata storage can't.
 */
final class RecordedMigrationsQueryWriter implements QueryWriter
{
    private QueryWriter $innerWriter;

    private MetadataStorage $metadataStorage;

    private TableMetadataStorageConfiguration $storageConfiguration;

    private Connection $connection;

    private RecordedMigrations $recordedMigrations;

    public function __construct(
        QueryWriter $innerWriter,
        MetadataStorage $metadataStorage,
        TableMetadataStorageConfiguration $storageConfiguration,
        Connection $connection,
        RecordedMigrations $recordedMigrations
    ) {
        $this->innerWriter = $innerWriter;
        $this->metadataStorage = $metadataStorage;
        $this->storageConfiguration = $storageConfiguration;
        $this->connection = $connection;
        $this->recordedMigrations = $recordedMigrations;
    }

    /**
     * @param array<string, Query[]> $queriesByVersion
     */
    public function write(
        string $path,
        string $direction,
        array $queriesByVersion
    ): bool {
        $storageWritesRows = method_exists($this->metadataStorage, 'getSql');
        // Doctrine adds them to a dry run's file itself where it can.
        if (!$this->recordedMigrations->isDryRun() || !$storageWritesRows) {
            foreach ($queriesByVersion as $version => $queries) {
                $result = $this->recordedMigrations->get($version);
                if ($result === null) {
                    continue;
                }

                // getSql() isn't part of the MetadataStorage interface, and doesn't exist before Doctrine Migrations 3.6
                $rows = $storageWritesRows ? $this->metadataStorage->getSql($result) : $this->buildRows($result);
                assert(is_iterable($rows));
                foreach ($rows as $query) {
                    assert($query instanceof Query);
                    $queriesByVersion[$version][] = $query;
                }
            }
        }

        $firstVersion = array_key_first($queriesByVersion);
        if ($firstVersion !== null && !$this->recordedMigrations->isDryRun() && $this->recordedMigrations->hasAny()) {
            $queriesByVersion[$firstVersion] = array_merge($this->createMetadataTable(), $queriesByVersion[$firstVersion]);
        }

        return $this->innerWriter->write($path, $direction, $queriesByVersion);
    }

    /**
     * Doctrine creates its metadata table before the run, outside the migrations, so a database that never ran a
     * migrations command (a legacy install) needs it from the file too. Taken from the table the run used, so it is
     * the same; elsewhere it exists already. A dry run creates it on the database it runs against.
     *
     * @return list<Query>
     */
    private function createMetadataTable(): array
    {
        // DBAL 2.13 has getSchemaManager() and listTableDetails() only
        $schemaManager = method_exists($this->connection, 'createSchemaManager')
            ? $this->connection->createSchemaManager()
            : $this->connection->getSchemaManager();
        assert($schemaManager instanceof AbstractSchemaManager);
        $tableName = $this->storageConfiguration->getTableName();
        $table = method_exists($schemaManager, 'introspectTable')
            ? $schemaManager->introspectTable($tableName)
            : $schemaManager->listTableDetails($tableName);
        assert($table instanceof Table);

        $queries = [new Query('-- Doctrine Migrations\' metadata table, for a database that has none yet')];
        foreach ($this->connection->getDatabasePlatform()->getCreateTableSQL($table) as $sql) {
            $queries[] = new Query(preg_replace('/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', $sql) ?? $sql);
        }

        return $queries;
    }

    /**
     * The same rows TableMetadataStorage::getSql() writes in later Doctrine Migrations.
     *
     * @return list<Query>
     */
    private function buildRows(ExecutionResult $result): array
    {
        $version = $this->connection->quote((string)$result->getVersion());
        $executedAt = $this->connection->quote(($result->getExecutedAt() ?? new DateTimeImmutable())->format('Y-m-d H:i:s'));
        assert(is_string($version) && is_string($executedAt));

        return [
            new Query('-- Version ' . $result->getVersion() . ' update table metadata'),
            new Query(sprintf(
                'INSERT INTO %s (%s, %s, %s) VALUES (%s, %s, 0)',
                $this->storageConfiguration->getTableName(),
                $this->storageConfiguration->getVersionColumnName(),
                $this->storageConfiguration->getExecutedAtColumnName(),
                $this->storageConfiguration->getExecutionTimeColumnName(),
                $version,
                $executedAt
            )),
        ];
    }
}
