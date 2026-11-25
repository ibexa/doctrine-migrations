<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Exception;

use RuntimeException;

/**
 * Thrown before a migrate when Ibexa tables use another collation than the tables the migrations create would get.
 */
final class TableCollationMismatchException extends RuntimeException
{
    private const LISTED_TABLES = 5;

    /**
     * @param non-empty-array<string, string> $mismatchedCollations table name => collation
     */
    public function __construct(
        string $expectedCollation,
        string $charset,
        array $mismatchedCollations
    ) {
        $listed = [];
        foreach (array_slice($mismatchedCollations, 0, self::LISTED_TABLES, true) as $table => $collation) {
            $listed[] = sprintf('%s (%s)', $table, $collation);
        }

        $count = count($mismatchedCollations);

        parent::__construct(sprintf(
            'Ibexa tables in this database use another collation than the %s the tables these migrations create '
            . 'would get, from "ibexa_doctrine_schema.tables.options" (DATABASE_CHARSET and DATABASE_COLLATION in the '
            . 'recipe): %s. A foreign key between string columns of different collations fails, so nothing was '
            . 'migrated. Configure the collation those tables use, or convert those tables to the configured one '
            . 'first, columns included, e.g. "ALTER TABLE %s CONVERT TO CHARACTER SET %s COLLATE %s;" ("ALTER TABLE ... '
            . 'COLLATE" alone changes only the default of new columns). Converting rewrites the whole table and blocks '
            . 'writes to it while it runs, changes every string column in it, including ones given another collation '
            . 'on purpose, and changes how their values compare and sort, so a unique index may reject values that '
            . 'now count as duplicates: back the database up and try it on a copy first. A table that has to keep its '
            . 'collation, or that Ibexa doesn\'t own, can go in "ibexa_doctrine_migrations.table_collation_check.'
            . 'ignored_tables" instead, as long as no new foreign key points at it; "enabled: false" there turns the '
            . 'whole check off.',
            $expectedCollation,
            implode(', ', $listed)
                . ($count > self::LISTED_TABLES ? sprintf(' and %d more', $count - self::LISTED_TABLES) : ''),
            array_key_first($mismatchedCollations),
            $charset,
            $expectedCollation
        ));
    }
}
