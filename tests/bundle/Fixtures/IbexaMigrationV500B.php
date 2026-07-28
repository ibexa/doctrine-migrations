<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures;

use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationInterface;

/** Same target version as V500A but a later creation date. */
final class IbexaMigrationV500B implements IbexaMigrationInterface
{
    public static function getTargetVersion(): string
    {
        return '5.0.0';
    }

    public static function getCreationDate(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-06-01 00:00:00');
    }
}
