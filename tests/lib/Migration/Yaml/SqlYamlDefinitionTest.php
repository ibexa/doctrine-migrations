<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineMigrations\Migration\Yaml;

use Ibexa\DoctrineMigrations\Migration\Yaml\SqlYamlDefinition;
use Ibexa\DoctrineMigrations\Migration\Yaml\SqlYamlPlatform;
use PHPUnit\Framework\TestCase;

final class SqlYamlDefinitionTest extends TestCase
{
    public function testAppliesToPlatformIsTrueForAnyPlatformWhenNoneAreDeclared(): void
    {
        $definition = new SqlYamlDefinition('SELECT 1;', [[]]);

        self::assertSame([], $definition->getPlatforms());
        self::assertTrue($definition->appliesToPlatform(SqlYamlPlatform::MYSQL));
        self::assertTrue($definition->appliesToPlatform(SqlYamlPlatform::POSTGRESQL));
        self::assertTrue($definition->appliesToPlatform(SqlYamlPlatform::SQLITE));
        self::assertTrue($definition->appliesToPlatform(null));
    }

    public function testAppliesToPlatformIsTrueOnlyForDeclaredPlatforms(): void
    {
        $definition = new SqlYamlDefinition('SELECT 1;', [[]], [SqlYamlPlatform::MYSQL, SqlYamlPlatform::SQLITE]);

        self::assertTrue($definition->appliesToPlatform(SqlYamlPlatform::MYSQL));
        self::assertTrue($definition->appliesToPlatform(SqlYamlPlatform::SQLITE));
        self::assertFalse($definition->appliesToPlatform(SqlYamlPlatform::POSTGRESQL));
    }

    public function testAppliesToPlatformIsFalseForUnresolvedPlatformWhenRestricted(): void
    {
        $definition = new SqlYamlDefinition('SELECT 1;', [[]], [SqlYamlPlatform::MYSQL]);

        self::assertFalse($definition->appliesToPlatform(null));
    }
}
