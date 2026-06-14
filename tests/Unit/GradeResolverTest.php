<?php

namespace Tests\Unit;

use App\Services\GradeResolver;
use Database\Seeders\GradingScaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GradeResolverTest extends TestCase
{
    use RefreshDatabase;

    private GradeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GradingScaleSeeder::class);

        $this->resolver = app(GradeResolver::class);
    }

    /**
     * @return array<string, array{int|float, string, string, bool}>
     */
    public static function boundaryProvider(): array
    {
        return [
            'top of scale' => [100, 'A+', '5.00', false],
            'A+ floor' => [80, 'A+', '5.00', false],
            'A ceiling' => [79, 'A', '4.00', false],
            'A- band' => [65, 'A-', '3.50', false],
            'D floor (pass boundary)' => [33, 'D', '1.00', false],
            'F ceiling (fail boundary)' => [32, 'F', '0.00', true],
            'bottom of scale' => [0, 'F', '0.00', true],
            'fractional in A band' => [79.5, 'A', '4.00', false],
        ];
    }

    #[DataProvider('boundaryProvider')]
    public function test_resolves_marks_at_boundaries(int|float $marks, string $grade, string $point, bool $isFail): void
    {
        $result = $this->resolver->resolve($marks);

        $this->assertSame($grade, $result['grade']);
        $this->assertSame($point, $result['grade_point']);
        $this->assertSame($isFail, $result['is_fail']);
    }
}
