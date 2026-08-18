<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Bike extends Model
{
    use HasFactory, SoftDeletes;

    /** Kilometres left before a bike is flagged Due Soon. */
    public const DUE_SOON_KM = 300;

    /** Days left before a time-based service is flagged Due Soon. */
    public const DUE_SOON_DAYS = 30;

    protected $fillable = [
        'reg',
        'model',
        'rider_id',
        'service_interval_km',
        'service_interval_months',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'service_interval_km' => 'integer',
            'service_interval_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function serviceRecords(): HasMany
    {
        return $this->hasMany(ServiceRecord::class);
    }

    /**
     * Loads current mileage and last-service figures as aggregates so a whole
     * dashboard costs one query instead of one per bike.
     *
     * A validated odometer only ever rises, so MAX(mileage) is the current reading.
     */
    public function scopeWithFleetStats(Builder $query): Builder
    {
        return $query
            ->withMax('readings as current_mileage', 'mileage')
            ->withMax('readings as last_reading_on', 'recorded_on')
            ->withMax('serviceRecords as last_service_mileage', 'mileage')
            ->withMax('serviceRecords as last_serviced_on', 'serviced_on');
    }

    public function currentMileage(): int
    {
        return (int) ($this->current_mileage ?? $this->readings()->max('mileage') ?? 0);
    }

    public function lastServiceMileage(): int
    {
        return (int) ($this->last_service_mileage ?? $this->serviceRecords()->max('mileage') ?? 0);
    }

    public function lastServicedOn(): ?Carbon
    {
        $value = $this->last_serviced_on ?? $this->serviceRecords()->max('serviced_on');

        return $value ? Carbon::parse($value) : null;
    }

    /** Kilometres remaining until the next service. Negative means overdue. */
    public function kmUntilService(): int
    {
        return $this->service_interval_km - ($this->currentMileage() - $this->lastServiceMileage());
    }

    /** Days remaining until the time-based service falls due, or null if not tracked. */
    public function daysUntilService(): ?int
    {
        $lastServiced = $this->lastServicedOn();

        if (! $this->service_interval_months || ! $lastServiced) {
            return null;
        }

        $dueOn = $lastServiced->copy()->addMonths($this->service_interval_months);

        return (int) now()->startOfDay()->diffInDays($dueOn->startOfDay(), false);
    }

    /**
     * Service standing — distance or time, whichever is worse. Mirrors the
     * {level, label} shape used everywhere else so one badge renders all of it.
     */
    public function serviceStatus(): array
    {
        $km = $this->kmUntilService();
        $days = $this->daysUntilService();

        $byDistance = match (true) {
            $km <= 0 => ['level' => 'bad', 'label' => 'Overdue'],
            $km <= self::DUE_SOON_KM => ['level' => 'warn', 'label' => 'Due Soon'],
            default => ['level' => 'ok', 'label' => 'OK'],
        };

        $byTime = match (true) {
            $days === null => ['level' => 'ok', 'label' => 'OK'],
            $days <= 0 => ['level' => 'bad', 'label' => 'Overdue'],
            $days <= self::DUE_SOON_DAYS => ['level' => 'warn', 'label' => 'Due Soon'],
            default => ['level' => 'ok', 'label' => 'OK'],
        };

        $rank = ['ok' => 0, 'warn' => 1, 'bad' => 2];
        $timeIsWorse = $rank[$byTime['level']] > $rank[$byDistance['level']];
        $worst = $timeIsWorse ? $byTime : $byDistance;

        return [
            'level' => $worst['level'],
            'label' => $worst['label'],
            'km_remaining' => $km,
            'days_remaining' => $days,
            'driver' => $timeIsWorse ? 'time' : 'distance',
        ];
    }

    /** Odometer at which the next service falls due. */
    public function nextServiceAtKm(): int
    {
        return $this->lastServiceMileage() + $this->service_interval_km;
    }
}
