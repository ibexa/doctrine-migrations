<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Ibexa\DoctrineMigrations\Migration\SqlPlatform;

/**
 * Resolves a {@see Connection}'s database platform to one of the {@see SqlPlatform}
 * identifiers, or null if it isn't one of the recognized platforms.
 */
final class DatabasePlatformResolver
{
    public static function resolve(Connection $connection): ?SqlPlatform
    {
        $platform = $connection->getDatabasePlatform();

        // Not MySQLPlatform: as of DBAL 4, MariaDBPlatform extends only AbstractMySQLPlatform.
        if ($platform instanceof AbstractMySQLPlatform) {
            return SqlPlatform::MYSQL;
        }

        // DBAL 3 renamed these platform classes; DBAL 2 uses the original names.
        $postgresqlClass = class_exists('Doctrine\\DBAL\\Platforms\\PostgreSQLPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\PostgreSQLPlatform'
            : 'Doctrine\\DBAL\\Platforms\\PostgreSqlPlatform';
        if ($platform instanceof $postgresqlClass) {
            return SqlPlatform::POSTGRESQL;
        }

        $sqliteClass = class_exists('Doctrine\\DBAL\\Platforms\\SQLitePlatform')
            ? 'Doctrine\\DBAL\\Platforms\\SQLitePlatform'
            : 'Doctrine\\DBAL\\Platforms\\SQLitePlatform';
        if ($platform instanceof $sqliteClass) {
            return SqlPlatform::SQLITE;
        }

        return null;
    }
}
