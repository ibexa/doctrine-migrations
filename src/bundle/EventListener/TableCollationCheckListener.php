<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\EventListener;

use Doctrine\Migrations\Event\MigrationsEventArgs;
use Doctrine\Migrations\Version\Direction;
use Ibexa\Contracts\DoctrineMigrations\Exception\TableCollationMismatchException;
use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformName;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformResolver;

/**
 * Stops an up migrate on MySQL or MariaDB before any migration runs when one of Ibexa's tables already in the database
 * doesn't use the collation new tables would get. A foreign key between string columns of different collations fails
 * there, and halfway through a run, since MySQL and MariaDB can't roll schema changes back: a new table referencing an
 * existing one (an oauth2 consent its user's login, a shopping list entry its product's code) would stop the run.
 *
 * Only Ibexa's tables count, the ones named "ez*" or "ibexa_*": a project keeps tables of its own in the same database,
 * in whatever collation it likes, and no Ibexa foreign key points at them. Project tables that happen to be named like
 * Ibexa's go in "ibexa_doctrine_migrations.table_collation_check.ignored_tables", and "enabled: false" there turns the
 * check off.
 */
final class TableCollationCheckListener
{
    /**
     * The charset MariaDB's charset-independent collations ("uca1400_ai_ci") are taken with when none is configured.
     */
    private const CHARSET_INDEPENDENT_COLLATION_CHARSET = 'utf8mb4';

    private const IBEXA_TABLE_PATTERN = '/^(ez|ibexa_)/';

    private MigrationRunContext $migrationRunContext;

    /** @var list<string> */
    private array $ignoredTables;

    /**
     * @param list<string> $ignoredTables table names, "*" matching any characters
     */
    public function __construct(
        MigrationRunContext $migrationRunContext,
        array $ignoredTables = []
    ) {
        $this->migrationRunContext = $migrationRunContext;
        $this->ignoredTables = $ignoredTables;
    }

    public function onMigrationsMigrating(MigrationsEventArgs $args): void
    {
        if ($args->getPlan()->getDirection() === Direction::DOWN) {
            return;
        }

        $connection = $args->getConnection();
        // MariaDB is resolved as MySQL too
        if (DatabasePlatformResolver::resolveName($connection->getDatabasePlatform()) !== DatabasePlatformName::MYSQL) {
            return;
        }

        $expected = self::normalize($this->migrationRunContext->getMysqlTableCollation());
        $charset = self::normalize(
            $this->migrationRunContext->getMysqlTableCharset() ?? self::CHARSET_INDEPENDENT_COLLATION_CHARSET
        );
        $mismatched = [];
        $tables = $connection->fetchAllKeyValue(
            "SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_TYPE = 'BASE TABLE' AND TABLE_COLLATION IS NOT NULL ORDER BY TABLE_NAME"
        );
        foreach ($tables as $table => $collation) {
            $table = (string)$table;
            if (!is_string($collation) || !$this->isIbexaTable($table)) {
                continue;
            }

            $collation = self::normalize($collation);
            // MariaDB's charset-independent names ("uca1400_ai_ci") show with the charset in front
            if ($collation !== $expected && $collation !== $charset . '_' . $expected) {
                $mismatched[$table] = $collation;
            }
        }

        if ($mismatched === []) {
            return;
        }

        throw new TableCollationMismatchException($expected, $mismatched);
    }

    private function isIbexaTable(string $table): bool
    {
        if (preg_match(self::IBEXA_TABLE_PATTERN, $table) !== 1) {
            return false;
        }

        foreach ($this->ignoredTables as $ignoredTable) {
            if (fnmatch($ignoredTable, $table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * MySQL 8 reports "utf8" collations, an alias of "utf8mb3", under their utf8mb3 name.
     */
    private static function normalize(string $name): string
    {
        $name = strtolower($name);
        if ($name === 'utf8') {
            return 'utf8mb3';
        }

        return str_starts_with($name, 'utf8_') ? 'utf8mb3_' . substr($name, 5) : $name;
    }
}
