<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations;

use Ibexa\Bundle\DoctrineMigrations\DependencyInjection\Compiler\RegisterMigrationsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class IbexaDoctrineMigrationsBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new RegisterMigrationsPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -10);
    }
}
