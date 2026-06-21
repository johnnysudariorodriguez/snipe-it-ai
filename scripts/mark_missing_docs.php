<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AiDocument;
use Illuminate\Support\Facades\Storage;

$missing = AiDocument::where('status', 'Error')->get();
if ($missing->isEmpty()) {
    echo "No error docs found\n";
    exit(0);
}

foreach ($missing as $d) {
    $disk = config('filesystems.default', env('PRIVATE_FILESYSTEM_DISK', 'local'));
    if (! Storage::disk($disk)->exists($d->path)) {
        $d->status = 'Missing';
        $d->error_message = 'File missing on disk; marked as Missing';
        $d->save();
        echo "Marked missing: id={$d->id} filename={$d->filename}\n";
    }
}

echo "done\n";
