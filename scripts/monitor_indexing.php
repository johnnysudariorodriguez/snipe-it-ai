<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AiDocument;
use App\Jobs\ProcessAiDocument;

$start = time();
$timeout = 300; // seconds

while (true) {
    $queued = AiDocument::where('status', 'Queued')->count();
    $error = AiDocument::where('status', 'Error')->count();
    $indexed = AiDocument::where('status', 'Indexed')->count();

    echo sprintf("%s queued=%d error=%d indexed=%d\n", date('Y-m-d H:i:s'), $queued, $error, $indexed);

    if ($queued === 0 && $error === 0) {
        echo "All documents indexed or no pending errors.\n";
        exit(0);
    }

    // Re-dispatch small batches of error docs if found
    if ($error > 0) {
        $errors = AiDocument::where('status', 'Error')->limit(10)->get();
        foreach ($errors as $doc) {
            echo "Re-dispatching doc id={$doc->id} filename={$doc->filename}\n";
            $doc->status = 'Queued';
            $doc->error_message = null;
            $doc->save();
            ProcessAiDocument::dispatch($doc->id);
        }
    }

    if (time() - $start > $timeout) {
        echo "Timeout waiting for indexing to complete.\n";
        exit(2);
    }

    sleep(5);
}
