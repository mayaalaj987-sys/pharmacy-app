<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One side's verdict on one finished job.
 *
 * Deliberately not the same thing as {@see Rating}, which is a pharmacist
 * rating this application.
 */
class EmploymentRating extends Model
{
    /** The employee judging the pharmacy they worked for. */
    public const FROM_EMPLOYEE = 'employee_to_pharmacy';

    /** The pharmacy judging the person who worked for them. */
    public const FROM_PHARMACY = 'pharmacy_to_employee';

    use HasFactory;

    protected $fillable = ['employment_id', 'direction', 'stars'];

    protected function casts(): array
    {
        return ['stars' => 'integer'];
    }

    public function employment()
    {
        return $this->belongsTo(Employment::class);
    }

    /**
     * The average and count for a set of employments, in one direction.
     *
     * Returns the pair the client shows and nothing else — never the individual
     * rows. A pharmacy has two staff, so naming who said what would make an
     * honest low rating unsurvivable for the person who gave it.
     *
     * @return array{average: float|null, count: int}
     */
    public static function summarise(iterable $employmentIds, string $direction): array
    {
        $ids = collect($employmentIds)->all();

        if ($ids === []) {
            return ['average' => null, 'count' => 0];
        }

        $ratings = self::query()
            ->whereIn('employment_id', $ids)
            ->where('direction', $direction);

        $count = (clone $ratings)->count();

        return [
            'average' => $count === 0 ? null : round((float) $ratings->avg('stars'), 1),
            'count' => $count,
        ];
    }
}
