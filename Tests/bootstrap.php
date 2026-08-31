<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Bootstrap for the ApprovalBundle test-suite.
 *
 * Unlike the Kimai core bootstrap (tests/bootstrap.php) this does NOT re-install the test
 * database - that would run on every single invocation and only works on POSIX shells.
 * Use "composer db-setup" once instead, see Tests/README.md.
 */

$autoloaders = [
    // plugin checked out standalone with its own dependencies
    __DIR__ . '/../vendor/autoload.php',
    // plugin lives inside a Kimai checkout: var/plugins/ApprovalBundle/Tests -> <kimai>/vendor
    __DIR__ . '/../../../../vendor/autoload.php',
];

$loaded = false;
foreach ($autoloaders as $autoloader) {
    if (is_file($autoloader)) {
        require $autoloader;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    throw new RuntimeException('Could not find a composer autoloader. Run "composer install" in your Kimai directory (the one containing var/plugins/ApprovalBundle) to install the dev dependencies.');
}

$kimaiDir = dirname(__DIR__, 4);

// single source of truth for the connection: <kimai>/.env plus <kimai>/.env.test.local.
// Existing variables (those set by phpunit.xml.dist) are never overwritten.
if (is_file($kimaiDir . '/.env')) {
    (new Symfony\Component\Dotenv\Dotenv())->bootEnv($kimaiDir . '/.env', 'test');
}

// Safety net for the whole suite: tests truncate and rewrite data, so a misconfigured
// DATABASE_URL must never reach the real Kimai installation.
$database = ltrim((string) parse_url((string) ($_ENV['DATABASE_URL'] ?? ''), PHP_URL_PATH), '/');

if (!str_ends_with($database, '_test')) {
    throw new RuntimeException(sprintf(
        'Refusing to run the test-suite against the database "%s" - the name must end with "_test". ' .
        'Create %s/.env.test.local with a dedicated test connection (see Tests/README.md).',
        $database !== '' ? $database : '(unknown)',
        $kimaiDir
    ));
}
