<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineMigrations\Migration;

/**
 * The database platform identifiers recognized across the Doctrine Migrations integration
 * (see {@see Ibexa\Bundle\DoctrineMigrations\Migrations\DatabasePlatformResolver}).
 */
enum SqlPlatform: string
{
    case MYSQL = 'mysql';

    case POSTGRESQL = 'postgresql';

    case SQLITE = 'sqlite';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
