<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Base class for Ibexa migrations whose SQL is fully declared in a single YAML file,
 * covering all supported database platforms (see {@see YamlSqlFileMigrationTrait}),
 * instead of requiring a separate PHP implementation per platform.
 */
abstract class AbstractVersion extends AbstractMigration
{
    use YamlSqlFileMigrationTrait;

    final public function up(Schema $schema): void
    {
        $this->addSqlFromYamlFile($this->getYamlFilePath());
    }

    /**
     * Returns the absolute path to the YAML file declaring this migration's SQL statements.
     *
     * @see YamlSqlFileMigrationTrait
     */
    abstract protected function getYamlFilePath(): string;
}
