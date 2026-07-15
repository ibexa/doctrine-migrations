<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineMigrations\Migration\Yaml;

use Symfony\Component\Yaml\Yaml;

/**
 * Loads {@see SqlYamlDefinitions} from a YAML file.
 *
 * The YAML file must contain a mapping with an `up` and/or a `down` key, each a list of
 * entries (omitting either means it declares no statements for that direction). Each entry
 * declares exactly one of:
 *  - `file`: the path to a SQL file, resolved relative to the directory of the YAML file;
 *  - `sql`: an inline SQL statement, declared directly in the YAML file.
 *
 * Each entry may also declare:
 *  - `parameters` (optional): either a single parameter set (e.g. a map of placeholder
 *    names to values, or a list of positional values), executed once, or a list of
 *    parameter sets, in which case the SQL is executed once per set;
 *  - `platforms` (optional): a {@see SqlYamlPlatform} identifier, or a list thereof, the
 *    statement applies to. Omitting it means the statement applies to all platforms;
 *    it is up to consumers of {@see SqlYamlDefinition::appliesToPlatform()} to skip
 *    statements that don't apply to the platform currently being migrated.
 *
 * Example:
 *
 * ```yaml
 * up:
 *     - file: 'sql/create_indexes.sql'
 *     - sql: 'DELETE FROM setting WHERE name = :name'
 *       parameters:
 *           name: 'obsolete_setting'
 *     - file: 'sql/mysql/insert_setting.sql'
 *       platforms: 'mysql'
 *       parameters:
 *           name: 'my_setting'
 *           value: '42'
 *     - file: 'sql/insert_multiple_settings.sql'
 *       platforms: ['mysql', 'postgresql']
 *       parameters:
 *           - { name: 'setting_a', value: '1' }
 *           - { name: 'setting_b', value: '2' }
 * down:
 *     - file: 'sql/drop_indexes.sql'
 *     - sql: 'DELETE FROM setting WHERE name = :name'
 *       parameters:
 *           name: 'my_setting'
 * ```
 */
final class SqlYamlDefinitionLoader
{
    private const SECTION_UP = 'up';

    private const SECTION_DOWN = 'down';

    public function load(string $yamlFilePath): SqlYamlDefinitions
    {
        if (!is_file($yamlFilePath)) {
            throw new \RuntimeException(sprintf('YAML SQL migration definition file "%s" does not exist.', $yamlFilePath));
        }

        $document = Yaml::parseFile($yamlFilePath);
        if ($document === null) {
            $document = [];
        }

        if (!is_array($document) || ($document !== [] && array_is_list($document))) {
            throw new \RuntimeException(sprintf('YAML SQL migration definition file "%s" must contain a mapping with an "up" and/or a "down" key.', $yamlFilePath));
        }

        $baseDirectory = dirname($yamlFilePath);

        return new SqlYamlDefinitions(
            $this->parseSection($document[self::SECTION_UP] ?? [], self::SECTION_UP, $yamlFilePath, $baseDirectory),
            $this->parseSection($document[self::SECTION_DOWN] ?? [], self::SECTION_DOWN, $yamlFilePath, $baseDirectory),
        );
    }

    /**
     * @param mixed $entries
     *
     * @return list<SqlYamlDefinition>
     */
    private function parseSection(
        $entries,
        string $section,
        string $yamlFilePath,
        string $baseDirectory
    ): array {
        if (!is_array($entries)) {
            throw new \RuntimeException(sprintf('"%s" in "%s" must be a list of entries.', $section, $yamlFilePath));
        }

        $definitions = [];
        foreach ($entries as $index => $entry) {
            $definitions[] = $this->parseEntry($entry, sprintf('%s[%s]', $section, $index), $yamlFilePath, $baseDirectory);
        }

        return $definitions;
    }

