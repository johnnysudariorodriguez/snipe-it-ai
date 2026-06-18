<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (!isset($argv[1])) {
    echo "Usage: php scripts/run_process_ai_doc_now.php <docId>\n";
    exit(1);
}

$id = (int)$argv[1];

$job = new \App\Jobs\ProcessAiDocument($id);

try {
    $job->handle();
    echo "Processed doc $id synchronously.\n";
} catch (Throwable $e) {
    echo "Job failed: " . $e->getMessage() . "\n";
}
