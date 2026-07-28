<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Migrations;

use Ibexa\Bundle\DoctrineMigrations\Comparator\IbexaMigrationComparator;

/**
 * Interface representing an Ibexa-internal Doctrine migration.
 *
 * Migration classes implementing this interface can declare their target Ibexa version
 * and creation date so that they can be ordered correctly (and executed first)
 * by the {@see IbexaMigrationComparator}.
 */
interface IbexaMigrationInterface
{
    /**
     * Returns the target Ibexa version this migration is for (e.g. '5.0.0').
     */
    public static function getTargetVersion(): string;

    /**
     * Returns the creation date of this migration.
     */
    public static function getCreationDate(): \DateTimeInterface;
}
