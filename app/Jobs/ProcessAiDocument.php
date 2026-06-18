<?php

namespace App\Jobs;

use App\Models\AiDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ProcessAiDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $docId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $docId)
    {
        $this->docId = $docId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $doc = AiDocument::find($this->docId);
        if (! $doc) return;

        $doc->status = 'Processing';
        $doc->save();

        $pythonUrl = rtrim(config('app.python_api_url', env('PYTHON_API_URL', 'http://127.0.0.1:8001')), '/') . '/add-doc';

        try {
            // Resolve the path using the configured filesystem disk. The
            // application uses a custom 'local' root (storage_path()) so
            // files may live in storage/ai_docs rather than storage/app/ai_docs.
            $disk = config('filesystems.default', env('PRIVATE_FILESYSTEM_DISK', 'local'));

            if (!Storage::disk($disk)->exists($doc->path)) {
                $doc->status = 'Error';
                $doc->error_message = 'Stored file not found on disk ' . $disk . ': ' . $doc->path;
                $doc->save();
                return;
            }

            $stream = Storage::disk($disk)->readStream($doc->path);
            if ($stream === false) {
                $doc->status = 'Error';
                $doc->error_message = 'Failed to open stored file for reading on disk ' . $disk . ': ' . $doc->path;
                $doc->save();
                return;
            }

            $response = Http::timeout(120)
                ->attach('file', $stream, $doc->original_name)
                ->post($pythonUrl);

            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($response->successful()) {
                $json = $response->json();
                $inserted = data_get($json, 'chunks', data_get($json, 'inserted', 0));
                $doc->status = 'Indexed';
                $doc->inserted_count = $inserted;
                $doc->external_id = data_get($json, 'file', null);
                $doc->processed_at = now();
                // clear any previous transient error message
                $doc->error_message = null;
                $doc->save();
            } else {
                $doc->status = 'Error';
                $doc->error_message = $response->body();
                $doc->save();
            }
        } catch (\Throwable $e) {
            $doc->status = 'Error';
            $doc->error_message = $e->getMessage();
            $doc->save();
            throw $e;
        }
    }
}
