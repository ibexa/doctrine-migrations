<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ibexa\Bundle\DoctrineMigrations\Migrations\YamlSqlFileMigrationTrait;

final class YamlSqlFileMigrationFixture extends AbstractMigration
{
    use YamlSqlFileMigrationTrait;

    public function up(Schema $schema): void
    {
        $this->addSqlFromYamlFile(__DIR__ . '/../../lib/Migration/Yaml/Fixtures/definitions.yaml');
    }
}
