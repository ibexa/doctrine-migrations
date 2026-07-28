<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures;

use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationInterface;

final class IbexaMigrationV510 implements IbexaMigrationInterface
{
    public static function getTargetVersion(): string
    {
        return '5.1.0';
    }

    public static function getCreationDate(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-01-01 00:00:00');
    }
}
