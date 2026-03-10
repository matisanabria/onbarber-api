<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Barber;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function index(): JsonResponse
    {
        $appointments = Appointment::with('barber:id,name')
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->get();

        return response()->json($appointments);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barber_id'        => 'required|exists:barbers,id',
            'client_name'      => 'required|string|max:100',
            'client_phone'     => 'required|string|max:30',
            'appointment_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
        ]);

        $barber = Barber::active()->findOrFail($data['barber_id']);
        $date   = Carbon::parse($data['appointment_date']);
        $time   = $data['appointment_time'];
        $dow    = (int) $date->format('w');

        // Verify the barber is open that day
        $override = $barber->scheduleOverrides()
            ->whereDate('date', $date)
            ->first();

        if ($override) {
            abort_if(! $override->is_open, 422, 'The barber is not available on that date.');
            $openTime  = $override->open_time;
            $closeTime = $override->close_time;
        } else {
            $schedule = $barber->schedules()->where('day_of_week', $dow)->first();
            abort_if(! $schedule || ! $schedule->is_open, 422, 'The barber is not available on that day.');
            $openTime  = $schedule->open_time;
            $closeTime = $schedule->close_time;
        }

        // Verify the slot falls within working hours
        $slotStart = Carbon::parse($date->format('Y-m-d') . ' ' . $time);
        $slotEnd   = $slotStart->copy()->addHour();
        $open      = Carbon::parse($date->format('Y-m-d') . ' ' . $openTime);
        $close     = Carbon::parse($date->format('Y-m-d') . ' ' . $closeTime);

        abort_if($slotStart->lt($open) || $slotEnd->gt($close), 422, 'The selected time slot is outside working hours.');

        // Verify slot is not already taken
        $conflict = $barber->appointments()
            ->whereDate('appointment_date', $date)
            ->where('appointment_time', $time . ':00')
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        abort_if($conflict, 422, 'That time slot is already taken.');

        $appointment = Appointment::create([
            'barber_id'        => $barber->id,
            'client_name'      => $data['client_name'],
            'client_phone'     => $data['client_phone'],
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $time . ':00',
            'status'           => 'pending',
        ]);

        return response()->json($appointment->load('barber:id,name'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'confirmed', 'cancelled', 'completed'])],
        ]);

        $appointment->update($data);

        return response()->json($appointment->fresh('barber:id,name'));
    }
}
