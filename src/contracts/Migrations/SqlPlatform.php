<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Migrations;

use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformName;

/**
 * Database platforms an {@see AbstractSqlMigration} can target.
 *
 * Unlike {@see DatabasePlatformName}, MariaDB is a platform of its own here: it runs MySQL's SQL,
 * but some DDL (JSON columns, for instance) has to match what Doctrine DBAL generates for MariaDB.
 */
enum SqlPlatform: string
{
    case MYSQL = 'mysql';

    case MARIADB = 'mariadb';

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
