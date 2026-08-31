<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Standalone bootstrap for the ApprovalBundle test-suite.
 *
 * Unlike the Kimai core bootstrap (tests/bootstrap.php) this does NOT re-install
 * the test database - that would run on every single invocation and only works on
 * POSIX shells. Use "composer db-setup" once instead, see README-TESTING.md.
 */

$autoloaders = [
    // plugin checked out standalone with its own dependencies
    __DIR__ . '/../vendor/autoload.php',
    // plugin lives inside a Kimai checkout: var/plugins/ApprovalBundle/Tests -> <kimai>/vendor
    __DIR__ . '/../../../../vendor/autoload.php',
];

foreach ($autoloaders as $autoloader) {
    if (is_file($autoloader)) {
        require $autoloader;

        return;
    }
}

throw new RuntimeException('Could not find a composer autoloader. Run "composer install" in your Kimai directory (the one containing var/plugins/ApprovalBundle) to install the dev dependencies.');
