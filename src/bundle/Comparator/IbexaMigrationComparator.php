<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Comparator;

use Doctrine\Migrations\Version\AlphabeticalComparator;
use Doctrine\Migrations\Version\Comparator;
use Doctrine\Migrations\Version\Version;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationInterface;

/**
 * Custom migration comparator that ensures Ibexa-internal migrations (implementing IbexaMigrationInterface)
 * are always sorted and executed first, followed by project-level migrations.
 *
 * Within Ibexa migrations, ordering is resolved chronologically by version, and then by creation date.
 * Non-Ibexa migrations are sorted using a configurable fallback comparator (defaulting to AlphabeticalComparator).
 */
final class IbexaMigrationComparator implements Comparator
{
    private Comparator $fallbackComparator;

    public function __construct(?Comparator $fallbackComparator = null)
    {
        $this->fallbackComparator = $fallbackComparator ?? new AlphabeticalComparator();
    }

    public function compare(
        Version $a,
        Version $b
    ): int {
        $classA = (string) $a;
        $classB = (string) $b;

        $isIbexaA = class_exists($classA) && is_subclass_of($classA, IbexaMigrationInterface::class);
        $isIbexaB = class_exists($classB) && is_subclass_of($classB, IbexaMigrationInterface::class);

        if ($isIbexaA && !$isIbexaB) {
            return -1;
        }

        if (!$isIbexaA && $isIbexaB) {
            return 1;
        }

        if ($isIbexaA) {
            /** @var class-string<IbexaMigrationInterface> $classA */
            /** @var class-string<IbexaMigrationInterface> $classB */
            $versionA = $classA::getTargetVersion();
            $versionB = $classB::getTargetVersion();

            $versionComparison = version_compare($versionA, $versionB);
            if ($versionComparison !== 0) {
                return $versionComparison;
            }

            $dateA = $classA::getCreationDate();
            $dateB = $classB::getCreationDate();

            return $dateA <=> $dateB;
        }

        return $this->fallbackComparator->compare($a, $b);
    }
}
