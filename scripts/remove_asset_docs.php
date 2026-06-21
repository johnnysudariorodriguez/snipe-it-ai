<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AiDocument;
use Illuminate\Support\Facades\Http;

$pythonBase = rtrim(config('app.python_api_url', env('PYTHON_API_URL', 'http://127.0.0.1:8001')), '/');

$docs = AiDocument::where('filename', 'like', '%asset-policy%')
    ->orWhere('filename', 'like', '%asset_policy%')
    ->get();

if ($docs->isEmpty()) {
    echo "No matching AiDocument rows found.\n";
    exit(0);
}

foreach ($docs as $d) {
    echo "Processing id={$d->id} filename={$d->filename} status={$d->status} external_id={$d->external_id}... ";

    try {
        if ($d->external_id) {
            $payload = ['file_id' => $d->external_id];
            $note = 'file_id';
        } else {
            // fall back to deleting by original filename (source metadata)
            $payload = ['file_name' => $d->filename];
            $note = 'file_name';
        }

        $res = Http::timeout(15)->post($pythonBase . '/delete-doc', $payload);
        if ($res->successful()) {
            echo "deleted-from-chroma(by={$note}) ";
        } else {
            echo "chroma-delete-failed(status=" . $res->status() . ") ";
        }
    } catch (\Throwable $e) {
        echo "chroma-delete-error: " . $e->getMessage() . " ";
    }

    // Mark DB row as Deleted and clear external_id/inserted_count to avoid reprocessing
    $d->status = 'Deleted';
    $d->external_id = null;
    $d->inserted_count = 0;
    $d->processed_at = null;
    $d->error_message = 'Removed from KB by admin script';
    $d->save();

    echo "marked-Deleted\n";
}

echo "done\n";
