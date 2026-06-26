<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineMigrations\Migration;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\MigrationClassNotFound;
use Doctrine\Migrations\Metadata\AvailableMigration;
use Doctrine\Migrations\Metadata\AvailableMigrationsSet;
use Doctrine\Migrations\MigrationsRepository;
use Doctrine\Migrations\Version\Version;
use Ibexa\DoctrineMigrations\Migration\ServiceMigrationsRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\ServiceProviderInterface;

final class ServiceMigrationsRepositoryTest extends TestCase
{
    private const string MIGRATION_ID = 'App\\Migrations\\Version20260101';

    private const string OTHER_MIGRATION_ID = 'App\\Migrations\\Version20260201';

    // ── hasMigration ──────────────────────────────────────────────────────────

    public function testHasMigrationReturnsTrueForContainerMigration(): void
    {
        $container = $this->buildContainer([self::MIGRATION_ID => true]);

        $repo = new ServiceMigrationsRepository($container);

        self::assertTrue($repo->hasMigration(self::MIGRATION_ID));
    }

    public function testHasMigrationReturnsTrueForInnerRepositoryMigration(): void
    {
        $container = $this->buildContainer([]);

        $inner = $this->createMock(MigrationsRepository::class);
        $inner->method('hasMigration')->with(self::MIGRATION_ID)->willReturn(true);

        $repo = new ServiceMigrationsRepository($container, $inner);

        self::assertTrue($repo->hasMigration(self::MIGRATION_ID));
    }

    public function testHasMigrationReturnsFalseWhenNotFound(): void
    {
        $container = $this->buildContainer([]);

        $inner = $this->createMock(MigrationsRepository::class);
        $inner->method('hasMigration')->willReturn(false);

        $repo = new ServiceMigrationsRepository($container, $inner);

        self::assertFalse($repo->hasMigration(self::MIGRATION_ID));
    }

    public function testHasMigrationReturnsFalseWithoutInner(): void
    {
        $container = $this->buildContainer([]);

        $repo = new ServiceMigrationsRepository($container);

        self::assertFalse($repo->hasMigration(self::MIGRATION_ID));
    }

    // ── getMigration ──────────────────────────────────────────────────────────

    public function testGetMigrationFromContainer(): void
    {
        $migration = $this->createMock(AbstractMigration::class);
        $version = new Version(self::MIGRATION_ID);

        $container = $this->buildContainer([self::MIGRATION_ID => true], [self::MIGRATION_ID => $migration]);

        $repo = new ServiceMigrationsRepository($container);
        $result = $repo->getMigration($version);

        self::assertSame($version, $result->getVersion());
        self::assertSame($migration, $result->getMigration());
    }

    public function testGetMigrationFallsBackToInner(): void
    {
        $migration = $this->createMock(AbstractMigration::class);
        $version = new Version(self::MIGRATION_ID);

        $container = $this->buildContainer([]);

        $available = new AvailableMigration($version, $migration);
        $inner = $this->createMock(MigrationsRepository::class);
        $inner->method('getMigration')->with($version)->willReturn($available);

        $repo = new ServiceMigrationsRepository($container, $inner);

        self::assertSame($available, $repo->getMigration($version));
    }

    public function testGetMigrationThrowsWhenNotFoundWithoutInner(): void
    {
        $container = $this->buildContainer([]);

        $repo = new ServiceMigrationsRepository($container);

        $this->expectException(MigrationClassNotFound::class);
        $repo->getMigration(new Version(self::MIGRATION_ID));
    }

    public function testGetMigrationThrowsWhenNotFoundInEither(): void
    {
        $container = $this->buildContainer([]);

        $inner = $this->createMock(MigrationsRepository::class);
        $inner->method('hasMigration')->willReturn(false);
        $inner->method('getMigration')->willThrowException(MigrationClassNotFound::new(self::MIGRATION_ID));

        $repo = new ServiceMigrationsRepository($container, $inner);

        $this->expectException(MigrationClassNotFound::class);
        $repo->getMigration(new Version(self::MIGRATION_ID));
    }

    public function testGetMigrationCachesResultFromContainer(): void
    {
        $migration = $this->createMock(AbstractMigration::class);
        $version = new Version(self::MIGRATION_ID);

        $container = $this->createMock(ServiceProviderInterface::class);
        $container->method('has')->with(self::MIGRATION_ID)->willReturn(true);
        // get() must only be called once even if getMigration() is called twice
        $container->expects(self::once())
            ->method('get')
            ->with(self::MIGRATION_ID)
            ->willReturn($migration);

        $repo = new ServiceMigrationsRepository($container);
        $repo->getMigration($version);
        $repo->getMigration($version);
    }

