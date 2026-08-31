<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Safety guard for the destructive database setup.
 *
 * "kimai:reset:test" runs "doctrine:schema:drop --force --full-database". If the test
 * environment resolves DATABASE_URL to the real Kimai database - which happens whenever
 * <kimai>/.env.test.local is missing - that command destroys production data.
 *
 * This script refuses to continue unless the database name ends with "_test".
 */

$kimaiDir = dirname(__DIR__, 4);

require $kimaiDir . '/vendor/autoload.php';

// force the "test" environment before the dotenv files are evaluated
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv($kimaiDir . '/.env', 'test');

$url = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? '';
$database = ltrim((string) parse_url($url, PHP_URL_PATH), '/');

if ($database === '') {
    fwrite(STDERR, "ABORTED: could not determine the database name from DATABASE_URL.\n");
    exit(1);
}

if (!str_ends_with($database, '_test')) {
    fwrite(STDERR, <<<TXT

        ABORTED - refusing to reset the database "{$database}".

        The test environment does not point at a test database, so continuing would DROP
        ALL TABLES of your real Kimai installation.

        Create {$kimaiDir}/.env.test.local containing a dedicated test connection, e.g.

            DATABASE_URL=mysql://kimai2_test:kimai2_test@127.0.0.1:3306/kimai2_test?charset=utf8mb4&serverVersion=10.5.8-MariaDB

        The database name has to end with "_test". That file is only read for APP_ENV=test
        and is git-ignored, so dev and prod stay untouched.


        TXT);
    exit(1);
}

echo "Test database check passed: \"{$database}\"\n";
