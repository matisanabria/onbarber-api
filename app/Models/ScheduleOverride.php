<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleOverride extends Model
{
    protected $fillable = ['barber_id', 'date', 'is_open', 'open_time', 'close_time'];

    protected $casts = [
        'is_open' => 'boolean',
        'date'    => 'date',
    ];

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }
}
