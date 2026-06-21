<?php
// Reconcile files under storage/ai_docs into AiDocument rows and dispatch indexing jobs.
// Run this from project root: php scripts/reconcile_ai_docs.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;
use App\Models\AiDocument;
use App\Jobs\ProcessAiDocument;

$disk = Storage::disk('local');
$files = $disk->files('ai_docs');
if (empty($files)) {
    echo "no files found\n";
    exit(0);
}

foreach ($files as $path) {
    $filename = basename($path);
    if (AiDocument::where('filename', $filename)->exists()) {
        echo "exists: {$filename}\n";
        continue;
    }

    try {
        $size = null;
        try { $size = $disk->size($path); } catch (\Throwable $e) { }

        $doc = AiDocument::create([
            'original_name' => $filename,
            'filename' => $filename,
            'path' => $path,
            'size' => $size,
            'status' => 'Queued',
            'created_by' => 1,
        ]);

        ProcessAiDocument::dispatch($doc->id);
        echo "created: {$filename}\n";
    } catch (\Throwable $e) {
        echo "failed: {$filename} -> " . $e->getMessage() . "\n";
    }
}

echo "done\n";
