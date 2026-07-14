<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineMigrations\Migration\Yaml;

/**
 * The database platform identifiers recognized by the `platforms` key of a
 * {@see SqlYamlDefinitionLoader} entry.
 */
final class SqlYamlPlatform
{
    public const MYSQL = 'mysql';

    public const POSTGRESQL = 'postgresql';

    public const SQLITE = 'sqlite';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::MYSQL, self::POSTGRESQL, self::SQLITE];
    }
}
