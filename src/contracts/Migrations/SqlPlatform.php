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
 *
 * Provided as class constants rather than an enum for PHP 7.4 compatibility.
 */
final class SqlPlatform
{
    public const MYSQL = 'mysql';

    public const MARIADB = 'mariadb';

    public const POSTGRESQL = 'postgresql';

    public const SQLITE = 'sqlite';

    private function __construct()
    {
        // intentionally prevent instantiation
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::MYSQL, self::MARIADB, self::POSTGRESQL, self::SQLITE];
    }
}
