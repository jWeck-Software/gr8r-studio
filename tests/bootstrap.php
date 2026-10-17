<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Every test seeds the demo Files into a new Workspace's directory and is rolled back afterwards,
// which leaves the blobs behind: start each run with an empty test storage.
(new Symfony\Component\Filesystem\Filesystem())->remove(dirname(__DIR__).'/var/test-storage');
