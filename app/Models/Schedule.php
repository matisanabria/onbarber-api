<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $fillable = ['barber_id', 'day_of_week', 'is_open', 'open_time', 'close_time', 'break_start', 'break_end'];

    protected $casts = [
        'is_open' => 'boolean',
    ];

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }
}
