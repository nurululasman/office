<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = config('database.connections.pgsql');
$path = dirname(__DIR__).'/tmp/backups/document-template-step12-preflight.dump';
File::ensureDirectoryExists(dirname($path));

$process = new Process([
    'C:\Program Files\PostgreSQL\15\bin\pg_dump.exe',
    '--format=custom',
    '--no-owner',
    '--no-privileges',
    '--host='.(string) $connection['host'],
    '--port='.(string) $connection['port'],
    '--username='.(string) $connection['username'],
    '--file='.$path,
    (string) $connection['database'],
], null, ['PGPASSWORD' => (string) $connection['password']]);
$process->setTimeout(120);
$process->mustRun();

echo json_encode([
    'backup' => basename($path),
    'bytes' => File::size($path),
    'sha256' => hash_file('sha256', $path),
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
