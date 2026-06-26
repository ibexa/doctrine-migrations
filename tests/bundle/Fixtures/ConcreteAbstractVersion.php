<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures;

use Doctrine\DBAL\Schema\Schema;
use Ibexa\Bundle\DoctrineMigrations\Migrations\AbstractVersion;

final class ConcreteAbstractVersion extends AbstractVersion
{
    private bool $mysqlCalled = false;

    private bool $postgresCalled = false;

    public function getDescription(): string
    {
        return 'Test multi-platform migration';
    }

    public function down(Schema $schema): void {}

    protected function upForMysql(Schema $schema): void
    {
        $this->mysqlCalled = true;
    }

    protected function upForPostgresql(Schema $schema): void
    {
        $this->postgresCalled = true;
    }

    public function wasMysqlCalled(): bool
    {
        return $this->mysqlCalled;
    }

    public function wasPostgresCalled(): bool
    {
        return $this->postgresCalled;
    }
}
