<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Migrations;

use Doctrine\Migrations\Exception\IrreversibleMigration;
use Ibexa\DoctrineMigrations\Migration\Yaml\SqlYamlDefinition;
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
     * Loads the SQL statements declared in the "up" section of the given YAML file and
     * queues them for execution via {@see addSql()}, once per declared parameter set,
     * skipping any statement that doesn't apply to the current database platform.
     */
    protected function addUpSqlFromYamlFile(string $yamlFilePath): void
    {
        $this->addSqlFromYamlDefinitions((new SqlYamlDefinitionLoader())->load($yamlFilePath)->getUp());
    }

    /**
     * Loads the SQL statements declared in the "down" section of the given YAML file and
     * queues them for execution via {@see addSql()}, once per declared parameter set,
     * skipping any statement that doesn't apply to the current database platform.
     *
     * @throws IrreversibleMigration if the YAML file declares no "down" statements
     */
    protected function addDownSqlFromYamlFile(string $yamlFilePath): void
    {
        $down = (new SqlYamlDefinitionLoader())->load($yamlFilePath)->getDown();

        if ($down === []) {
            $this->throwIrreversibleMigrationException(sprintf('YAML file "%s" declares no "down" statements.', $yamlFilePath));
        }

        $this->addSqlFromYamlDefinitions($down);
    }

    /**
     * @param list<SqlYamlDefinition> $definitions
     */
    private function addSqlFromYamlDefinitions(array $definitions): void
    {
        $platform = DatabasePlatformResolver::resolve($this->connection);

        foreach ($definitions as $definition) {
            if (!$definition->appliesToPlatform($platform)) {
                continue;
            }

            foreach ($definition->getParameterSets() as $parameters) {
                $this->addSql($definition->getSql(), $parameters);
            }
        }
    }
}
