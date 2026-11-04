<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\DependencyInjection;

use Ibexa\Bundle\DoctrineMigrations\EventListener\TableCollationCheckListener;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class IbexaDoctrineMigrationsExtension extends Extension
{
    public const TABLE_COLLATION_CHECK_IGNORED_TABLES = 'ibexa.doctrine_migrations.table_collation_check.ignored_tables';

    public const TABLE_COLLATION_CHECK_IGNORED_PREFIXES = 'ibexa.doctrine_migrations.table_collation_check.ignored_prefixes';

    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');
        $loader->load('console.php');

        /** @var array{table_collation_check: array{enabled: bool, ignored_tables: list<string>, ignored_prefixes: list<string>}} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);
        $collationCheck = $config['table_collation_check'];
        $container->setParameter(self::TABLE_COLLATION_CHECK_IGNORED_TABLES, $collationCheck['ignored_tables']);
        $container->setParameter(self::TABLE_COLLATION_CHECK_IGNORED_PREFIXES, $collationCheck['ignored_prefixes']);
        if (!$collationCheck['enabled']) {
            $container->removeDefinition(TableCollationCheckListener::class);
        }
    }

    public function getAlias(): string
    {
        return Configuration::ROOT;
    }
}
