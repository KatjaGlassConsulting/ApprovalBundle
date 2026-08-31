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

### DANGER: point the test environment at a test database first

`kimai:reset:test` runs `doctrine:schema:drop --force --full-database`. `bin/console` reads the
dotenv files, **not** `phpunit.xml.dist`, so without a test-specific override it resolves
`DATABASE_URL` from `<kimai>/.env` - the real installation - and destroys it.

Create `<kimai>/.env.test.local` (git-ignored, only read for `APP_ENV=test`):

```
DATABASE_URL=mysql://kimai2_test:kimai2_test@127.0.0.1:3306/kimai2_test?charset=utf8mb4&serverVersion=10.5.8-MariaDB
```

and create that database once:

```sql
CREATE DATABASE IF NOT EXISTS `kimai2_test`;
CREATE USER IF NOT EXISTS `kimai2_test`@`localhost` IDENTIFIED BY 'kimai2_test';
GRANT ALL ON `kimai2_test`.* TO `kimai2_test`@`localhost`;
```

Two guards enforce this and must not be removed:

* `Tests/guard-test-database.php` - first step of `composer db-setup`
* `Tests/bootstrap.php` - refuses to start the suite at all against a non-`_test` database

### What db-setup does

```
composer db-setup
```

1. verifies the target database name ends with `_test`
2. `kimai:reset:test` - drops the schema, runs all 71 core migrations, loads Kimai's
   deterministic test data (8 users incl. `john_user`, `tony_teamlead`, `anna_admin`,
   1 customer, 1 project, 1 activity, 1 team)
3. runs the 9 ApprovalBundle migrations, which create the 6 plugin tables and seed the four
   `kimai2_ext_approval_status` rows

Step 3 deliberately does **not** use `kimai:bundle:approval:install`: Kimai's Kernel skips plugin
discovery in the `test` environment, so that command is not registered there. The migrations are
executed directly via `Migrations/approval.yaml` instead.

The script is idempotent - re-run it whenever migrations change. Afterwards the DAMA extension
wraps every test in a transaction and rolls it back, so data stays clean between tests.

Do **not** use `kimai:reset:dev`: it generates large amounts of random Faker data, which makes
assertions non-deterministic. `kimai:reset:test` is the fixed, minimal set the core suite uses.

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
