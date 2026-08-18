<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'bike_id',
        'serviced_on',
        'mileage',
        'cost',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'serviced_on' => 'date',
            'mileage' => 'integer',
            'cost' => 'decimal:2',
        ];
    }

    public function bike(): BelongsTo
    {
        return $this->belongsTo(Bike::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
