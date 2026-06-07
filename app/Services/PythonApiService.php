<?php
/* GENERATED: snipe-it-ai RAG - Copilot */
namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Http\UploadedFile;
use Exception;

class PythonApiService
{
    protected Client $client;
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('app.python_api_url', env('PYTHON_API_URL', 'http://127.0.0.1:8000')), '/');
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
        ]);
    }

    /**
     * Upload a document to the Python vector service
     * @param UploadedFile $file
     * @return array
     * @throws Exception
     */
    public function uploadDocument(UploadedFile $file): array
    {
        try {
            $response = $this->client->request('POST', '/add-doc', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen($file->getRealPath(), 'r'),
                        'filename' => $file->getClientOriginalName(),
                    ],
                ],
            ]);

            $ct = $response->getHeaderLine('Content-Type');
            $body = (string)$response->getBody();

            if (stripos($ct, 'application/json') === false) {
                // Non-JSON response often indicates an auth redirect or server HTML error
                throw new \Exception('Non-JSON response from Python service. Response snippet: ' . substr($body, 0, 500));
            }

            $decoded = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON from Python service: ' . json_last_error_msg());
            }

            return $decoded ?? [];
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Semantic query against Python service
     * @param string $query
     * @param int $top_k
     * @return array
     * @throws Exception
     */
    public function query(string $query, int $top_k = 5): array
    {
        try {
            $resp = $this->client->post('/query', [
                'json' => [
                    'query' => $query,
                    'top_k' => $top_k,
                ],
            ]);

            $ct = $resp->getHeaderLine('Content-Type');
            $body = (string)$resp->getBody();
            if (stripos($ct, 'application/json') === false) {
                throw new \Exception('Non-JSON response from Python service on query: ' . substr($body, 0, 500));
            }
            $decoded = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON from Python service: ' . json_last_error_msg());
            }
            return $decoded ?? [];
        } catch (Exception $e) {
            throw $e;
        }
    }
}
