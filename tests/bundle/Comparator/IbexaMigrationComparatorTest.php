<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Comparator;

use Doctrine\Migrations\Version\Comparator;
use Doctrine\Migrations\Version\Version;
use Ibexa\Bundle\DoctrineMigrations\Comparator\IbexaMigrationComparator;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\IbexaMigrationV500A;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\IbexaMigrationV500B;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\IbexaMigrationV510;
use PHPUnit\Framework\TestCase;

final class IbexaMigrationComparatorTest extends TestCase
{
    private IbexaMigrationComparator $comparator;

    protected function setUp(): void
    {
        $this->comparator = new IbexaMigrationComparator();
    }

    public function testIbexaMigrationSortsBeforeNonIbexa(): void
    {
        $ibexa = new Version(IbexaMigrationV500A::class);
        $nonIbexa = new Version(self::class);

        self::assertLessThan(0, $this->comparator->compare($ibexa, $nonIbexa));
    }

    public function testNonIbexaSortsAfterIbexa(): void
    {
        $ibexa = new Version(IbexaMigrationV500A::class);
        $nonIbexa = new Version(self::class);

        self::assertGreaterThan(0, $this->comparator->compare($nonIbexa, $ibexa));
    }

    public function testTwoIbexaMigrationsSortedByTargetVersion(): void
    {
        $v500 = new Version(IbexaMigrationV500A::class);
        $v510 = new Version(IbexaMigrationV510::class);

        self::assertLessThan(0, $this->comparator->compare($v500, $v510));
        self::assertGreaterThan(0, $this->comparator->compare($v510, $v500));
    }

    public function testTwoIbexaMigrationsSameVersionSortedByCreationDate(): void
    {
        $earlier = new Version(IbexaMigrationV500A::class); // 2026-01-01
        $later = new Version(IbexaMigrationV500B::class);   // 2026-06-01

        self::assertLessThan(0, $this->comparator->compare($earlier, $later));
        self::assertGreaterThan(0, $this->comparator->compare($later, $earlier));
    }

    public function testTwoIbexaMigrationsSameVersionAndDateCompareAsEqual(): void
    {
        $a = new Version(IbexaMigrationV500A::class);
        $b = new Version(IbexaMigrationV500A::class);

        self::assertSame(0, $this->comparator->compare($a, $b));
    }

    public function testTwoNonIbexaMigrationsDelegateToFallbackComparator(): void
    {
        $fallback = $this->createMock(Comparator::class);
        $fallback->expects(self::once())
            ->method('compare')
            ->willReturn(42);

        $comparator = new IbexaMigrationComparator($fallback);

        $a = new Version(self::class);
        $b = new Version(IbexaMigrationComparator::class);

        self::assertSame(42, $comparator->compare($a, $b));
    }

    public function testDefaultFallbackIsAlphabeticalOrder(): void
    {
        // Non-existent class names — class_exists() returns false so they are
        // treated as non-Ibexa and routed through AlphabeticalComparator.
        $first = new Version('AAA\Migration20260101');
        $second = new Version('ZZZ\Migration20260101');

        self::assertLessThan(0, $this->comparator->compare($first, $second));
        self::assertGreaterThan(0, $this->comparator->compare($second, $first));
    }
}
