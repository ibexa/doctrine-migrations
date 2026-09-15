<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

use Ibexa\Contracts\Test\Core\Bootstrapper\Bootstrapper;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// This package's TestKernel is a plain Symfony kernel with two in-memory SQLite connections built
// inline -- there is no repository, no schema and no fixtures -- so every hook Bootstrapper would
// otherwise run is either unsatisfiable (and dropped by RemoveUnsatisfiableHooksPass) or pointless
// here. Database preparation is switched off for the same reason: doctrine:database:drop/create
// have nothing to act on.
(new Bootstrapper())->bootstrap(null, [
    Bootstrapper::class => [Bootstrapper::OPTION_PREPARE_DATABASE => false],
]);
