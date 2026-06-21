<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('failed_jobs')->orderBy('failed_at', 'desc')->take(10)->get();
if ($rows->isEmpty()) {
    echo "No failed jobs in DB.\n";
    exit(0);
}

foreach ($rows as $r) {
    echo "id={$r->id} connection={$r->connection} queue={$r->queue} payload_len=" . strlen($r->payload) . " exception=" . substr($r->exception,0,200) . "\n";
}
