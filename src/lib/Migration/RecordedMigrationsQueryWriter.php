<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineMigrations\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Query\Query;
use Doctrine\Migrations\QueryWriter;

/**
 * Doctrine writes the version rows into the --write-sql file of a dry run only; a real run records them in the
 * database alone. This adds them to a real run's file too, for every migration the run recorded, together with the
 * metadata table itself, so the file brings another copy of the database to the same state on its own.
 */
final class RecordedMigrationsQueryWriter implements QueryWriter
{
    public function __construct(
        private readonly QueryWriter $innerWriter,
        private readonly MetadataStorage $metadataStorage,
        private readonly TableMetadataStorageConfiguration $storageConfiguration,
        private readonly Connection $connection,
        private readonly RecordedMigrations $recordedMigrations
    ) {}

    /**
     * @param array<string, Query[]> $queriesByVersion
     */
    public function write(
        string $path,
        string $direction,
        array $queriesByVersion
    ): bool {
        if (method_exists($this->metadataStorage, 'getSql')) {
            foreach ($queriesByVersion as $version => $queries) {
                $result = $this->recordedMigrations->get($version);
                if ($result === null) {
                    continue;
                }

                foreach ($this->metadataStorage->getSql($result) as $query) {
                    $queriesByVersion[$version][] = $query;
                }
            }
        }

        $firstVersion = array_key_first($queriesByVersion);
        if ($firstVersion !== null && $this->recordedMigrations->hasAny()) {
            $queriesByVersion[$firstVersion] = [...$this->createMetadataTable(), ...$queriesByVersion[$firstVersion]];
        }

        return $this->innerWriter->write($path, $direction, $queriesByVersion);
    }

    /**
     * Doctrine creates its metadata table before the run, outside the migrations, so a database that never ran a
     * migrations command (a legacy install) needs it from the file too. Taken from the table the run used, so it is
     * the same; elsewhere it exists already.
     *
     * @return list<Query>
     */
    private function createMetadataTable(): array
    {
        $table = $this->connection->createSchemaManager()->introspectTable($this->storageConfiguration->getTableName());
        $queries = [new Query('-- Doctrine Migrations\' metadata table, for a database that has none yet')];
        foreach ($this->connection->getDatabasePlatform()->getCreateTableSQL($table) as $sql) {
            $queries[] = new Query(preg_replace('/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', $sql) ?? $sql);
        }

        return $queries;
    }
}
