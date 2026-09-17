<?php

declare(strict_types=1);

/**
 * PHPUnit's bootstrap, ahead of the autoloader and therefore ahead of anything
 * Laravel does.
 *
 * Its one job is APP_KEY. The framework needs one before it boots, and there is
 * nowhere good to keep a real one:
 *
 * - Committed in .env.testing, which is where it started, a valid Laravel key
 *   is indistinguishable from a production key to anything that reads the file,
 *   and the argument that it only protects test data holds only until somebody
 *   copies the file to .env. GitGuardian flagged it and was right to.
 * - Generated into .env.testing on the first run, which is what replaced it,
 *   leaves every clean clone with a modified tracked file holding a real key.
 *   Git tracks modifications whatever .gitignore says, so `git commit -a` puts
 *   it straight back - the same accident, one step further away.
 *
 * So it is never written down. A key is generated per run, into this process's
 * environment only, and dies with the process. Nothing to commit, nothing to
 * leak, and no setup step: `vendor/bin/pest` works on a fresh clone.
 *
 * An APP_KEY already in the environment is left alone, so CI or a developer can
 * pin one when they want to (debugging something encryption-related, say).
 */
$existing = getenv('APP_KEY');

if ($existing === false || $existing === '') {
    $existing = $_ENV['APP_KEY'] ?? $_SERVER['APP_KEY'] ?? '';
}

if ($existing === '') {
    $key = 'base64:' . base64_encode(random_bytes(32));

    // All three, because Laravel's env() reads whichever the adapter offers
    // rather than one canonical source.
    putenv("APP_KEY={$key}");
    $_ENV['APP_KEY'] = $key;
    $_SERVER['APP_KEY'] = $key;
}

require __DIR__ . '/../vendor/autoload.php';
