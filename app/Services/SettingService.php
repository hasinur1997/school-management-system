<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Settings\SettingRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Database-backed, cached source of truth for tunable settings. Global values
 * live under a single cache key; each branch's overrides under their own. The
 * cache is dropped on every write. Values are stored JSON-encoded so types
 * round-trip exactly.
 */
class SettingService
{
    private const CACHE_GLOBAL = 'settings.global';

    /**
     * The effective value of a key, or null when unset. Branch-scoped keys are
     * read from the given branch; global keys ignore the branch argument.
     */
    public function get(string $key, ?int $branchId = null): mixed
    {
        if (! SettingRegistry::has($key)) {
            return null;
        }

        $values = SettingRegistry::isGlobal($key)
            ? $this->globalValues()
            : $this->branchValues($branchId);

        return $values[$key] ?? null;
    }

    /**
     * All stored global values, keyed by setting key.
     *
     * @return array<string, mixed>
     */
    public function globalValues(): array
    {
        return Cache::rememberForever(self::CACHE_GLOBAL, fn (): array => $this->load(null));
    }

    /**
     * All stored values for a branch, keyed by setting key.
     *
     * @return array<string, mixed>
     */
    public function branchValues(?int $branchId): array
    {
        if ($branchId === null) {
            return [];
        }

        return Cache::rememberForever($this->branchCacheKey($branchId), fn (): array => $this->load($branchId));
    }

    /**
     * Bulk upsert validated settings. Each key is routed to its scope: global
     * keys to a NULL branch_id row, branch keys to the given branch. The cache
     * for the affected scopes is dropped afterward.
     *
     * @param  array<string, mixed>  $settings
     */
    public function upsert(array $settings, ?int $branchId): void
    {
        DB::transaction(function () use ($settings, $branchId): void {
            foreach ($settings as $key => $value) {
                $scopeBranchId = SettingRegistry::isGlobal($key) ? null : $branchId;

                Setting::query()->updateOrCreate(
                    ['branch_id' => $scopeBranchId, 'key' => $key],
                    ['value' => json_encode($value)],
                );
            }
        });

        $this->forget($branchId);
    }

    /**
     * The effective settings for the GET / PUT response: global values (secrets
     * masked as `{ "is_set": bool }`) plus the branch's values.
     *
     * @return array{global: array<string, mixed>, branch: array<string, mixed>}
     */
    public function effective(?int $branchId): array
    {
        $global = [];
        foreach (SettingRegistry::globalKeys() as $key) {
            $value = $this->get($key, $branchId);

            $global[$key] = SettingRegistry::isSecret($key)
                ? ['is_set' => $value !== null]
                : $value;
        }

        $branch = [];
        foreach (SettingRegistry::branchKeys() as $key) {
            $branch[$key] = $this->get($key, $branchId);
        }

        return ['global' => $global, 'branch' => $branch];
    }

    /**
     * The public, unauthenticated subset for the admission page: school name,
     * logo URL, and active branches each with their open (active) classes.
     * Secrets are never part of this payload.
     *
     * @return array{school_name: mixed, school_logo: mixed, branches: array<int, array<string, mixed>>}
     */
    public function publicSettings(): array
    {
        $classesByBranch = SchoolClass::query()
            ->where('is_active', true)
            ->orderBy('numeric_level')
            ->get(['id', 'branch_id', 'name'])
            ->groupBy('branch_id');

        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'classes' => ($classesByBranch->get($branch->id) ?? collect())
                    ->map(fn (SchoolClass $class): array => ['id' => $class->id, 'name' => $class->name])
                    ->values()
                    ->all(),
            ])
            ->all();

        return [
            'school_name' => $this->get('school_name'),
            'school_logo' => $this->get('school_logo'),
            'branches' => $branches,
        ];
    }

    /**
     * Drop the cache for the global scope and, when given, a branch scope.
     */
    public function forget(?int $branchId): void
    {
        Cache::forget(self::CACHE_GLOBAL);

        if ($branchId !== null) {
            Cache::forget($this->branchCacheKey($branchId));
        }
    }

    /**
     * Load and decode a scope's rows into a key => value map.
     *
     * @return array<string, mixed>
     */
    private function load(?int $branchId): array
    {
        return Setting::query()
            ->when(
                $branchId === null,
                fn ($query) => $query->whereNull('branch_id'),
                fn ($query) => $query->where('branch_id', $branchId),
            )
            ->pluck('value', 'key')
            ->map(fn (?string $value): mixed => $value === null ? null : json_decode($value, true))
            ->all();
    }

    private function branchCacheKey(int $branchId): string
    {
        return "settings.branch.{$branchId}";
    }
}
