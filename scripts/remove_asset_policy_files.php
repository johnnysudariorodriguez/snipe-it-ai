<?php
// Remove asset-policy files from storage/ai_docs, back them up, and remove embeddings via Python /delete-doc by file name.
// Run from project root: php scripts/remove_asset_policy_files.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

$disk = Storage::disk('local');
$all = $disk->files('ai_docs');
$targets = [];
foreach ($all as $p) {
    if (preg_match('/asset[-_]?policy/i', basename($p))) {
        $targets[] = $p;
    }
}

if (empty($targets)) {
    echo "no asset-policy files found in storage/ai_docs\n";
    exit(0);
}

$timestamp = date('Ymd_His');
$backupDir = "ai_docs_backups/asset_policy_files_backup_{$timestamp}";
if (! $disk->exists($backupDir)) {
    $disk->makeDirectory($backupDir);
}

$pythonUrl = rtrim(env('PYTHON_API_URL', 'http://127.0.0.1:8001'), '/') . '/delete-doc';

foreach ($targets as $path) {
    $filename = basename($path);
    echo "Processing file: {$path}\n";

    // backup
    try {
        $copyTo = $backupDir . '/' . $filename;
        $disk->copy($path, $copyTo);
        echo "  backed up to {$copyTo}\n";
    } catch (\Throwable $e) {
        echo "  backup failed: " . $e->getMessage() . "\n";
    }

    // call python delete by file_name
    try {
        $res = Http::timeout(15)->post($pythonUrl, ['file_name' => $filename]);
        if ($res->successful()) {
            $json = $res->json();
            $deleted = $json['deleted'] ?? 0;
            echo "  chroma delete: deleted={$deleted}\n";
        } else {
            echo "  chroma delete failed: status={$res->status()} body={$res->body()}\n";
        }
    } catch (\Throwable $e) {
        echo "  chroma delete exception: " . $e->getMessage() . "\n";
    }

    // delete file
    try {
        if ($disk->exists($path)) {
            $disk->delete($path);
            echo "  file deleted from disk\n";
        }
    } catch (\Throwable $e) {
        echo "  failed to delete file: " . $e->getMessage() . "\n";
    }
}

echo "Done. Backups are in: {$backupDir}\n";
