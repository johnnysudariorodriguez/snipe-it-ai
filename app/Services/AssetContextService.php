<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AssetContextService
{
    /**
     * Build a short, sanitized asset context for LLM prompts.
     * Returns array with key 'lines' => array<string>.
     */
    public function buildContext(string $message, ?User $user = null, int $limit = 10): array
    {
        $lines = [];

        $term = trim($message);
        $lower = Str::lower($message);

        try {
            $query = Asset::query()->with(['model:id,name', 'assetstatus:id,name', 'assignedTo']);

            // If user asked for "available" assets, prefer deployable + unassigned
            if (Str::contains($lower, ['available', 'currently available', 'show available', 'available laptops', 'available devices'])) {
                $query->whereNull('assigned_to')
                    ->whereHas('assetstatus', function ($q) {
                        $q->where('deployable', 1)->where('archived', 0);
                    });
            }

            // Build generic search across common fields + model
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like) {
                $q->where('asset_tag', 'like', $like)
                  ->orWhere('name', 'like', $like)
                  ->orWhere('serial', 'like', $like)
                  ->orWhereHas('model', function ($mq) use ($like) {
                      $mq->where('name', 'like', $like);
                  });
            });

            $assets = $query->limit($limit)->get();

            foreach ($assets as $a) {
                $assigned = 'Unassigned';
                try {
                    $assignee = $a->assignedTo;
                    if ($assignee) {
                        $assigned = $assignee->full_name ?? $assignee->name ?? $assignee->username ?? $assignee->email ?? 'Assigned';
                    }
                } catch (\Throwable $e) {
                    $assigned = 'Assigned';
                }

                $status = $a->assetstatus?->name ?? 'Unknown';
                $model = $a->model?->name ?? 'No model';
                $availableFlag = $a->availableForCheckout() ? 'Available' : 'Not available';

                $lines[] = sprintf('- Asset Tag: %s | Name: %s | Model: %s | Status: %s | Assigned To: %s | %s',
                    $a->asset_tag ?? '-',
                    Str::limit($a->name ?? '-', 120),
                    $model,
                    $status,
                    $assigned,
                    $availableFlag
                );
            }
        } catch (\Throwable $e) {
            Log::warning('AssetContextService failure: '.$e->getMessage(), ['message' => $message, 'exception' => $e]);
        }

        return ['lines' => $lines];
    }
}
