<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
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

        if ($platform instanceof PostgreSQLPlatform) {
            return SqlPlatform::POSTGRESQL;
        }

        if ($platform instanceof SQLitePlatform) {
            return SqlPlatform::SQLITE;
        }

        return null;
    }
}
