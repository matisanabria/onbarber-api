<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarberController extends Controller
{
    public function index(): JsonResponse
    {
        $barbers = Barber::active()->get(['id', 'name', 'phone', 'photo_url']);

        return response()->json($barbers);
    }

    public function slots(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $barber = Barber::active()->findOrFail($id);
        $date   = Carbon::parse($request->input('date'));
        $dow    = (int) $date->format('w'); // 0=Sunday … 6=Saturday

        // Determine open/close from override or regular schedule
        $override = $barber->scheduleOverrides()
            ->whereDate('date', $date)
            ->first();

        if ($override) {
            if (! $override->is_open) {
                return response()->json(['slots' => []]);
            }
            $openTime  = $override->open_time;
            $closeTime = $override->close_time;
        } else {
            $schedule = $barber->schedules()
                ->where('day_of_week', $dow)
                ->first();

            if (! $schedule || ! $schedule->is_open) {
                return response()->json(['slots' => []]);
            }
            $openTime  = $schedule->open_time;
            $closeTime = $schedule->close_time;
        }

        // Build hourly slots
        $slots = [];
        $current = Carbon::parse($date->format('Y-m-d') . ' ' . $openTime);
        $close   = Carbon::parse($date->format('Y-m-d') . ' ' . $closeTime);

        while ($current->copy()->addHour()->lte($close)) {
            $slots[] = $current->format('H:i');
            $current->addHour();
        }

        // Get booked slots (non-cancelled)
        $booked = $barber->appointments()
            ->whereDate('appointment_date', $date)
            ->whereNotIn('status', ['cancelled'])
            ->pluck('appointment_time')
            ->map(fn ($t) => substr($t, 0, 5)) // "HH:MM:SS" → "HH:MM"
            ->toArray();

        $result = array_map(fn ($time) => [
            'time'      => $time,
            'available' => ! in_array($time, $booked),
        ], $slots);

        return response()->json(['slots' => $result]);
    }
}
