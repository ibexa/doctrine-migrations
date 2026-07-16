<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\DoctrineMigrations;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Version\Version;
use Ibexa\Bundle\DoctrineMigrations\Comparator\IbexaMigrationComparator;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyDependencyFactory;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyMigrationsRepository;
use Ibexa\DoctrineMigrations\Migration\ServiceMigrationsRepository;
use Ibexa\Tests\Integration\DoctrineMigrations\Fixtures\IntegrationTestMigration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class IbexaDoctrineMigrationsIntegrationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // FrameworkBundle::boot() registers Symfony's ErrorHandler as an exception handler.
        // Kernel::shutdown() does not restore it, so we do it here to keep PHPUnit happy.
        restore_exception_handler();
    }

    public function testServiceMigrationsRepositoryIsAvailableInContainer(): void
    {
        self::assertInstanceOf(
            ServiceMigrationsRepository::class,
            self::getContainer()->get(ServiceMigrationsRepository::class),
        );
    }

    public function testIbexaOnlyMigrationsRepositoryIsAvailableInContainer(): void
    {
        self::assertInstanceOf(
            ServiceMigrationsRepository::class,
            self::getContainer()->get('test.' . IbexaOnlyMigrationsRepository::SERVICE_ID),
        );
    }

    public function testIbexaOnlyMigrationsRepositoryDiscoversTaggedMigrationWithoutAnInnerRepository(): void
    {
        $repo = self::getContainer()->get('test.' . IbexaOnlyMigrationsRepository::SERVICE_ID);
        self::assertInstanceOf(ServiceMigrationsRepository::class, $repo);

        $items = $repo->getMigrations()->getItems();

        self::assertCount(1, $items);
        self::assertSame(IntegrationTestMigration::class, (string) $items[0]->getVersion());
    }

    public function testIbexaOnlyDependencyFactoryIsAvailableInContainer(): void
    {
        self::assertInstanceOf(
            DependencyFactory::class,
            self::getContainer()->get('test.' . IbexaOnlyDependencyFactory::SERVICE_ID),
        );
    }

    public function testIbexaOnlyDependencyFactoryUsesTheIbexaOnlyRepository(): void
    {
        $dependencyFactory = self::getContainer()->get('test.' . IbexaOnlyDependencyFactory::SERVICE_ID);
        self::assertInstanceOf(DependencyFactory::class, $dependencyFactory);

        self::assertTrue($dependencyFactory->getMigrationRepository()->hasMigration(IntegrationTestMigration::class));
    }

    public function testIbexaOnlyDependencyFactoryIsIndependentFromTheApplicationOne(): void
    {
        $applicationDependencyFactory = self::getContainer()->get('doctrine.migrations.dependency_factory');
        self::assertInstanceOf(DependencyFactory::class, $applicationDependencyFactory);

        $ibexaOnlyDependencyFactory = self::getContainer()->get('test.' . IbexaOnlyDependencyFactory::SERVICE_ID);
        self::assertInstanceOf(DependencyFactory::class, $ibexaOnlyDependencyFactory);

        self::assertNotSame($applicationDependencyFactory, $ibexaOnlyDependencyFactory);
        self::assertNotSame(
            $applicationDependencyFactory->getMigrationRepository(),
            $ibexaOnlyDependencyFactory->getMigrationRepository(),
        );
    }

    public function testIbexaOnlyDependencyFactoryUsesTheIbexaPersistenceConnection(): void
    {
        $applicationDependencyFactory = self::getContainer()->get('doctrine.migrations.dependency_factory');
        self::assertInstanceOf(DependencyFactory::class, $applicationDependencyFactory);

        $ibexaOnlyDependencyFactory = self::getContainer()->get('test.' . IbexaOnlyDependencyFactory::SERVICE_ID);
        self::assertInstanceOf(DependencyFactory::class, $ibexaOnlyDependencyFactory);

        self::assertSame(
            self::getContainer()->get('ibexa.persistence.connection'),
            $ibexaOnlyDependencyFactory->getConnection(),
        );
        self::assertNotSame($applicationDependencyFactory->getConnection(), $ibexaOnlyDependencyFactory->getConnection());
    }

    public function testIbexaMigrationComparatorIsAvailableInContainer(): void
    {
        self::assertInstanceOf(
            IbexaMigrationComparator::class,
            self::getContainer()->get(IbexaMigrationComparator::class),
        );
    }

    public function testTaggedMigrationIsDiscoveredByRepository(): void
    {
        $repo = self::getContainer()->get(ServiceMigrationsRepository::class);

        self::assertInstanceOf(ServiceMigrationsRepository::class, $repo);
        self::assertTrue($repo->hasMigration(IntegrationTestMigration::class));
    }

    public function testGetMigrationsReturnsTaggedMigration(): void
    {
        $repo = self::getContainer()->get(ServiceMigrationsRepository::class);

        self::assertInstanceOf(ServiceMigrationsRepository::class, $repo);

        $items = $repo->getMigrations()->getItems();

        self::assertCount(1, $items);
        self::assertSame(IntegrationTestMigration::class, (string) $items[0]->getVersion());
    }

    public function testGetMigrationReturnsCorrectInstance(): void
    {
        $repo = self::getContainer()->get(ServiceMigrationsRepository::class);

        self::assertInstanceOf(ServiceMigrationsRepository::class, $repo);

        $available = $repo->getMigration(new Version(IntegrationTestMigration::class));

        self::assertInstanceOf(IntegrationTestMigration::class, $available->getMigration());
    }
}
