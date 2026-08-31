<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Builds the test database from scratch:
 *
 *   1. refuses to run unless the test environment points at a "*_test" database
 *   2. Kimai core schema + the deterministic test data (kimai:reset:test)
 *   3. the ApprovalBundle tables
 *
 * The plugin's own "kimai:bundle:approval:install" command cannot be used here: Kimai's Kernel
 * skips plugin discovery in the "test" environment, so that command is not registered. The
 * migrations are executed directly through the bundle's migration configuration instead.
 */

require __DIR__ . '/guard-test-database.php';

$kimaiDir = dirname(__DIR__, 4);
chdir($kimaiDir);

$php = escapeshellarg(PHP_BINARY);

$commands = [
    'Kimai core schema + test data' => "{$php} bin/console kimai:reset:test --env=test --no-interaction",
    'ApprovalBundle tables' => "{$php} bin/console doctrine:migrations:migrate --env=test --no-interaction --configuration=var/plugins/ApprovalBundle/Migrations/approval.yaml",
];

foreach ($commands as $label => $command) {
    echo "\n>>> {$label}\n";
    passthru($command, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, "\nFAILED: {$label}\n");
        exit($exitCode);
    }
}

echo "\nTest database is ready.\n";
