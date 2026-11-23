<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\DoctrineMigrations;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorage;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Version\AlphabeticalComparator;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\ExecutionResult;
use Doctrine\Migrations\Version\Version;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyDependencyFactory;
use Ibexa\Tests\Integration\DoctrineMigrations\Fixtures\IntegrationTestMigration;
use Ibexa\Tests\Integration\DoctrineMigrations\Fixtures\NothingToDoMigration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class IbexaMetadataStorageIntegrationTest extends KernelTestCase
{
    private const APPLICATION_VERSION = 'DoctrineMigrations\Version20260101000000';
    private const EXECUTED_AT = '2026-11-05 10:00:00';

    private DependencyFactory $applicationDependencyFactory;

    private DependencyFactory $ibexaDependencyFactory;

    protected function setUp(): void
    {
        self::bootKernel();

        $applicationDependencyFactory = self::getContainer()->get('doctrine.migrations.dependency_factory');
        self::assertInstanceOf(DependencyFactory::class, $applicationDependencyFactory);
        $this->applicationDependencyFactory = $applicationDependencyFactory;

        $ibexaDependencyFactory = self::getContainer()->get('test.' . IbexaOnlyDependencyFactory::SERVICE_ID);
        self::assertInstanceOf(DependencyFactory::class, $ibexaDependencyFactory);
        $this->ibexaDependencyFactory = $ibexaDependencyFactory;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testMovesIbexaVersionsOutOfTheProjectStorage(): void
    {
        $this->recordInProjectStorage(NothingToDoMigration::class);
        $this->recordInProjectStorage(self::APPLICATION_VERSION);
        $this->recordWithApplicationDependencyFactory(self::APPLICATION_VERSION);

        $this->ibexaDependencyFactory->getMetadataStorage()->ensureInitialized();

        self::assertEquals(
            [['version' => NothingToDoMigration::class, 'executed_at' => self::EXECUTED_AT, 'execution_time' => 1500]],
            $this->fetchVersions($this->ibexaDependencyFactory->getConnection(), 'ibexa_doctrine_migration_versions')
        );
        self::assertSame([self::APPLICATION_VERSION], array_column($this->fetchProjectStorageVersions(), 'version'));
        self::assertSame([self::APPLICATION_VERSION], array_column($this->fetchApplicationVersions(), 'version'));
    }

    public function testCountsIbexaVersionsStillInTheProjectStorageAsExecuted(): void
    {
        $this->recordInProjectStorage(NothingToDoMigration::class);
        $this->recordInProjectStorage(self::APPLICATION_VERSION);

        $executedMigrations = $this->ibexaDependencyFactory->getMetadataStorage()->getExecutedMigrations();

        self::assertTrue($executedMigrations->hasMigration(new Version(NothingToDoMigration::class)));
        self::assertFalse($executedMigrations->hasMigration(new Version(self::APPLICATION_VERSION)));
        self::assertSame(
            [self::APPLICATION_VERSION, NothingToDoMigration::class],
            array_column($this->fetchProjectStorageVersions(), 'version')
        );
    }

    public function testLeavesTheProjectStorageAloneOnceIbexaRecordedVersionsOfItsOwn(): void
    {
        $ibexaConfiguration = new TableMetadataStorageConfiguration();
        $ibexaConfiguration->setTableName('ibexa_doctrine_migration_versions');
        $this->record(
            new TableMetadataStorage($this->ibexaDependencyFactory->getConnection(), new AlphabeticalComparator(), $ibexaConfiguration),
            IntegrationTestMigration::class
        );
        $this->recordInProjectStorage(NothingToDoMigration::class);

        $storage = $this->ibexaDependencyFactory->getMetadataStorage();
        $storage->ensureInitialized();

        self::assertFalse($storage->getExecutedMigrations()->hasMigration(new Version(NothingToDoMigration::class)));
        self::assertSame([NothingToDoMigration::class], array_column($this->fetchProjectStorageVersions(), 'version'));
    }

    /**
     * Records a version in the project's table on the Ibexa connection.
     */
    private function recordInProjectStorage(string $version): void
    {
        $this->record(
            new TableMetadataStorage(
                $this->ibexaDependencyFactory->getConnection(),
                new AlphabeticalComparator(),
                $this->applicationDependencyFactory->getConfiguration()->getMetadataStorageConfiguration()
            ),
            $version
        );
    }

    private function recordWithApplicationDependencyFactory(string $version): void
    {
        $this->record($this->applicationDependencyFactory->getMetadataStorage(), $version);
    }

    private function record(
        MetadataStorage $storage,
        string $version
    ): void {
        $storage->ensureInitialized();

        $result = new ExecutionResult(new Version($version), Direction::UP, new DateTimeImmutable(self::EXECUTED_AT));
        $result->setTime(1.5);
        $storage->complete($result);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchProjectStorageVersions(): array
    {
        return $this->fetchVersions($this->ibexaDependencyFactory->getConnection(), 'doctrine_migration_versions');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchApplicationVersions(): array
    {
        return $this->fetchVersions($this->applicationDependencyFactory->getConnection(), 'doctrine_migration_versions');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchVersions(
        Connection $connection,
        string $table
    ): array {
        return $connection->fetchAllAssociative(
            sprintf('SELECT version, executed_at, execution_time FROM %s ORDER BY version', $table)
        );
    }
}
