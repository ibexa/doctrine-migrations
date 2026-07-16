<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\DependencyInjection\Compiler;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigrationsRepository;
use Doctrine\Migrations\Version\Comparator;
use Ibexa\Bundle\DoctrineMigrations\Comparator\IbexaMigrationComparator;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationTag;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyDependencyFactory;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyMigrationsRepository;
use Ibexa\DoctrineMigrations\Migration\ServiceMigrationsRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;

/**
 * Collects all services tagged with {@see IbexaMigrationTag::TAG} and wires them into the
 * {@see ServiceMigrationsRepository} via a lazy service locator.
 *
 * For each tagged migration service the pass also binds the Doctrine DBAL
 * {@see Connection} and a {@see LoggerInterface} so that migration constructors
 * can receive them through Symfony's auto-wiring or explicit binding without
 * any extra configuration.
 *
 * Our {@see ServiceMigrationsRepository} is registered with the Doctrine Migrations
 * {@see DependencyFactory} as the active {@see MigrationsRepository}
 * via {@see DependencyFactory::setDefinition()} using a
 * {@see ServiceClosureArgument}, which takes precedence over the Doctrine bundle's own
 * {@see DependencyFactory::setDefinition()} call. To avoid discarding
 * project migrations, when the Doctrine bundle's
 * {@code doctrine.migrations.service_migrations_repository} is present it is injected as
 * the inner (decorated) repository so that all project migrations remain available alongside
 * Ibexa-internal ones.
 *
 * The same tagged migrations are also wired into the {@see IbexaOnlyMigrationsRepository::SERVICE_ID}
 * service — a second, separate {@see ServiceMigrationsRepository} instance that never decorates an
 * inner repository, for callers that explicitly need only Ibexa-internal migrations.
 *
 * Finally, the {@see IbexaOnlyDependencyFactory::SERVICE_ID} service — an independent copy of this
 * DependencyFactory that always runs against the "ibexa.persistence.connection" service (assumed
 * to exist; it's created by ibexa/core) and uses that Ibexa-only repository — is wired to this
 * same "doctrine.migrations.dependency_factory" service as its source of Configuration/logger.
 */
final class RegisterMigrationsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (
            !$container->hasDefinition(ServiceMigrationsRepository::class)
            || !$container->hasDefinition('doctrine.migrations.dependency_factory')
        ) {
            return;
        }

        $repositoryDefinition = $container->getDefinition(ServiceMigrationsRepository::class);

        $migrationRefs = [];

        foreach ($container->findTaggedServiceIds(IbexaMigrationTag::TAG, true) as $id => $attributes) {
            $definition = $container->getDefinition($id);
            $definition->setBindings([
                Connection::class => new BoundArgument(new Reference('doctrine.migrations.connection'), false),
                LoggerInterface::class => new BoundArgument(new Reference('doctrine.migrations.logger'), false),
            ] + $definition->getBindings());

            $migrationRefs[$id] = new TypedReference($id, (string) $definition->getClass());
        }

        $repositoryDefinition->replaceArgument(0, new ServiceLocatorArgument($migrationRefs));

        // Same tagged migrations, exposed without decorating any inner repository.
        if ($container->hasDefinition(IbexaOnlyMigrationsRepository::SERVICE_ID)) {
            $container->getDefinition(IbexaOnlyMigrationsRepository::SERVICE_ID)
                ->replaceArgument(0, new ServiceLocatorArgument($migrationRefs));
        }

        // Decorate the Doctrine bundle's service-migrations repository when present,
        // so that project migrations are preserved alongside Ibexa-internal ones.
        // Arg 1 must always be replaced (originally an abstract_arg placeholder).
        if ($container->hasDefinition('doctrine.migrations.service_migrations_repository')) {
            $repositoryDefinition->replaceArgument(1, new Reference('doctrine.migrations.service_migrations_repository'));
        } else {
            $repositoryDefinition->replaceArgument(1, null);
        }

        // Independent copy of this DependencyFactory, always using the Ibexa-only repository
        // (its "ibexa.persistence.connection" argument is wired directly in services.php). Its
        // Configuration and logger are fetched from this DependencyFactory via two inline
        // definitions in services.php, each with an abstract_arg placeholder standing in for it.
        if ($container->hasDefinition(IbexaOnlyDependencyFactory::SERVICE_ID)) {
            $ibexaOnlyDependencyFactoryDefinition = $container->getDefinition(IbexaOnlyDependencyFactory::SERVICE_ID);

            $existingConfigurationDefinition = $ibexaOnlyDependencyFactoryDefinition->getArgument(0);
            if ($existingConfigurationDefinition instanceof Definition) {
                $configurationDefinition = $existingConfigurationDefinition->getArgument(0);
                if ($configurationDefinition instanceof Definition) {
                    $configurationDefinition->setFactory([new Reference('doctrine.migrations.dependency_factory'), 'getConfiguration']);
                }
            }

            $loggerDefinition = $ibexaOnlyDependencyFactoryDefinition->getArgument(2);
            if ($loggerDefinition instanceof Definition) {
                $loggerDefinition->setFactory([new Reference('doctrine.migrations.dependency_factory'), 'getLogger']);
            }
        }

        $dependencyFactory = $container->getDefinition('doctrine.migrations.dependency_factory');

        $dependencyFactory->addMethodCall(
            'setDefinition',
            [MigrationsRepository::class, new ServiceClosureArgument(new Reference(ServiceMigrationsRepository::class))],
        );

        if ($container->hasDefinition(IbexaMigrationComparator::class)) {
            $dependencyFactory->addMethodCall(
                'setDefinition',
                [Comparator::class, new ServiceClosureArgument(new Reference(IbexaMigrationComparator::class))],
            );
        }
    }
}
