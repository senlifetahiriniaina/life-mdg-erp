<?php

declare(strict_types=1);

// Docker HEALTHCHECK probe for the "reverb" service (see docker-compose.prod.yml):
// verifies the Reverb WebSocket server is accepting connections on the port it
// binds to (--port=8080). Same bare TCP-connect approach as docker/health-check.php
// for php-fpm — a refused connection is exactly the failure mode this needs to
// catch (server crashed, still starting, etc.).
$connection = @fsockopen('127.0.0.1', 8080, $errno, $errstr, 3);

if ($connection === false) {
    fwrite(STDERR, "reverb health check failed: {$errstr} ({$errno})\n");
    exit(1);
}

fclose($connection);
exit(0);
