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
$npmOutput = shell_exec('cd /mnt/m2/www/default/Zirpo/api && npm install --omit=dev 2>&1');
$restartOutput = shell_exec('pm2 restart zirpo-api 2>&1');

echo "OK\n";
echo $gitOutput;
echo $npmOutput;
echo $restartOutput;

