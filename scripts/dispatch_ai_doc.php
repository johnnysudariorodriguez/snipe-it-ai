<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AiDocument;
use App\Jobs\ProcessAiDocument;

$id = $argv[1] ?? null;
if (! $id) {
    echo "Usage: php scripts/dispatch_ai_doc.php <docId>\n";
    exit(1);
}

$doc = AiDocument::find($id);
if (! $doc) {
    echo "Document not found: $id\n";
    exit(1);
}

$doc->status = 'Queued';
$doc->error_message = null;
$doc->save();

// Ensure the job is dispatched onto the configured queue connection (database)
ProcessAiDocument::dispatch($id)->onConnection(config('queue.default', 'database'));

echo "Dispatched ProcessAiDocument for doc $id onto connection " . config('queue.default') . "\n";
