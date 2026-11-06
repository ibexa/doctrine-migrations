<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineMigrations\Metadata;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\Migrations\Metadata\ExecutedMigration;
use Doctrine\Migrations\Metadata\ExecutedMigrationsList;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\MigrationsRepository;
use Doctrine\Migrations\Version\Comparator;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\ExecutionResult;
use Doctrine\Migrations\Version\Version;

/**
 * The Ibexa-only DependencyFactory's metadata storage: the storage it decorates keeps Ibexa's executed
 * versions in a table of their own, and this one takes over the versions that earlier releases recorded
 * in the application's table instead.
 *
 * While Ibexa's table is empty, every row of the application's table whose version is a registered
 * Ibexa migration counts as executed, and {@see ensureInitialized()} moves it into Ibexa's table, with
 * its execution date and time, so none of those migrations runs again. Before that, e.g. for
 * "ibexa:doctrine:migrations:status", which never initializes the storage, the rows are only read.
 *
 * The application's table is read with a plain query rather than through the schema manager, so no
 * schema assets filter can hide it. It may sit on another connection than Ibexa's, so a row is
 * deleted from it only after it's been inserted into Ibexa's.
 */
final class IbexaMetadataStorage implements MetadataStorage
{
    private MetadataStorage $storage;

    private MigrationsRepository $migrationRepository;

    private Comparator $comparator;

    private Connection $applicationConnection;

    private TableMetadataStorageConfiguration $applicationConfiguration;

    /** @var array<string, ExecutedMigration>|null Ibexa's versions still in the application's table, once looked up */
    private ?array $applicationVersions = null;

    public function __construct(
        MetadataStorage $storage,
        MigrationsRepository $migrationRepository,
        Comparator $comparator,
        Connection $applicationConnection,
        TableMetadataStorageConfiguration $applicationConfiguration
    ) {
        $this->storage = $storage;
        $this->migrationRepository = $migrationRepository;
        $this->comparator = $comparator;
        $this->applicationConnection = $applicationConnection;
        $this->applicationConfiguration = $applicationConfiguration;
    }

    public function ensureInitialized(): void
    {
        $this->storage->ensureInitialized();

        $applicationVersions = $this->getApplicationVersions();
        foreach ($applicationVersions as $migration) {
            $result = new ExecutionResult($migration->getVersion(), Direction::UP, $migration->getExecutedAt());
            $executionTime = $migration->getExecutionTime();
            if ($executionTime !== null) {
                $result->setTime($executionTime);
            }

            $this->storage->complete($result);
        }

        foreach (array_keys($applicationVersions) as $version) {
            $this->applicationConnection->delete($this->applicationConfiguration->getTableName(), [
                $this->applicationConfiguration->getVersionColumnName() => $version,
            ]);
        }

        $this->applicationVersions = [];
    }

    public function getExecutedMigrations(): ExecutedMigrationsList
    {
        $applicationVersions = $this->getApplicationVersions();
        if ($applicationVersions === []) {
            return $this->storage->getExecutedMigrations();
        }

        return new ExecutedMigrationsList($applicationVersions);
    }

    public function complete(ExecutionResult $result): void
    {
        $this->storage->complete($result);
    }

    public function reset(): void
    {
        $this->storage->reset();
    }

    /**
     * @return array<string, ExecutedMigration>
     */
    private function getApplicationVersions(): array
    {
        if ($this->applicationVersions === null) {
            $this->applicationVersions = count($this->storage->getExecutedMigrations()) === 0
                ? $this->readApplicationVersions()
                : [];
        }

        return $this->applicationVersions;
    }

    /**
     * Reads the application's table the way Doctrine's TableMetadataStorage does.
     *
     * @return array<string, ExecutedMigration>
     */
    private function readApplicationVersions(): array
    {
        $configuration = $this->applicationConfiguration;
        try {
            $rows = $this->applicationConnection->fetchAllAssociative(
                sprintf('SELECT * FROM %s', $configuration->getTableName())
            );
        } catch (TableNotFoundException $e) {
            return [];
        }

        $dateTimeFormat = $this->applicationConnection->getDatabasePlatform()->getDateTimeFormatString();
        $versions = [];
        foreach ($rows as $row) {
            $row = array_change_key_case($row, CASE_LOWER);

            $version = $row[strtolower($configuration->getVersionColumnName())] ?? null;
            if (!is_string($version) || !$this->migrationRepository->hasMigration($version)) {
                continue;
            }

            $executedAt = $row[strtolower($configuration->getExecutedAtColumnName())] ?? null;
            $executedAt = is_string($executedAt) && $executedAt !== ''
                ? DateTimeImmutable::createFromFormat($dateTimeFormat, $executedAt)
                : null;

            $executionTime = $row[strtolower($configuration->getExecutionTimeColumnName())] ?? null;

            $versions[$version] = new ExecutedMigration(
                new Version($version),
                $executedAt instanceof DateTimeImmutable ? $executedAt : null,
                is_numeric($executionTime) ? (float) $executionTime / 1000 : null
            );
        }

        uasort(
            $versions,
            fn (ExecutedMigration $a, ExecutedMigration $b): int => $this->comparator->compare($a->getVersion(), $b->getVersion())
        );

        return $versions;
    }
}
