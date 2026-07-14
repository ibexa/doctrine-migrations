<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Migrations;

use Ibexa\DoctrineMigrations\Migration\Yaml\SqlYamlDefinitionLoader;

/**
 * Allows a Doctrine migration to declare its SQL statements (and their parameters) in a
 * YAML definition file instead of, or in addition to, building them up in PHP.
 *
 * Statements restricted to specific database platforms (see {@see SqlYamlDefinitionLoader})
 * are skipped when they don't apply to the platform currently being migrated.
 *
 * @see SqlYamlDefinitionLoader for the expected YAML structure.
 *
 * @phpstan-require-extends \Doctrine\Migrations\AbstractMigration
 */
trait YamlSqlFileMigrationTrait
{
    /**
     * @param mixed[] $params
     * @param mixed[] $types
     */
    abstract protected function addSql(
        string $sql,
        array $params = [],
        array $types = []
    ): void;

    /**
     * Loads the SQL statements declared in the given YAML file and queues them for
     * execution via {@see addSql()}, once per declared parameter set, skipping any
     * statement that doesn't apply to the current database platform.
     */
    protected function addSqlFromYamlFile(string $yamlFilePath): void
    {
        $loader = new SqlYamlDefinitionLoader();
        $platform = DatabasePlatformResolver::resolve($this->connection);

        foreach ($loader->load($yamlFilePath) as $definition) {
            if (!$definition->appliesToPlatform($platform)) {
                continue;
            }

            foreach ($definition->getParameterSets() as $parameters) {
                $this->addSql($definition->getSql(), $parameters);
            }
        }
    }
}
