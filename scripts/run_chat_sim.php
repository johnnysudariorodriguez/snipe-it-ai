<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Controllers\ChatController;

$user = User::first();
if (! $user) {
    echo "No user found in database. Cannot simulate authenticated chat.\n";
    exit(1);
}

$req = Request::create('/ai/chat', 'POST', ['message' => 'who are u']);
$req->setUserResolver(function () use ($user) { return $user; });

$controller = new ChatController();
$openai = $app->make(\App\Services\OpenAIChatService::class);
$rag = $app->make(\App\Services\RagService::class);

try {
    $resp = $controller->handle($req, $openai, $rag);
    $content = $resp->getContent();
    echo "Response JSON:\n";
    echo $content . "\n";
} catch (\Throwable $e) {
    echo "Error running simulated chat: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
