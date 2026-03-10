<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $fillable = [
        'barber_id',
        'client_name',
        'client_phone',
        'appointment_date',
        'appointment_time',
        'status',
    ];

    protected $casts = [
        'appointment_date' => 'date',
    ];

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }
}
