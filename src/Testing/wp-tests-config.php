<?php

// phpcs:ignoreFile -- WordPress tests configuration ($table_prefix is a WordPress global)

/*
 * WordPress tests configuration, from Coretik\Testing\Bootstrap: loaded by the test suite, and by the process which
 * installs WordPress (that is why the settings go through an environment variable). No autoloader there:
 * the name of the variable is Coretik\Testing\Bootstrap::ENV.
 */

$coretikTestsConfig = json_decode((string)getenv('CORETIK_TESTS_CONFIG'), true);

if (!is_array($coretikTestsConfig) || !is_array($coretikTestsConfig['constants'] ?? null)) {
    echo 'Error: no tests configuration, run the tests through Coretik\Testing\Bootstrap::run().' . PHP_EOL;
    exit(1);
}

foreach ($coretikTestsConfig['constants'] as $name => $value) {
    defined($name) || define($name, $value);
}

$table_prefix = (string)($coretikTestsConfig['table_prefix'] ?? 'wptests_');

unset($coretikTestsConfig, $name, $value);
