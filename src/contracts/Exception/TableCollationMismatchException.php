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
            'Ibexa tables use another collation than the %s new tables get from "ibexa_doctrine_schema.tables.options": '
            . '%s. Foreign keys between them would fail, so nothing was migrated. Configure the collation those tables '
            . 'use, or convert them first, e.g. "ALTER TABLE %s CONVERT TO CHARACTER SET %s COLLATE %s;", which rewrites '
            . 'and locks the table and changes how its string values compare: back the database up first. A table that '
            . 'has to keep its collation can go in "ibexa_doctrine_migrations.table_collation_check.ignored_tables".',
            $expectedCollation,
            implode(', ', $listed)
                . ($count > self::LISTED_TABLES ? sprintf(' and %d more', $count - self::LISTED_TABLES) : ''),
            array_key_first($mismatchedCollations),
            $charset,
            $expectedCollation
        ));
    }
}
