<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineMigrations\Migration\Yaml;

/**
 * Represents a single SQL statement loaded from a YAML SQL migration definition file,
 * together with the list of parameter sets it should be executed with and the database
 * platforms (see {@see SqlYamlPlatform}) it applies to.
 */
final class SqlYamlDefinition
{
    private string $sql;

    /** @var list<array<int|string, mixed>> */
    private array $parameterSets;

    /** @var list<string> */
    private array $platforms;

    /**
     * @param list<array<int|string, mixed>> $parameterSets
     * @param list<string> $platforms an empty list means the statement applies to all platforms
     */
    public function __construct(
        string $sql,
        array $parameterSets,
        array $platforms = []
    ) {
        $this->sql = $sql;
        $this->parameterSets = $parameterSets;
        $this->platforms = $platforms;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return list<array<int|string, mixed>>
     */
    public function getParameterSets(): array
    {
        return $this->parameterSets;
    }

    /**
     * @return list<string>
     */
    public function getPlatforms(): array
    {
        return $this->platforms;
    }

    /**
     * Determines whether this statement should be executed on the given database platform.
     *
     * A statement with no declared platforms applies to all of them. A statement declaring
     * platforms only applies if $platform is one of them; in particular, it never applies
     * when $platform is null (i.e. the current platform could not be determined).
     */
    public function appliesToPlatform(?string $platform): bool
    {
        if ($this->platforms === []) {
            return true;
        }

        return $platform !== null && in_array($platform, $this->platforms, true);
    }
}
