<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineMigrations\Migration;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\MigrationClassNotFound;
use Doctrine\Migrations\Metadata\AvailableMigration;
use Doctrine\Migrations\Metadata\AvailableMigrationsSet;
use Doctrine\Migrations\MigrationsRepository;
use Doctrine\Migrations\Version\Version;
use Ibexa\Bundle\DoctrineMigrations\DependencyInjection\Compiler\RegisterMigrationsPass;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationTag;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * A {@see MigrationsRepository} implementation that provides migrations registered as Symfony services,
 * while decorating an existing inner {@see MigrationsRepository} so that Ibexa-internal migrations
 * are added on top of any migrations already declared by the project.
 *
 * Migrations in the service locator take precedence over inner repository migrations of the same version.
 * All three lookup methods ({@see hasMigration}, {@see getMigration}, {@see getMigrations}) transparently
 * combine both sources.
 *
 * The service locator is populated at compile-time by
 * {@see RegisterMigrationsPass}
 * from all services tagged with
 * {@see IbexaMigrationTag::TAG}.
 *
 * The inner repository defaults to {@see null}, in which case only tagged migrations are served.
 */
final class ServiceMigrationsRepository implements MigrationsRepository
{
    /** @var ServiceProviderInterface<AbstractMigration> */
    private ServiceProviderInterface $container;

    private ?MigrationsRepository $inner;

    /** @var array<string, AvailableMigration> */
    private array $migrations = [];

    /** @param ServiceProviderInterface<AbstractMigration> $container */
    public function __construct(
        ServiceProviderInterface $container,
        ?MigrationsRepository $inner = null
    ) {
        $this->container = $container;
        $this->inner = $inner;
    }

    public function hasMigration(string $version): bool
    {
        return isset($this->migrations[$version])
            || $this->container->has($version)
            || ($this->inner !== null && $this->inner->hasMigration($version));
    }

    public function getMigration(Version $version): AvailableMigration
    {
        $id = (string) $version;

        // Our tagged migrations take priority
        if (isset($this->migrations[$id]) || $this->container->has($id)) {
            $this->loadMigrationFromContainer($version);

            return $this->migrations[$id];
        }

        if ($this->inner !== null) {
            return $this->inner->getMigration($version);
        }

        throw MigrationClassNotFound::new($id);
    }

    /**
     * Returns a non-sorted set of migrations combining inner and tagged migrations.
     * Tagged migrations override inner migrations with the same version.
     */
    public function getMigrations(): AvailableMigrationsSet
    {
        // Start with the inner repository's migrations (may be empty if inner is null)
        $merged = [];
        if ($this->inner !== null) {
            foreach ($this->inner->getMigrations()->getItems() as $migration) {
                $merged[(string) $migration->getVersion()] = $migration;
            }
        }

        // Load all our tagged migrations into the cache
        foreach (array_keys($this->container->getProvidedServices()) as $id) {
            $this->loadMigrationFromContainer(new Version($id));
        }

        // Our migrations override inner ones of the same version
        foreach ($this->migrations as $id => $migration) {
            $merged[$id] = $migration;
        }

        return new AvailableMigrationsSet($merged);
    }

    private function loadMigrationFromContainer(Version $version): void
    {
        $id = (string) $version;

        if (isset($this->migrations[$id])) {
            return;
        }

        if (!$this->container->has($id)) {
            throw MigrationClassNotFound::new($id);
        }

        $service = $this->container->get($id);
        if (!$service instanceof AbstractMigration) {
            throw new \LogicException(sprintf('Service "%s" must be an instance of %s.', $id, AbstractMigration::class));
        }

        $this->migrations[$id] = new AvailableMigration($version, $service);
    }
}
