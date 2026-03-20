<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\ScheduleOverride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleOverrideController extends Controller
{
    /**
     * GET /barbers/{barberId}/overrides — list overrides for a barber
     */
    public function index(int $barberId): JsonResponse
    {
        $barber = Barber::findOrFail($barberId);

        $overrides = $barber->scheduleOverrides()
            ->orderBy('date')
            ->get();

        return response()->json($overrides);
    }

    /**
     * POST /schedule-overrides — close (or modify) a specific day
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barber_id'   => 'required|exists:barbers,id',
            'date'        => 'required|date_format:Y-m-d',
            'is_open'     => 'required|boolean',
            'open_time'   => 'nullable|date_format:H:i',
            'close_time'  => 'nullable|date_format:H:i',
            'break_start' => 'nullable|date_format:H:i',
            'break_end'   => 'nullable|date_format:H:i',
        ]);

        // Upsert: if an override already exists for this barber+date, update it
        $override = ScheduleOverride::updateOrCreate(
            ['barber_id' => $data['barber_id'], 'date' => $data['date']],
            $data
        );

        return response()->json($override->fresh(), 201);
    }

    /**
     * DELETE /schedule-overrides/{id} — remove an override (revert to normal schedule)
     */
    public function destroy(int $id): JsonResponse
    {
        $override = ScheduleOverride::findOrFail($id);
        $override->delete();

        return response()->json(['message' => 'Override removed.']);
    }
}