    /**
     * @param mixed $entry
     */
    private function parseEntry(
        $entry,
        string $entryLabel,
        string $yamlFilePath,
        string $baseDirectory
    ): SqlYamlDefinition {
        if (!is_array($entry)) {
            throw new \RuntimeException(sprintf('Entry "%s" in "%s" must be a mapping.', $entryLabel, $yamlFilePath));
        }

        $file = $entry['file'] ?? null;
        $inlineSql = $entry['sql'] ?? null;

        $hasFile = is_string($file) && $file !== '';
        $hasSql = is_string($inlineSql) && $inlineSql !== '';

        if ($hasFile === $hasSql) {
            throw new \RuntimeException(sprintf('Entry "%s" in "%s" must declare exactly one of a non-empty "file" or "sql" string.', $entryLabel, $yamlFilePath));
        }

        if (is_string($file) && $hasFile) {
            $sql = $this->readSqlFile($file, $entryLabel, $yamlFilePath, $baseDirectory);
        } elseif (is_string($inlineSql) && $hasSql) {
            $sql = $inlineSql;
        } else {
            throw new \RuntimeException(sprintf('Entry "%s" in "%s" must declare exactly one of a non-empty "file" or "sql" string.', $entryLabel, $yamlFilePath));
        }

        return new SqlYamlDefinition(
            rtrim($sql),
            $this->normalizeParameterSets($entry['parameters'] ?? null, $entryLabel, $yamlFilePath),
            $this->normalizePlatforms($entry['platforms'] ?? null, $entryLabel, $yamlFilePath),
        );
    }

    private function readSqlFile(
        string $file,
        string $entryLabel,
        string $yamlFilePath,
        string $baseDirectory
    ): string {
        $sqlFilePath = $baseDirectory . '/' . $file;
        if (!is_file($sqlFilePath)) {
            throw new \RuntimeException(sprintf('SQL file "%s" declared in entry "%s" in "%s" does not exist.', $sqlFilePath, $entryLabel, $yamlFilePath));
        }

        $sql = file_get_contents($sqlFilePath);
        if ($sql === false) {
            throw new \RuntimeException(sprintf('Could not read SQL file "%s".', $sqlFilePath));
        }

        return $sql;
    }

    /**
     * @param mixed $parameters
     *
     * @return list<array<int|string, mixed>>
     */
    private function normalizeParameterSets(
        $parameters,
        string $entryLabel,
        string $yamlFilePath
    ): array {
        if ($parameters === null) {
            return [[]];
        }

        if (!is_array($parameters)) {
            throw new \RuntimeException(sprintf('"parameters" for entry "%s" in "%s" must be an array.', $entryLabel, $yamlFilePath));
        }

        if ($parameters === [] || !$this->isListOfParameterSets($parameters)) {
            /** @var array<int|string, mixed> $parameters */
            return [$parameters];
        }

        /** @var list<array<int|string, mixed>> $parameters */
        return array_values($parameters);
    }

    /**
     * Determines whether the given array is a list of parameter sets (i.e. a list whose
     * every value is itself an array), as opposed to a single, flat parameter set.
     *
     * @param array<int|string, mixed> $array
     */
    private function isListOfParameterSets(array $array): bool
    {
        if (!array_is_list($array)) {
            return false;
        }

        foreach ($array as $value) {
            if (!is_array($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $platforms
     *
     * @return list<string>
     */
    private function normalizePlatforms(
        $platforms,
        string $entryLabel,
        string $yamlFilePath
    ): array {
        if ($platforms === null) {
            return [];
        }

        if (is_string($platforms)) {
            $platforms = [$platforms];
        }

        if (!is_array($platforms)) {
            throw new \RuntimeException(sprintf('"platforms" for entry "%s" in "%s" must be a string or a list of strings.', $entryLabel, $yamlFilePath));
        }

        $normalized = [];
        foreach ($platforms as $platform) {
            if (!is_string($platform) || !in_array($platform, SqlYamlPlatform::all(), true)) {
                throw new \RuntimeException(sprintf(
                    '"platforms" for entry "%s" in "%s" must only contain one of "%s".',
                    $entryLabel,
                    $yamlFilePath,
                    implode('", "', SqlYamlPlatform::all()),
                ));
            }

            $normalized[] = $platform;
        }

        return $normalized;
    }
}
