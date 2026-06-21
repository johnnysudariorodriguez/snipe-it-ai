<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AiDocument;
use App\Jobs\ProcessAiDocument;

$errors = AiDocument::where('status', 'Error')->get();
if ($errors->isEmpty()) {
    echo "No error documents to reprocess.\n";
    exit(0);
}

foreach ($errors as $doc) {
    echo "Re-dispatching doc id={$doc->id} filename={$doc->filename}\n";
    $doc->status = 'Queued';
    $doc->error_message = null;
    $doc->save();
    ProcessAiDocument::dispatch($doc->id);
}

echo "done\n";
