<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AiDocument;

$docs = AiDocument::orderBy('id', 'desc')->take(20)->get();
if ($docs->isEmpty()) {
    echo "no ai_documents found\n";
    exit(0);
}

foreach ($docs as $d) {
    echo sprintf("%d | %s | status=%s | inserted=%s | external_id=%s | processed_at=%s\n", $d->id, $d->filename, $d->status, $d->inserted_count ?? 'null', $d->external_id ?? 'null', $d->processed_at ?? 'null');
}
