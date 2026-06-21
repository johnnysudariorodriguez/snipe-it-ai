<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AiDocument;

$docs = AiDocument::where('filename', 'like', '%asset-policy%')
    ->orWhere('filename', 'like', '%asset_policy%')
    ->get();

if ($docs->isEmpty()) {
    echo "No matching AiDocument rows found.\n";
    exit(0);
}

foreach ($docs as $d) {
    echo "id={$d->id} filename={$d->filename} path={$d->path} status={$d->status} external_id={$d->external_id}\n";
}
