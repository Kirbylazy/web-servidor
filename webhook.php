<?php

$internalSecret = 'worker-to-server-2025-ok';

$received = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';
if (!hash_equals($internalSecret, $received)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$repoPath = '/mnt/m2/www';
chdir($repoPath);

$output = shell_exec('git pull origin main 2>&1');

echo "OK\n";
echo $output;

