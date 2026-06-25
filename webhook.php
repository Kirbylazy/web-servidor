<?php

$internalSecret = 'worker-to-server-2025-ok';

$received = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';
if (!hash_equals($internalSecret, $received)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$repoPath = '/mnt/m2/www/default';
chdir($repoPath);

$gitOutput = shell_exec('git pull origin main 2>&1');

// Zirpo
$npmOutput = shell_exec('cd /mnt/m2/www/default/Zirpo/api && npm install --omit=dev 2>&1');
$restartOutput = shell_exec('pm2 restart zirpo-api 2>&1');

// Escalada (Laravel)
$composerOutput = shell_exec('cd /mnt/m2/www/default/Escalada && composer install --no-dev --optimize-autoloader --no-interaction 2>&1');
$migrateOutput = shell_exec('cd /mnt/m2/www/default/Escalada && php artisan migrate --force 2>&1');
$cacheOutput = shell_exec('cd /mnt/m2/www/default/Escalada && php artisan config:cache && php artisan route:cache && php artisan view:cache 2>&1');

echo "OK\n";
echo "=== Git ===\n$gitOutput\n";
echo "=== Zirpo NPM ===\n$npmOutput\n";
echo "=== Zirpo PM2 ===\n$restartOutput\n";
echo "=== Escalada Composer ===\n$composerOutput\n";
echo "=== Escalada Migrate ===\n$migrateOutput\n";
echo "=== Escalada Cache ===\n$cacheOutput\n";

