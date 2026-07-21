<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineMigrations\Migration;

/**
 * The database platform identifiers recognized across the Doctrine Migrations integration
 * (see {@see \Ibexa\Bundle\DoctrineMigrations\Migrations\DatabasePlatformResolver}).
 */
final class SqlPlatform
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
