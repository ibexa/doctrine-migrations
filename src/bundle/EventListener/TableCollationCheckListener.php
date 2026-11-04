<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\EventListener;

use Doctrine\Migrations\Event\MigrationsEventArgs;
use Ibexa\Contracts\DoctrineMigrations\Migrations\AbstractSqlMigration;
use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformName;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformResolver;
use RuntimeException;

/**
 * Stops a migrate on MySQL or MariaDB before any migration runs when one of Ibexa's tables already in the database
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
     * What {@see AbstractSqlMigration::addSqlFile()} leaves in the SQL files when nothing is configured.
     */
    private const FILE_COLLATION = 'utf8mb4_unicode_520_ci';

    private const IBEXA_TABLE_PATTERN = '/^(ez|ibexa_)/';

    private const LISTED_TABLES = 5;

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
        $connection = $args->getConnection();
        // MariaDB is resolved as MySQL too
        if (DatabasePlatformResolver::resolveName($connection->getDatabasePlatform()) !== DatabasePlatformName::MYSQL) {
            return;
        }

        $expected = self::normalize($this->getNewTableCollation());
        $mismatched = [];
        $tables = $connection->fetchAllKeyValue(
            "SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_TYPE = 'BASE TABLE' AND TABLE_COLLATION IS NOT NULL ORDER BY TABLE_NAME"
        );
        foreach ($tables as $table => $collation) {
            if (!is_string($collation) || !$this->isIbexaTable((string)$table)) {
                continue;
            }

            $collation = self::normalize($collation);
            // MariaDB's charset-independent names ("uca1400_ai_ci") show with the charset in front
            if ($collation !== $expected && !str_ends_with($collation, '_' . $expected)) {
                $mismatched[] = sprintf('%s (%s)', $table, $collation);
            }
        }

        if ($mismatched === []) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Ibexa tables in this database use another collation than the %s the tables these migrations create '
            . 'would get, from "ibexa_doctrine_schema.tables.options" (DATABASE_CHARSET and DATABASE_COLLATION in the '
            . 'recipe): %s. A foreign key between string columns of different collations fails, so nothing was '
            . 'migrated. Configure the collation those tables use, or convert them to the configured one first. '
            . 'Tables Ibexa doesn\'t own go in "ibexa_doctrine_migrations.table_collation_check.ignored_tables".',
            $expected,
            implode(', ', array_slice($mismatched, 0, self::LISTED_TABLES))
                . (count($mismatched) > self::LISTED_TABLES ? sprintf(' and %d more', count($mismatched) - self::LISTED_TABLES) : '')
        ));
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
     * The same choice {@see AbstractSqlMigration::addSqlFile()} makes.
     */
    private function getNewTableCollation(): string
    {
        $collation = $this->migrationRunContext->getTableCollation();
        if ($collation !== null) {
            return $collation;
        }

        $charset = $this->migrationRunContext->getTableCharset();

        return $charset !== null ? $charset . '_unicode_ci' : self::FILE_COLLATION;
    }

    /**
     * MySQL 8 reports "utf8" collations, an alias of "utf8mb3", under their utf8mb3 name.
     */
    private static function normalize(string $collation): string
    {
        $collation = strtolower($collation);

        return str_starts_with($collation, 'utf8_') ? 'utf8mb3_' . substr($collation, 5) : $collation;
    }
}
