<?php

/*
 * Front controller of the browser tests (npm run test:e2e): the same app as public/index.php, in the
 * dev environment, but on the tests' own database, file storage and link base from .env.e2e.local.
 * Variables set here before the kernel boots win over .env and .env.local (Dotenv never overrides
 * what is already set), so a test run never touches the developer's database.
 *
 * The runtime requires this file a second time to get the app; only then is the autoloader there,
 * and the runtime has not read any .env file yet.
 */

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__, 2).'/vendor/autoload_runtime.php';

$e2eEnv = dirname(__DIR__, 2).'/.env.e2e.local';
if (!is_file($e2eEnv)) {
    throw new RuntimeException('Missing .env.e2e.local: the browser tests need their own database (see README).');
}
foreach ((new Dotenv())->parse(file_get_contents($e2eEnv), $e2eEnv) as $name => $value) {
    $_SERVER[$name] = $_ENV[$name] = $value;
}

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
