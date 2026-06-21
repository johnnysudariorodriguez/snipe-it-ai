<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AiDocument;

$errors = AiDocument::where('status', 'Error')->get();
if ($errors->isEmpty()) {
    echo "No error docs.\n";
    exit(0);
}

foreach ($errors as $d) {
    echo sprintf("id=%d filename=%s path=%s error=%s\n", $d->id, $d->filename, $d->path, substr($d->error_message ?? 'null', 0, 200));
}