    // ── getMigrations ─────────────────────────────────────────────────────────

    public function testGetMigrationsFromContainerOnly(): void
    {
        $migration = $this->createMock(AbstractMigration::class);

        $container = $this->buildContainer(
            [self::MIGRATION_ID => true],
            [self::MIGRATION_ID => $migration],
            [self::MIGRATION_ID => AbstractMigration::class],
        );

        $repo = new ServiceMigrationsRepository($container);
        $items = $repo->getMigrations()->getItems();

        self::assertCount(1, $items);
        $found = $this->findByVersionId($items, self::MIGRATION_ID);
        self::assertNotNull($found);
        self::assertSame($migration, $found->getMigration());
    }

    public function testGetMigrationsFromInnerOnlyWhenContainerIsEmpty(): void
    {
        $migration = $this->createMock(AbstractMigration::class);
        $version = new Version(self::MIGRATION_ID);

        $container = $this->buildContainer([], [], []);

        $available = new AvailableMigration($version, $migration);
        $inner = $this->createMock(MigrationsRepository::class);
        $inner->method('getMigrations')
            ->willReturn(new AvailableMigrationsSet([self::MIGRATION_ID => $available]));

        $repo = new ServiceMigrationsRepository($container, $inner);
        $items = $repo->getMigrations()->getItems();

        self::assertCount(1, $items);
        $found = $this->findByVersionId($items, self::MIGRATION_ID);
        self::assertNotNull($found);
        self::assertSame($migration, $found->getMigration());
    }

    public function testGetMigrationsMergesContainerAndInner(): void
    {
        $containerMigration = $this->createMock(AbstractMigration::class);
        $innerMigration = $this->createMock(AbstractMigration::class);

        $container = $this->buildContainer(
            [self::MIGRATION_ID => true],
            [self::MIGRATION_ID => $containerMigration],
            [self::MIGRATION_ID => AbstractMigration::class],
        );

        $innerAvailable = new AvailableMigration(new Version(self::OTHER_MIGRATION_ID), $innerMigration);
        $inner = $this->createMock(MigrationsRepository::class);
        $inner->method('getMigrations')
            ->willReturn(new AvailableMigrationsSet([self::OTHER_MIGRATION_ID => $innerAvailable]));

        $repo = new ServiceMigrationsRepository($container, $inner);
        $items = $repo->getMigrations()->getItems();

        self::assertCount(2, $items);
        self::assertNotNull($this->findByVersionId($items, self::MIGRATION_ID), 'Expected MIGRATION_ID in merged set');
        self::assertNotNull($this->findByVersionId($items, self::OTHER_MIGRATION_ID), 'Expected OTHER_MIGRATION_ID in merged set');
    }

    public function testGetMigrationsContainerOverridesInnerForSameVersion(): void
    {
        $containerMigration = $this->createMock(AbstractMigration::class);
        $innerMigration = $this->createMock(AbstractMigration::class);

        $container = $this->buildContainer(
            [self::MIGRATION_ID => true],
            [self::MIGRATION_ID => $containerMigration],
            [self::MIGRATION_ID => AbstractMigration::class],
        );

        $innerAvailable = new AvailableMigration(new Version(self::MIGRATION_ID), $innerMigration);
        $inner = $this->createMock(MigrationsRepository::class);
        $inner->method('getMigrations')
            ->willReturn(new AvailableMigrationsSet([self::MIGRATION_ID => $innerAvailable]));

        $repo = new ServiceMigrationsRepository($container, $inner);
        $items = $repo->getMigrations()->getItems();

        self::assertCount(1, $items);
        $found = $this->findByVersionId($items, self::MIGRATION_ID);
        self::assertNotNull($found);
        self::assertSame($containerMigration, $found->getMigration());
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * @param array<AvailableMigration> $items
     */
    private function findByVersionId(
        array $items,
        string $versionId
    ): ?AvailableMigration {
        foreach ($items as $item) {
            if ((string) $item->getVersion() === $versionId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array<string, bool> $hasMap
     * @param array<string, AbstractMigration> $getMap
     * @param array<string, string> $providedServices
     *
     * @return ServiceProviderInterface<AbstractMigration>
     */
    private function buildContainer(
        array $hasMap,
        array $getMap = [],
        array $providedServices = [],
    ): ServiceProviderInterface {
        $container = $this->createMock(ServiceProviderInterface::class);

        $container->method('has')->willReturnCallback(
            static fn (string $id): bool => $hasMap[$id] ?? false,
        );
        $container->method('get')->willReturnCallback(
            static fn (string $id) => $getMap[$id] ?? null,
        );
        $container->method('getProvidedServices')->willReturn($providedServices);

        return $container;
    }
}
