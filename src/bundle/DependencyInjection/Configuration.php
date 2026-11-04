<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\DependencyInjection;

use Ibexa\Bundle\DoctrineMigrations\EventListener\TableCollationCheckListener;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const ROOT = 'ibexa_doctrine_migrations';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ROOT);

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('table_collation_check')
                    ->info(sprintf(
                        'Before migrating on MySQL or MariaDB, stops the run when one of Ibexa\'s tables ("ez*", '
                        . '"ibexa_*") uses another collation than new tables would get. See %s.',
                        TableCollationCheckListener::class
                    ))
                    ->canBeDisabled()
                    ->children()
                        ->arrayNode('ignored_tables')
                            ->info('Tables named like Ibexa\'s that Ibexa doesn\'t own; "*" matches any characters.')
                            ->example(['ezcustom_report', 'ibexa_app_*'])
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                        ->arrayNode('ignored_prefixes')
                            ->info('Prefixes of tables named like Ibexa\'s that Ibexa doesn\'t own.')
                            ->example(['ezcustom_', 'ibexa_app_'])
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
