<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineMigrations\Migration\Yaml;

use Ibexa\DoctrineMigrations\Migration\Yaml\SqlYamlDefinitionLoader;
use Ibexa\DoctrineMigrations\Migration\Yaml\SqlYamlPlatform;
use PHPUnit\Framework\TestCase;

final class SqlYamlDefinitionLoaderTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/Fixtures';

    public function testLoadReturnsOneDefinitionPerEntryWithNormalizedParameterSets(): void
    {
        $loader = new SqlYamlDefinitionLoader();

        $definitions = $loader->load(self::FIXTURES_DIR . '/definitions.yaml');

        self::assertCount(5, $definitions);

        // No `parameters` declared: SQL is queued once, with no parameters.
        self::assertSame('SELECT 1;', $definitions[0]->getSql());
        self::assertSame([[]], $definitions[0]->getParameterSets());

        // A single, flat parameter set: SQL is queued once with that set.
        self::assertStringContainsString('INSERT INTO setting', $definitions[1]->getSql());
        self::assertSame(
            [['name' => 'my_setting', 'value' => '42']],
            $definitions[1]->getParameterSets(),
        );

        // A list of parameter sets: SQL is queued once per set.
        self::assertSame(
            [
                ['name' => 'setting_a', 'value' => '1'],
                ['name' => 'setting_b', 'value' => '2'],
            ],
            $definitions[2]->getParameterSets(),
        );

        // A flat list of positional (scalar) values is treated as a single parameter set.
        self::assertSame([[1, 2]], $definitions[3]->getParameterSets());

        // Inline `sql` (instead of `file`): SQL is taken directly from the YAML file.
        self::assertSame('DELETE FROM setting WHERE name = :name', $definitions[4]->getSql());
        self::assertSame([['name' => 'obsolete_setting']], $definitions[4]->getParameterSets());

        // No `platforms` declared on any entry: applies to all platforms.
        foreach ($definitions as $definition) {
            self::assertSame([], $definition->getPlatforms());
        }
    }

    public function testLoadNormalizesPlatformsToAList(): void
    {
        $loader = new SqlYamlDefinitionLoader();

        $definitions = $loader->load(self::FIXTURES_DIR . '/platforms.yaml');

        self::assertCount(3, $definitions);

        // No `platforms` declared: applies to all platforms.
        self::assertSame([], $definitions[0]->getPlatforms());

        // A single platform string is normalized to a one-element list.
        self::assertSame([SqlYamlPlatform::MYSQL], $definitions[1]->getPlatforms());

        // A list of platforms is kept as-is.
        self::assertSame([SqlYamlPlatform::MYSQL, SqlYamlPlatform::POSTGRESQL], $definitions[2]->getPlatforms());
    }

    public function testLoadThrowsWhenPlatformNameIsUnrecognized(): void
    {
        $loader = new SqlYamlDefinitionLoader();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/"platforms".*must only contain one of/');

        $loader->load(self::FIXTURES_DIR . '/invalid-platform-name.yaml');
    }

    public function testLoadThrowsWhenPlatformsIsNeitherAStringNorAnArray(): void
    {
        $loader = new SqlYamlDefinitionLoader();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/"platforms".*must be a string or a list of strings/');

        $loader->load(self::FIXTURES_DIR . '/invalid-platforms-type.yaml');
    }

    public function testLoadTreatsEmptyYamlFileAsNoDefinitions(): void
    {
        $loader = new SqlYamlDefinitionLoader();

        self::assertSame([], $loader->load(self::FIXTURES_DIR . '/empty.yaml'));
    }

    public function testLoadThrowsWhenYamlFileDoesNotExist(): void
    {
        $loader = new SqlYamlDefinitionLoader();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        $loader->load(self::FIXTURES_DIR . '/does-not-exist.yaml');
    }

    public function testLoadThrowsWhenYamlFileIsNotAList(): void
    {
        $loader = new SqlYamlDefinitionLoader();

        $this->expectException(\RuntimeException::class);

        $loader->load(self::FIXTURES_DIR . '/not-a-list.yaml');
    }

    public function testLoadThrowsWhenEntryDeclaresNeitherFileNorSql(): void
    {
        $loader = new SqlYamlDefinitionLoader();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/must declare exactly one of a non-empty "file" or "sql" string/');

        $loader->load(self::FIXTURES_DIR . '/neither-file-nor-sql.yaml');
    }

    public function testLoadThrowsWhenEntryDeclaresBothFileAndSql(): void
    {
        $loader = new SqlYamlDefinitionLoader();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/must declare exactly one of a non-empty "file" or "sql" string/');

        $loader->load(self::FIXTURES_DIR . '/both-file-and-sql.yaml');
    }

    public function testLoadThrowsWhenSqlFileDoesNotExist(): void
    {
        $loader = new SqlYamlDefinitionLoader();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        $loader->load(self::FIXTURES_DIR . '/missing-sql-file.yaml');
    }

    public function testLoadThrowsWhenParametersIsNotAnArray(): void
    {
        $loader = new SqlYamlDefinitionLoader();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/"parameters".*must be an array/');

        $loader->load(self::FIXTURES_DIR . '/invalid-parameters.yaml');
    }
}
