<?php

namespace App\Services;

use App\Models\GradingScale;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Owns the single global grading scale: the cached read used by marks and
 * results, the marks → grade resolution, and the validated full-replace write.
 */
class GradeResolver
{
    /**
     * The cache key for the ordered grading scale (highest band first).
     */
    private const CACHE_KEY = 'grading.scale';

    /**
     * How long the scale is cached (1 hour).
     */
    private const TTL = 3600;

    /**
     * The full grading scale, cached and ordered highest band first.
     *
     * @return Collection<int, GradingScale>
     */
    public function all(): Collection
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::TTL,
            fn (): Collection => GradingScale::query()->orderByDesc('min_marks')->get(),
        );
    }

    /**
     * Resolve obtained marks to their grade, grade point, and fail flag.
     *
     * Bands are contiguous and ordered highest first, so the first band whose
     * floor the marks reach is the match — robust to fractional marks that
     * fall between two integer boundaries.
     *
     * @return array{grade: string, grade_point: string, is_fail: bool}
     */
    public function resolve(int|float $marks): array
    {
        $band = $this->all()->first(fn (GradingScale $band): bool => $marks >= $band->min_marks);

        if ($band === null) {
            // Only reachable if the scale does not cover 0; the PUT validation
            // forbids that, but guard rather than return a partial result.
            throw new \RuntimeException("No grading band covers marks: {$marks}.");
        }

        return [
            'grade' => $band->grade,
            'grade_point' => $band->grade_point,
            'is_fail' => $band->is_fail,
        ];
    }

    /**
     * Replace the entire scale in one transaction and refresh the cache.
     *
     * @param  list<array{grade: string, min_marks: int, max_marks: int, grade_point: float|string, is_fail: bool}>  $scale
     * @return Collection<int, GradingScale>
     */
    public function replace(array $scale): Collection
    {
        DB::transaction(function () use ($scale): void {
            GradingScale::query()->delete();

            GradingScale::insert(array_map(
                fn (array $band): array => [
                    'grade' => $band['grade'],
                    'min_marks' => $band['min_marks'],
                    'max_marks' => $band['max_marks'],
                    'grade_point' => $band['grade_point'],
                    'is_fail' => $band['is_fail'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                $scale,
            ));
        });

        $this->forget();

        return $this->all();
    }

    /**
     * Forget the cached scale.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
