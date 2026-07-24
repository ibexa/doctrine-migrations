<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineMigrations\Migration;

use Ibexa\DoctrineMigrations\Migration\SqlPlatform;
use PHPUnit\Framework\TestCase;

final class SqlPlatformTest extends TestCase
{
    public function testAllReturnsEveryConstant(): void
    {
        self::assertSame(
            [SqlPlatform::MYSQL, SqlPlatform::POSTGRESQL, SqlPlatform::SQLITE],
            SqlPlatform::all(),
        );
    }
}
