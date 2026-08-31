# Testing the ApprovalBundle

The suite runs on its own - it never executes the Kimai core tests.

## Requirements

The plugin has to sit inside a Kimai checkout (`<kimai>/var/plugins/ApprovalBundle`) whose
**dev dependencies are installed**, because PHPUnit and the core test helpers come from there:

```
cd <kimai>
composer install
```

## Running

All commands are executed **from the plugin directory**, `phpunit.xml.dist` is picked up automatically:

```
cd <kimai>/var/plugins/ApprovalBundle

composer tests-unit          # fast, no database required
composer tests-testdox       # more details
composer tests-integration   # boots the kernel, needs a database
composer tests               # everything
```

Or without composer:

```
php ../../../vendor/bin/phpunit
php ../../../vendor/bin/phpunit --filter FormattingTest
php ../../../vendor/bin/phpunit Tests/Toolbox
```

## Seeing what is tested

```
composer tests-testdox    # every test as a readable sentence
composer tests-coverage   # which lines are reached (needs a coverage driver, see below)
```

Lower level listings:

```
php ../../../vendor/bin/phpunit --list-tests    # every test method
php ../../../vendor/bin/phpunit --list-groups   # available groups
```

`tests-coverage` writes a browsable report to `.phpunit.coverage/index.html` and prints a
per-class summary to the terminal. It requires **PCOV or Xdebug**; the bundled XAMPP PHP has
neither, so PHPUnit will report "No code coverage driver available" until one is installed.
PHPUnit 10 no longer supports the phpdbg driver.

## Database for the integration tier

Tests marked `@group integration` that touch the database need the Kimai test database plus
the plugin's own tables. The plugin ships its migrations separately (`Migrations/approval.yaml`,
table `bundle_migration_approval`), so `kimai:reset:test` alone is not enough:

```
composer db-setup
```

which is equivalent to:

```
php ../../../bin/console kimai:reset:test --env=test --no-interaction
php ../../../bin/console kimai:bundle:approval:install --env=test --no-interaction
```

`bin/console` reads `<kimai>/.env`, not `phpunit.xml.dist`, so the test `DATABASE_URL` has to be
configured there (or exported into the environment) before running the setup. Afterwards the DAMA
extension wraps every test in a transaction and rolls it back, so the data stays clean.

## Writing tests

Directory and namespace follow PSR-4: `Tests/Toolbox/FormattingTest.php` is
`KimaiPlugin\ApprovalBundle\Tests\Toolbox\FormattingTest`. Nothing needs to be registered.

| Tier | Base class | Notes |
|------|-----------|-------|
| unit | `PHPUnit\Framework\TestCase` | no kernel, no database - prefer this |
| service | `Symfony\Bundle\FrameworkBundle\Test\KernelTestCase` | mark `@group integration` |
| controller / API | `App\Tests\Controller\AbstractControllerBaseTestCase`, `App\Tests\API\APIControllerBaseTestCase` | mark `@group integration` |

Anything that boots a kernel must be tagged `@group integration` so `composer tests-unit` stays
fast and database-free.

### Why a custom kernel

`src/Kernel.php` in Kimai core returns early for the `test` environment and skips plugin
discovery, so a stock kernel would boot without the ApprovalBundle. `ApprovalTestKernel`
re-registers the bundle and is selected through the `KERNEL_CLASS` environment variable in
`phpunit.xml.dist`. It also pins `getProjectDir()` to the Kimai root and uses its own cache
directory (`var/cache/approval-test`).
