<?php

namespace App\Services;

use App\Actions\CheckoutRequests\CreateCheckoutRequestAction;
use App\Exceptions\AssetNotRequestable;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class AiOperationsService
{
    /**
     * Lines kept in sync with `ops help` and embedded into Gemini/OpenAI system context.
     *
     * @return list<string>
     */
    public static function builtinCommandHelpLines(): array
    {
        return [
            'Snipe-IT built-in chat commands (user types these exactly in this chat box):',
            '- `ops help` — show this command list in the UI',
            '- `asset <tag-or-name>` — search assets (permission: assets.view); replies include links to asset pages',
            '- `user <email-or-username>` — find a user (users.view); includes link when allowed',
            '- `assets for <email-or-username>` — list assets assigned to that user (users.view + assets.view)',
            '- `asset count` — count assets visible to the user (assets.view)',
            '- `requestable` or `requestable <term>` — list/search requestable assets (assets.view.requestable)',
            '- `request <asset-tag>` or `request asset <tag>` — submit an asset checkout request (assets.view.requestable)',
        ];
    }

    /**
     * Extra instructions for cloud LLMs so they promote built-in commands when chat intent is unclear.
     */
    public static function llmCommandsInstructions(): string
    {
        $catalog = implode("\n", self::builtinCommandHelpLines());

        return <<<TXT

{$catalog}

When answering:
- If the user asks what they can do here, what commands exist, how to search assets/users, request hardware, or uses wording that does not match a built-in command, briefly explain and include the FULL list above (or tell them to type `ops help`).
- If they ask you to perform Snipe-IT data actions (lookup, request asset, etc.), tell them to use the matching built-in command phrase above — you cannot run those actions yourself; only those typed commands trigger them.
- Do not invent additional slash-commands or shortcuts beyond this list.

Informal or "unsupported" wording (very important):
- Many messages will NOT use exact command phrases. Your job is to guess the user's goal and map it to the commands above.
- Examples of intent → command: "find laptop for John" → suggest `user j` or `assets for john@…`; "borrow a laptop" / "need hardware" → suggest `requestable` or `requestable laptop` then `request <tag>`; "how many assets" → `asset count`; "look up tag ABC" → `asset ABC`; "who is user X" → `user X`.
- Reply structure (keep short): (1) One sentence restating what they probably want. (2) "Try this in chat:" with one or more concrete example lines using backticks they can copy (only from the list above). (3) Mention permissions only if relevant (e.g. requestable flows need assets.view.requestable).
- If nothing fits well, give the best 2–3 commands that might help and remind them to type `ops help` for the full list.
TXT;
    }

    /**
     * @return array{reply: string, links: array<int, array{label: string, url: string}>}|null null = fall through to LLM
     */
    public function handle(string $message, User $actor): ?array
    {
        $input = trim($message);
        if ($input === '') {
            return null;
        }

        $lower = Str::lower($input);

        // Natural language intent shortcuts
        if (preg_match('/\b(how many assets|how many assets do we have|total assets|number of assets|count of assets)\b/i', $input)) {
            return $this->assetCount($actor);
        }

        if (preg_match('/\b(what assets are currently available|available laptops|show available|which assets are available)\b/i', $input)) {
            // Map to requestable / available listing where appropriate
            return $this->searchRequestable($actor, '');
        }

        if (preg_match('/\bwho(?:\s+is|\s+has)?(?:\s+assigned)?(?:\s+to)?\s+(?:asset\s+)?([A-Za-z0-9\-\_]+)\b/i', $input, $m)) {
            return $this->findAsset($actor, trim($m[1]));
        }

        if (preg_match('/\b(find|show|list)\s+(?:.*\b(dell|hp|lenovo|macbook|asus|acer)\b.*)/i', $input, $m)) {
            // Vendor/manufacturer search — forward the full phrase to asset finder
            $term = trim(str_replace($m[1], '', $input));
            return $this->findAsset($actor, $term ?: $m[2]);
        }

        if (in_array($lower, ['ops help', 'help ops', 'operations help', '/ops'], true)) {
            return $this->operationsHelp();
        }

        if (preg_match('/^(?:request\s+asset|request)\s+(.+)$/i', $input, $matches)) {
            return $this->requestAsset($actor, trim($matches[1]));
        }

        if (preg_match('/^(?:search\s+requestable|list\s+requestable|requestable)(?:\s+(.+))?$/i', $input, $matches)) {
            return $this->searchRequestable($actor, trim((string) ($matches[1] ?? '')));
        }

        if (preg_match('/^(?:count\s+assets|asset\s+count)$/i', $input)) {
            return $this->assetCount($actor);
        }

        if (preg_match('/^(?:find\s+asset|asset)\s+(.+)$/i', $input, $matches)) {
            return $this->findAsset($actor, trim($matches[1]));
        }

        if (preg_match('/^(?:find\s+user|user)\s+(.+)$/i', $input, $matches)) {
            return $this->findUser($actor, trim($matches[1]));
        }

        if (preg_match('/^(?:assets\s+(?:for|of)|list\s+assets\s+(?:for|of))\s+(.+)$/i', $input, $matches)) {
            return $this->assetsForUser($actor, trim($matches[1]));
        }

        return null;
    }

    /**
     * @param array<int, array{label: string, url: string}> $links
     * @return array{reply: string, links: array<int, array{label: string, url: string}>}
     */
    protected function formatReply(string $reply, array $links = []): array
    {
        return ['reply' => $reply, 'links' => array_values($links)];
    }

    protected function absoluteRoute(string $name, mixed $parameters = []): string
    {
        return url(route($name, $parameters));
    }

    /**
     * @return array{reply: string, links: array<int, array{label: string, url: string}>}|null
     */
    protected function denyUnless(User $actor, string $permission): ?array
    {
        if (! $actor->hasAccess($permission)) {
            return $this->formatReply('You do not have permission for this action (`'.$permission.'`).');
        }

        return null;
    }

    /**
     * @return array{reply: string, links: array<int, array{label: string, url: string}>}
     */
    protected function operationsHelp(): array
    {
        return $this->formatReply(implode("\n", array_merge(self::builtinCommandHelpLines(), [
            '',
            'Examples:',
            '- `requestable laptop`',
            '- `request LAP-1023`',
            '- `asset LAP-1023`',
            '- `user jsmith@company.com`',
        ])), [
            ['label' => 'Requestable items (UI)', 'url' => $this->absoluteRoute('requestable-assets')],
        ]);
    }

    /**
     * @return array{reply: string, links: array<int, array{label: string, url: string}>}
     */
    protected function assetCount(User $actor): array
    {
        if ($deny = $this->denyUnless($actor, 'assets.view')) {
            return $deny;
        }

        $count = Asset::query()->count();

        return $this->formatReply(
            'Assets visible to your account (scoped by Snipe-IT permissions): '.$count
        );
    }

    /**
     * @return array{reply: string, links: array<int, array{label: string, url: string}>}
     */
    protected function searchRequestable(User $actor, string $term): array
    {
        if ($deny = $this->denyUnless($actor, 'assets.view.requestable')) {
            return $deny;
        }

        $query = Asset::query()
            ->Hardware()
            ->RequestableAssets()
            ->select(['id', 'name', 'asset_tag', 'serial', 'model_id', 'status_id'])
            ->with(['model:id,name', 'assetstatus:id,name']);

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('asset_tag', 'like', '%'.$term.'%')
                    ->orWhere('name', 'like', '%'.$term.'%')
                    ->orWhere('serial', 'like', '%'.$term.'%');
            });
        }

        $assets = $query->orderBy('asset_tag')->limit(15)->get();

        if ($assets->isEmpty()) {
            return $this->formatReply(
                $term === ''
                    ? 'No requestable assets are available right now.'
                    : "No requestable assets matched `{$term}`.",
                [['label' => 'Open requestable catalog', 'url' => $this->absoluteRoute('requestable-assets')]]
            );
        }

        $lines = ["Requestable assets ({$assets->count()} shown):"];
        $links = [['label' => 'Browse all requestable items', 'url' => $this->absoluteRoute('requestable-assets')]];
        foreach ($assets as $asset) {
            $status = $asset->assetstatus?->name ?? 'Unknown';
            $model = $asset->model?->name ?? 'No model';
            $lines[] = "- `{$asset->asset_tag}` | {$asset->name} | {$model} | {$status}";
            $links[] = [
                'label' => 'Asset '.$asset->asset_tag,
                'url' => $this->absoluteRoute('hardware.show', $asset->id),
            ];
        }

        return $this->formatReply(implode("\n", $lines), $links);
    }

    /**
     * @return array{reply: string, links: array<int, array{label: string, url: string}>}
     */
    protected function requestAsset(User $actor, string $search): array
    {
        if ($deny = $this->denyUnless($actor, 'assets.view.requestable')) {
            return $deny;
        }

        if ($search === '') {
            return $this->formatReply('Provide an asset tag or name. Example: `request LAP-1023`.', [
                ['label' => 'Requestable items', 'url' => $this->absoluteRoute('requestable-assets')],
            ]);
        }

        $candidates = Asset::query()
            ->Hardware()
            ->RequestableAssets()
            ->where(function ($q) use ($search) {
                $q->where('asset_tag', $search)
                    ->orWhere('asset_tag', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            })
            ->orderByRaw('CASE WHEN asset_tag = ? THEN 0 ELSE 1 END', [$search])
            ->limit(10)
            ->get();

        if ($candidates->isEmpty()) {
            return $this->formatReply(
                "No requestable asset matched `{$search}` (confirm status is deployable/pending and asset is marked requestable).",
                [['label' => 'Open requestable catalog', 'url' => $this->absoluteRoute('requestable-assets')]]
            );
        }

        if ($candidates->count() > 1) {
            $lines = ['Multiple requestable assets matched — pick one below or narrow your tag, then run `request …` again:'];
            $links = [];
            foreach ($candidates as $asset) {
                $lines[] = "- `{$asset->asset_tag}` — {$asset->name}";
                $links[] = [
                    'label' => 'Open '.$asset->asset_tag,
                    'url' => $this->absoluteRoute('hardware.show', $asset->id),
                ];
            }

            return $this->formatReply(implode("\n", $lines), $links);
        }

        $asset = $candidates->first();

        try {
            CreateCheckoutRequestAction::run($asset, $actor);
        } catch (AssetNotRequestable) {
            return $this->formatReply(
                'That asset is not requestable anymore (status may have changed).',
                [['label' => 'View asset', 'url' => $this->absoluteRoute('hardware.show', $asset->id)]]
            );
        } catch (AuthorizationException) {
            return $this->formatReply(
                'You are not allowed to request this asset (company / visibility).',
                [['label' => 'View asset', 'url' => $this->absoluteRoute('hardware.show', $asset->id)]]
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->formatReply(
                'Could not complete the request. '.$e->getMessage(),
                [['label' => 'View asset', 'url' => $this->absoluteRoute('hardware.show', $asset->id)]]
            );
        }

        return $this->formatReply(
            "Request submitted for `{$asset->asset_tag}` ({$asset->name}).",
            [
                ['label' => 'Open asset', 'url' => $this->absoluteRoute('hardware.show', $asset->id)],
                ['label' => 'My requested assets', 'url' => $this->absoluteRoute('account.requested')],
                ['label' => 'Requestable catalog', 'url' => $this->absoluteRoute('requestable-assets')],
            ]
        );
    }

    /**
     * @return array{reply: string, links: array<int, array{label: string, url: string}>}
     */
    protected function findAsset(User $actor, string $search): array
    {
        if ($deny = $this->denyUnless($actor, 'assets.view')) {
            return $deny;
        }

        if ($search === '') {
            return $this->formatReply('Provide an asset tag or name. Example: `asset LAP-1023`.');
        }

        $assets = Asset::query()
            ->select(['id', 'name', 'asset_tag', 'assigned_type', 'assigned_to', 'status_id', 'model_id'])
            ->with(['model:id,name', 'assetstatus:id,name', 'assignedTo'])
            ->where(function ($query) use ($search) {
                $query->where('asset_tag', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('serial', 'like', '%'.$search.'%');
            })
            ->limit(5)
            ->get();

        if ($assets->isEmpty()) {
            return $this->formatReply("No assets found for `{$search}`.");
        }

        $lines = ["Found {$assets->count()} asset(s):"];
        $links = [];
        foreach ($assets as $asset) {
            $status = $asset->assetstatus?->name ?? 'Unknown';
            $model = $asset->model?->name ?? 'No model';
            $assigned = $this->formatAssetAssignee($asset);
            $lines[] = "- `{$asset->asset_tag}` | {$asset->name} | {$model} | Status: {$status} | Assigned: {$assigned}";
            $links[] = [
                'label' => 'Open '.$asset->asset_tag,
                'url' => $this->absoluteRoute('hardware.show', $asset->id),
            ];
        }

        return $this->formatReply(implode("\n", $lines), $links);
    }

    /**
     * @return array{reply: string, links: array<int, array{label: string, url: string}>}
     */
    protected function findUser(User $actor, string $search): array
    {
        if ($deny = $this->denyUnless($actor, 'users.view')) {
            return $deny;
        }

        if ($search === '') {
            return $this->formatReply('Provide a username or email. Example: `user jsmith@company.com`.');
        }

        $user = User::query()
            ->select(['id', 'first_name', 'last_name', 'username', 'email'])
            ->where(function ($query) use ($search) {
                $query->where('username', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%');
            })
            ->first();

        if (! $user) {
            return $this->formatReply("No user found for `{$search}`.");
        }

        $assetsCount = 0;
        if ($actor->hasAccess('assets.view')) {
            $assetsCount = $user->assets()->count();
        }

        $lines = [
            'User found:',
            "- Name: {$user->full_name}",
            "- Username: {$user->username}",
            "- Email: ".($user->email ?: 'none'),
        ];
        if ($actor->hasAccess('assets.view')) {
            $lines[] = "- Assigned assets: {$assetsCount}";
        } else {
            $lines[] = '- Assigned assets: (hidden — missing `assets.view`)';
        }

        $links = [];
        if ($actor->hasAccess('users.view')) {
            $links[] = [
                'label' => 'User profile (Snipe-IT)',
                'url' => $this->absoluteRoute('users.show', $user->id),
            ];
        }

        return $this->formatReply(implode("\n", $lines), $links);
    }

    /**
     * @return array{reply: string, links: array<int, array{label: string, url: string}>}
     */
    protected function assetsForUser(User $actor, string $search): array
    {
        if ($deny = $this->denyUnless($actor, 'users.view')) {
            return $deny;
        }
        if ($deny = $this->denyUnless($actor, 'assets.view')) {
            return $deny;
        }

        if ($search === '') {
            return $this->formatReply('Provide a username or email. Example: `assets for jsmith`.');
        }

        $user = User::query()
            ->where(function ($query) use ($search) {
                $query->where('username', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            })
            ->first();

        if (! $user) {
            return $this->formatReply("No user found for `{$search}`.");
        }

        $assets = $user->assets()
            ->select(['id', 'asset_tag', 'name', 'status_id', 'model_id'])
            ->with(['assetstatus:id,name', 'model:id,name'])
            ->limit(10)
            ->get();

        $links = [
            ['label' => 'User '.$user->username, 'url' => $this->absoluteRoute('users.show', $user->id)],
        ];

        if ($assets->isEmpty()) {
            return $this->formatReply("{$user->full_name} has no assigned assets.", $links);
        }

        $lines = ["Assets assigned to {$user->full_name} ({$assets->count()} shown):"];
        foreach ($assets as $asset) {
            $status = $asset->assetstatus?->name ?? 'Unknown';
            $model = $asset->model?->name ?? 'No model';
            $lines[] = "- `{$asset->asset_tag}` | {$asset->name} | {$model} | Status: {$status}";
            $links[] = [
                'label' => 'Asset '.$asset->asset_tag,
                'url' => $this->absoluteRoute('hardware.show', $asset->id),
            ];
        }

        return $this->formatReply(implode("\n", $lines), $links);
    }

    protected function formatAssetAssignee(Asset $asset): string
    {
        if (! $asset->assigned_type || ! $asset->assigned_to) {
            return 'Unassigned';
        }

        $assigned = $asset->assignedTo;
        if (! $assigned) {
            return class_basename($asset->assigned_type).' #'.$asset->assigned_to;
        }

        if (method_exists($assigned, 'getAttribute')) {
            $name = $assigned->getAttribute('name')
                ?? $assigned->getAttribute('full_name')
                ?? $assigned->getAttribute('username')
                ?? $assigned->getAttribute('email');
            if ($name) {
                return (string) $name;
            }
        }

        return class_basename($asset->assigned_type).' #'.$asset->assigned_to;
    }
}
