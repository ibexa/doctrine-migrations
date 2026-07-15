<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures;

use Ibexa\Contracts\DoctrineMigrations\Migrations\AbstractVersion;

final class ConcreteAbstractVersion extends AbstractVersion
{
    public function getDescription(): string
    {
        return 'Test YAML-based multi-platform migration';
    }

    protected function getYamlFilePath(): string
    {
        return __DIR__ . '/abstract-version-definitions.yaml';
    }
}
