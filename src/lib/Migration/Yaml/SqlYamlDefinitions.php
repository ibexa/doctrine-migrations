<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineMigrations\Migration\Yaml;

/**
 * The `up` and `down` {@see SqlYamlDefinition} lists loaded from a YAML SQL migration
 * definition file (see {@see SqlYamlDefinitionLoader}).
 */
final class SqlYamlDefinitions
{
    /** @var list<SqlYamlDefinition> */
    private array $up;

    /** @var list<SqlYamlDefinition> */
    private array $down;

    /**
     * @param list<SqlYamlDefinition> $up
     * @param list<SqlYamlDefinition> $down
     */
    public function __construct(
        array $up,
        array $down
    ) {
        $this->up = $up;
        $this->down = $down;
    }

    /**
     * @return list<SqlYamlDefinition>
     */
    public function getUp(): array
    {
        return $this->up;
    }

    /**
     * @return list<SqlYamlDefinition>
     */
    public function getDown(): array
    {
        return $this->down;
    }
}
