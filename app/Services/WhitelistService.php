<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CheckinIpWhitelist;
use App\Models\Scopes\BranchScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class WhitelistService
{
    /**
     * How long an active-whitelist lookup is cached (1 hour).
     */
    private const TTL = 3600;

    /**
     * The active whitelist entries for a branch, cached. Consulted on every
     * teacher check-in (6.2); invalidated on any whitelist write.
     *
     * @return Collection<int, CheckinIpWhitelist>
     */
    public function activeFor(Branch $branch): Collection
    {
        return Cache::remember(
            $this->cacheKey($branch->id),
            self::TTL,
            fn (): Collection => CheckinIpWhitelist::query()
                ->withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->get()
        );
    }

    /**
     * List the calling branch's whitelist entries, paginated.
     */
    public function list(int $perPage): LengthAwarePaginator
    {
        return CheckinIpWhitelist::query()
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Create a whitelist entry and invalidate the branch cache.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CheckinIpWhitelist
    {
        $entry = CheckinIpWhitelist::create($data);

        $this->forget($entry->branch_id);

        return $entry;
    }

    /**
     * Update a whitelist entry and invalidate the branch cache.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(CheckinIpWhitelist $entry, array $data): CheckinIpWhitelist
    {
        $entry->update($data);

        $this->forget($entry->branch_id);

        return $entry;
    }

    /**
     * Delete a whitelist entry and invalidate the branch cache.
     */
    public function delete(CheckinIpWhitelist $entry): void
    {
        $branchId = $entry->branch_id;

        $entry->delete();

        $this->forget($branchId);
    }

    /**
     * Forget the cached active whitelist for a branch.
     */
    private function forget(int $branchId): void
    {
        Cache::forget($this->cacheKey($branchId));
    }

    /**
     * The cache key for a branch's active whitelist.
     */
    private function cacheKey(int $branchId): string
    {
        return "checkin.whitelist.active.{$branchId}";
    }
}
