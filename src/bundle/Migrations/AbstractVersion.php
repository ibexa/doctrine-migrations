<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

/**
 * Base class for Ibexa migrations whose SQL is fully declared in a single YAML file,
 * covering all supported database platforms and both the "up" and "down" directions
 * (see {@see YamlSqlFileMigrationTrait}), instead of requiring a separate PHP
 * implementation per platform.
 *
 * If the YAML file declares no "down" section, {@see down()} throws an
 * {@see IrreversibleMigration} exception.
 */
abstract class AbstractVersion extends AbstractMigration
{
    use YamlSqlFileMigrationTrait;

    final public function up(Schema $schema): void
    {
        $this->addUpSqlFromYamlFile($this->getYamlFilePath());
    }

    final public function down(Schema $schema): void
    {
        $this->addDownSqlFromYamlFile($this->getYamlFilePath());
    }

    /**
     * Returns the absolute path to the YAML file declaring this migration's SQL statements.
     *
     * @see YamlSqlFileMigrationTrait
     */
    abstract protected function getYamlFilePath(): string;
}
