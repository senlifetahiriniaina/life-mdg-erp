<?php

declare(strict_types=1);

// Docker HEALTHCHECK probe (see Dockerfile's HEALTHCHECK directive): verifies
// php-fpm is accepting connections on the port the image exposes (EXPOSE
// 9000). A bare TCP connect is enough — FPM refusing connections is exactly
// the failure mode this needs to catch (worker pool exhausted, master
// crashed, etc.).
$connection = @fsockopen('127.0.0.1', 9000, $errno, $errstr, 3);

if ($connection === false) {
    fwrite(STDERR, "php-fpm health check failed: {$errstr} ({$errno})\n");
    exit(1);
}

fclose($connection);
exit(0);
