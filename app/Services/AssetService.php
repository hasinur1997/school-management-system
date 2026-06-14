<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Models\Asset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AssetService
{
    /**
     * Browse assets in the caller's branch (scope is automatic via branch_id).
     * Filters: status, name search. Sort by value or purchase_date (default
     * purchase_date desc).
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'purchase_date';
        $direction = $filters['direction'] ?? 'desc';

        return Asset::query()
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['search']), fn (Builder $query) => $query->where('name', 'like', '%'.$filters['search'].'%'))
            ->orderBy($sort, $direction)
            ->paginate($perPage);
    }

    /**
     * Create an asset. branch_id is stamped by BelongsToBranch; created_by is
     * the authenticated user; status defaults to in_use.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Asset
    {
        $data['created_by'] = Auth::id();
        // Status column defaults to in_use at the DB level; set it on the model
        // too so the freshly-created instance reflects it in the response.
        $data['status'] ??= AssetStatus::InUse;

        return Asset::create($data);
    }

    /**
     * Update an asset.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Asset $asset, array $data): Asset
    {
        $asset->update($data);

        return $asset;
    }

    /**
     * Delete an asset.
     */
    public function delete(Asset $asset): void
    {
        $asset->delete();
    }

    /**
     * At-a-glance figures for the caller's branch, computed in a single
     * aggregate query. total_value sums in_use + damaged only — disposed assets
     * are excluded from the headline figure (decision per ticket 11.4) — while
     * count and the per-status breakdown cover every status.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $rows = Asset::query()
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(value), 0) as value')
            ->groupBy('status')
            ->get()
            ->keyBy(fn ($row) => $row->status->value);

        $byStatus = [];
        $totalValue = '0';
        $count = 0;

        foreach (AssetStatus::cases() as $status) {
            $row = $rows->get($status->value);
            $statusCount = (int) ($row->count ?? 0);
            $statusValue = (string) ($row->value ?? '0');

            $byStatus[$status->value] = [
                'count' => $statusCount,
                'value' => number_format((float) $statusValue, 2, '.', ''),
            ];

            $count += $statusCount;

            if ($status !== AssetStatus::Disposed) {
                $totalValue = bcadd($totalValue, $statusValue, 2);
            }
        }

        return [
            'total_value' => number_format((float) $totalValue, 2, '.', ''),
            'count' => $count,
            'by_status' => $byStatus,
        ];
    }
}
