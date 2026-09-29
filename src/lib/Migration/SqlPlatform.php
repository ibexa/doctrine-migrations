<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineMigrations\Migration;

use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformName;

/**
 * The database platform identifiers recognized across the Doctrine Migrations integration
 * (see {@see Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformResolver}).
 */
final class SqlPlatform
{
    public const MYSQL = DatabasePlatformName::MYSQL;

    public const POSTGRESQL = DatabasePlatformName::POSTGRESQL;

    public const SQLITE = DatabasePlatformName::SQLITE;

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::MYSQL, self::POSTGRESQL, self::SQLITE];
    }
}
