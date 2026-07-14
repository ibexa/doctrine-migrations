<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Connection;
use Ibexa\DoctrineMigrations\Migration\Yaml\SqlYamlPlatform;

/**
 * Resolves a {@see Connection}'s database platform to one of the {@see SqlYamlPlatform}
 * identifiers, or null if it isn't one of the recognized platforms.
 */
final class DatabasePlatformResolver
{
    public static function resolve(Connection $connection): ?string
    {
        $platform = $connection->getDatabasePlatform();

        // DBAL 3 renamed these platform classes; DBAL 2 uses the original names.
        $mysqlClass = class_exists('Doctrine\\DBAL\\Platforms\\MySQLPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\MySQLPlatform'
            : 'Doctrine\\DBAL\\Platforms\\MySqlPlatform';
        if ($platform instanceof $mysqlClass) {
            return SqlYamlPlatform::MYSQL;
        }

        $postgresqlClass = class_exists('Doctrine\\DBAL\\Platforms\\PostgreSQLPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\PostgreSQLPlatform'
            : 'Doctrine\\DBAL\\Platforms\\PostgreSqlPlatform';
        if ($platform instanceof $postgresqlClass) {
            return SqlYamlPlatform::POSTGRESQL;
        }

        $sqliteClass = class_exists('Doctrine\\DBAL\\Platforms\\SQLitePlatform')
            ? 'Doctrine\\DBAL\\Platforms\\SQLitePlatform'
            : 'Doctrine\\DBAL\\Platforms\\SqlitePlatform';
        if ($platform instanceof $sqliteClass) {
            return SqlYamlPlatform::SQLITE;
        }

        return null;
    }
}
