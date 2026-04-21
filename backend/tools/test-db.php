<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

try {
    db();
    echo '✅ DB connected';
} catch (Throwable $e) {
    echo 'DB connection failed: ' . $e->getMessage();
}
