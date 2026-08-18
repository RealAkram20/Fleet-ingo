<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rider extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'license_number',
        'license_expiry',
        'is_active',
        'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'license_expiry' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function bikes(): HasMany
    {
        return $this->hasMany(Bike::class);
    }

    /**
     * Licence standing, in the same {level, label} shape the bike status uses so a
     * single badge component can render either one.
     */
    public function licenseStatus(): array
    {
        if (! $this->license_expiry) {
            return ['level' => 'warn', 'label' => 'No date on file', 'days' => null];
        }

        $days = (int) now()->startOfDay()->diffInDays($this->license_expiry->startOfDay(), false);

        return match (true) {
            $days < 0 => ['level' => 'bad', 'label' => 'Expired', 'days' => $days],
            $days <= 30 => ['level' => 'warn', 'label' => "Expires in {$days}d", 'days' => $days],
            default => ['level' => 'ok', 'label' => 'Valid', 'days' => $days],
        };
    }
}
