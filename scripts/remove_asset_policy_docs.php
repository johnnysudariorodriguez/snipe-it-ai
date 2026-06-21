<?php
// Remove asset-policy documents from ChromaDB, delete files, and remove DB rows.
// Run from project root: php scripts/remove_asset_policy_docs.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Models\AiDocument;

$disk = Storage::disk('local');

$docs = AiDocument::where('filename', 'like', '%asset-policy%')
    ->orWhere('filename', 'like', '%asset_policy%')
    ->get();

if ($docs->isEmpty()) {
    echo "no asset-policy documents found\n";
    exit(0);
}

$timestamp = date('Ymd_His');
$backupDir = "ai_docs_backups/asset_policy_backup_{$timestamp}";
if (! $disk->exists($backupDir)) {
    $disk->makeDirectory($backupDir);
}

$backupList = [];
$pythonUrl = rtrim(env('PYTHON_API_URL', 'http://127.0.0.1:8001'), '/') . '/delete-doc';

foreach ($docs as $doc) {
    echo "Processing id={$doc->id} filename={$doc->filename} external_id={$doc->external_id}\n";

    // Backup file if present
    if ($doc->path && $disk->exists($doc->path)) {
        $backupPath = $backupDir . '/' . basename($doc->path);
        try {
            $disk->copy($doc->path, $backupPath);
            echo "  backed up file to {$backupPath}\n";
        } catch (\Throwable $e) {
            echo "  failed to back up file: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  file not found on disk: {$doc->path}\n";
    }

    // Backup row data
    $backupList[] = $doc->toArray();

    // Call Python service to delete embeddings
    try {
        $payload = [];
        if (! empty($doc->external_id)) {
            $payload['file_id'] = $doc->external_id;
        } else {
            $payload['file_name'] = $doc->filename;
        }

        $res = Http::timeout(15)->post($pythonUrl, $payload);
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

    // Delete file from disk
    try {
        if ($doc->path && $disk->exists($doc->path)) {
            $disk->delete($doc->path);
            echo "  deleted file from disk\n";
        }
    } catch (\Throwable $e) {
        echo "  failed to delete file from disk: " . $e->getMessage() . "\n";
    }

    // Delete DB row (permanent)
    try {
        $doc->delete();
        echo "  deleted DB row\n";
    } catch (\Throwable $e) {
        echo "  failed to delete DB row: " . $e->getMessage() . "\n";
    }
}

$backupDirScripts = __DIR__ . '/backups';
if (! file_exists($backupDirScripts)) mkdir($backupDirScripts, 0755, true);
$backupFile = $backupDirScripts . "/asset_policy_backup_{$timestamp}.json";
file_put_contents($backupFile, json_encode($backupList, JSON_PRETTY_PRINT));
echo "Wrote backup JSON: {$backupFile}\n";
echo "Done\n";
