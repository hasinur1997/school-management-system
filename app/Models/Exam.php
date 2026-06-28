<?php

namespace App\Models;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use App\Models\Scopes\BranchScope;
use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * An exam held for one session and a set of classes. An exam targets either an
 * explicit list of classes (the exam_class pivot) or, when `all_classes` is
 * true, every class in its branch. Branch is stamped/scoped automatically via
 * BelongsToBranch; status only moves forward through the ExamStatus lifecycle.
 */
#[Fillable(['branch_id', 'session_id', 'type', 'name', 'all_classes', 'start_date', 'end_date', 'status'])]
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use BelongsToBranch, HasFactory, HasPublicId;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ExamStatus::Upcoming->value,
        'all_classes' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExamType::class,
            'status' => ExamStatus::class,
            'all_classes' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * The session this exam belongs to.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    /**
     * The classes this exam is held for. Empty when `all_classes` is true —
     * resolve the effective set via {@see classIds()} instead.
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'exam_class', 'exam_id', 'class_id');
    }

    /**
     * The effective set of class ids this exam covers: the branch's classes when
     * `all_classes` is set, otherwise the explicitly attached classes. Used by
     * the marks and result engines, which operate per class.
     *
     * @return list<int>
     */
    public function classIds(): array
    {
        return $this->effectiveClasses()->pluck('id')->all();
    }

    /**
     * The effective classes this exam covers as full models: the branch's
     * classes when `all_classes` is set, otherwise the explicitly attached
     * pivot rows. Memoized so a resource can read names/public ids without
     * re-querying. Drives both the marks/result engines (via {@see classIds()})
     * and the API resource's class list, so an `all_classes` exam still reports
     * which classes it covers rather than an empty set.
     *
     * @return Collection<int, SchoolClass>
     */
    public function effectiveClasses(): Collection
    {
        return $this->effectiveClasses ??= $this->all_classes
            ? SchoolClass::query()
                ->withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $this->branch_id)
                ->get()
            : $this->classes;
    }

    /**
     * Memoized backing store for {@see effectiveClasses()}.
     *
     * @var Collection<int, SchoolClass>|null
     */
    protected ?Collection $effectiveClasses = null;
}
