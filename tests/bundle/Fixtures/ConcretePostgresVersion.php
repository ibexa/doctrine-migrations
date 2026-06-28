<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures;

use Doctrine\DBAL\Schema\Schema;
use Ibexa\Bundle\DoctrineMigrations\Migrations\Postgres\AbstractPostgresVersion;

final class ConcretePostgresVersion extends AbstractPostgresVersion
{
    private bool $doUpCalled = false;

    private bool $doDownCalled = false;

    public static function getTargetVersion(): string
    {
        return '5.0.0';
    }

    public static function getCreationDate(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-01-01 00:00:00');
    }

    public function getDescription(): string
    {
        return 'Test PostgreSQL migration';
    }

    protected function doUp(Schema $schema): void
    {
        $this->doUpCalled = true;
    }

    protected function doDown(Schema $schema): void
    {
        $this->doDownCalled = true;
    }

    public function wasDoUpCalled(): bool
    {
        return $this->doUpCalled;
    }

    public function wasDoDownCalled(): bool
    {
        return $this->doDownCalled;
    }
}
