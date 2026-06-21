<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

/** @var \App\Services\RagService $rag */
$rag = $app->make(\App\Services\RagService::class);
$res = $rag->answer('who are u', [], ['top_k' => 5]);
print_r($res);
