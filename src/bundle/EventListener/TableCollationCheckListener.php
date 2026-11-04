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
 * Stops a migrate on MySQL or MariaDB before any migration runs when the database already has tables and none of them
 * uses the collation new tables would get. A foreign key between string columns of different collations fails there,
 * and halfway through a run, since MySQL and MariaDB can't roll schema changes back: a new table referencing an
 * existing one (an oauth2 consent its user's login, a shopping list entry its product's code) would stop the run.
 */
final class TableCollationCheckListener
{
    /**
     * What {@see AbstractSqlMigration::addSqlFile()} leaves in the SQL files when nothing is configured.
     */
    private const FILE_COLLATION = 'utf8mb4_unicode_520_ci';

    private MigrationRunContext $migrationRunContext;

    private string $metadataTableName;

    public function __construct(
        MigrationRunContext $migrationRunContext,
        string $metadataTableName
    ) {
        $this->migrationRunContext = $migrationRunContext;
        $this->metadataTableName = $metadataTableName;
    }

    public function onMigrationsMigrating(MigrationsEventArgs $args): void
    {
        $connection = $args->getConnection();
        // MariaDB is resolved as MySQL too
        if (DatabasePlatformResolver::resolveName($connection->getDatabasePlatform()) !== DatabasePlatformName::MYSQL) {
            return;
        }

        // Doctrine's own table, created before this event and in DBAL's defaults, says nothing about the rest
        $collations = $connection->fetchFirstColumn(
            "SELECT DISTINCT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_TYPE = 'BASE TABLE' AND TABLE_COLLATION IS NOT NULL AND TABLE_NAME <> ?",
            [$this->metadataTableName]
        );
        $collations = array_values(array_filter($collations, 'is_string'));

        $expected = self::normalize($this->getNewTableCollation());
        foreach ($collations as $collation) {
            $collation = self::normalize($collation);
            // MariaDB's charset-independent names ("uca1400_ai_ci") show with the charset in front
            if ($collation === $expected || str_ends_with($collation, '_' . $expected)) {
                return;
            }
        }

        if ($collations === []) {
            return;
        }

        throw new RuntimeException(sprintf(
            'The tables in this database use %s, but the tables these migrations create would get %s, from '
            . '"ibexa_doctrine_schema.tables.options" (DATABASE_CHARSET and DATABASE_COLLATION in the recipe). '
            . 'A foreign key between string columns of different collations fails, so nothing was migrated. '
            . 'Configure the collation the tables use, or convert the tables to the configured one first.',
            implode(', ', $collations),
            $expected
        ));
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
