<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\DependencyInjection\Compiler;

use Doctrine\DBAL\Connection;
use Ibexa\Bundle\DoctrineMigrations\Configuration\IbexaMigrationConfigurationFactory;
use Ibexa\Bundle\RepositoryInstaller\Migration\TaggedMigrationsRunner;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationTag;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyDependencyFactory;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyMigrationsRepository;
use Ibexa\DoctrineMigrations\Migration\ServiceMigrationsRepository;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;

/**
 * Collects all services tagged with {@see IbexaMigrationTag::TAG} and wires them into the
 * {@see IbexaOnlyMigrationsRepository::SERVICE_ID} service via a lazy service locator — a
 * {@see ServiceMigrationsRepository} instance that never
 * decorates any inner repository, so it serves Ibexa's own migrations only.
 *
 * For each tagged migration service the pass also binds the Doctrine DBAL {@see Connection} and
 * a {@see LoggerInterface} so that migration constructors can receive them through Symfony's
 * auto-wiring or explicit binding without any extra configuration. Both are sourced from the
 * application's own "doctrine.migrations.dependency_factory" (registered unconditionally by
 * DoctrineMigrationsBundle) via two Ibexa-owned services, "ibexa.doctrine_migrations.connection"
 * and "ibexa.doctrine_migrations.logger" — never via DoctrineMigrationsBundle's own
 * "doctrine.migrations.connection"/"doctrine.migrations.logger" aliases, since those are removed
 * from the container entirely when "enable_service_migrations" is false, and "doctrine.*" service
 * IDs belong to that bundle, not to Ibexa, to redefine.
 *
 * This pass never touches the application's own {@code doctrine.migrations.dependency_factory}
 * or its {@code MigrationsRepository}/{@code Comparator} — Ibexa's migrations are visible
 * exclusively through {@see IbexaOnlyMigrationsRepository::SERVICE_ID} and
 * {@see IbexaOnlyDependencyFactory::SERVICE_ID} (used by the "ibexa:doctrine:migrations:*"
 * commands and {@see TaggedMigrationsRunner}), never
 * by the application's own "doctrine:migrations:*" commands.
 *
 * Finally, the {@see IbexaOnlyDependencyFactory::SERVICE_ID} service — an independent copy of the
 * application's DependencyFactory that always runs against the "ibexa.persistence.connection"
 * service (assumed to exist; it's created by ibexa/core) and uses the Ibexa-only repository above
 * — has its Configuration built from the application's own by
 * {@see IbexaMigrationConfigurationFactory} (a
 * clone carrying an Ibexa-specific migration template, never the exact same instance) and its
 * logger fetched directly from the application's own "doctrine.migrations.dependency_factory".
 */
final class RegisterMigrationsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(IbexaOnlyMigrationsRepository::SERVICE_ID)) {
            return;
        }

        if (!$container->hasDefinition('doctrine.migrations.dependency_factory')) {
            throw new LogicException(sprintf(
                'Service "%s" is missing. DoctrineMigrationsBundle must be registered to use Ibexa doctrine-migrations.',
                'doctrine.migrations.dependency_factory'
            ));
        }

        $container->register('ibexa.doctrine_migrations.connection', Connection::class)
            ->setPublic(false)
            ->setFactory([new Reference('doctrine.migrations.dependency_factory'), 'getConnection']);

        $container->register('ibexa.doctrine_migrations.logger', LoggerInterface::class)
            ->setPublic(false)
            ->setFactory([new Reference('doctrine.migrations.dependency_factory'), 'getLogger']);

        $migrationRefs = [];

        foreach ($container->findTaggedServiceIds(IbexaMigrationTag::TAG, true) as $id => $attributes) {
            $definition = $container->getDefinition($id);
            $definition->setBindings([
                Connection::class => new BoundArgument(new Reference('ibexa.doctrine_migrations.connection'), false),
                LoggerInterface::class => new BoundArgument(new Reference('ibexa.doctrine_migrations.logger'), false),
            ] + $definition->getBindings());

            $migrationRefs[$id] = new TypedReference($id, (string) $definition->getClass());
        }

        $container->getDefinition(IbexaOnlyMigrationsRepository::SERVICE_ID)
            ->replaceArgument(0, new ServiceLocatorArgument($migrationRefs));

        // Independent copy of the application's DependencyFactory, always using the Ibexa-only
        // repository (its "ibexa.persistence.connection" argument is wired directly in
        // services.php). Its Configuration is built (via IbexaMigrationConfigurationFactory) and
        // its logger fetched from the application's DependencyFactory via two inline definitions
        // in services.php, each with an abstract_arg placeholder standing in for it.
        if ($container->hasDefinition(IbexaOnlyDependencyFactory::SERVICE_ID)) {
            $ibexaOnlyDependencyFactoryDefinition = $container->getDefinition(IbexaOnlyDependencyFactory::SERVICE_ID);

            $existingConfigurationDefinition = $ibexaOnlyDependencyFactoryDefinition->getArgument(0);
            if ($existingConfigurationDefinition instanceof Definition) {
                $configurationDefinition = $existingConfigurationDefinition->getArgument(0);
                if ($configurationDefinition instanceof Definition) {
                    $configurationDefinition->replaceArgument(0, new Reference('doctrine.migrations.dependency_factory'));
                }
            }

            $loggerDefinition = $ibexaOnlyDependencyFactoryDefinition->getArgument(2);
            if ($loggerDefinition instanceof Definition) {
                $loggerDefinition->setFactory([new Reference('doctrine.migrations.dependency_factory'), 'getLogger']);
            }
        }
    }
}
