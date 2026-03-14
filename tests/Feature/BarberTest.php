<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Schedule;
use App\Models\ScheduleOverride;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarberTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_barbers_returns_only_active(): void
    {
        Barber::create(['name' => 'Kevin', 'phone' => '0981000001', 'active' => true]);
        Barber::create(['name' => 'Carlos', 'phone' => '0981000002', 'active' => true]);
        Barber::create(['name' => 'Inactivo', 'phone' => '0981000003', 'active' => false]);

        $response = $this->getJson('/api/barbers');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['name' => 'Kevin'])
            ->assertJsonFragment(['name' => 'Carlos'])
            ->assertJsonMissing(['name' => 'Inactivo']);
    }

    public function test_list_barbers_includes_photo_url(): void
    {
        Barber::create([
            'name' => 'Kevin',
            'photo_url' => 'https://r2.dev/barbers/kevin.webp',
            'active' => true,
        ]);

        $response = $this->getJson('/api/barbers');

        $response->assertOk()
            ->assertJsonFragment(['photo_url' => 'https://r2.dev/barbers/kevin.webp']);
    }

    public function test_slots_returns_available_hours(): void
    {
        $barber = Barber::create(['name' => 'Kevin', 'active' => true]);

        // Monday = 1
        Schedule::create([
            'barber_id' => $barber->id,
            'day_of_week' => 1,
            'is_open' => true,
            'open_time' => '09:00',
            'close_time' => '13:00',
        ]);

        // Find next Monday
        $monday = now()->next('Monday')->format('Y-m-d');

        $response = $this->getJson("/api/barbers/{$barber->id}/slots?date={$monday}");

        $response->assertOk()
            ->assertJsonPath('slots', ['09:00', '10:00', '11:00', '12:00']);
    }

    public function test_slots_excludes_booked(): void
    {
        $barber = Barber::create(['name' => 'Kevin', 'active' => true]);

        Schedule::create([
            'barber_id' => $barber->id,
            'day_of_week' => 1,
            'is_open' => true,
            'open_time' => '09:00',
            'close_time' => '12:00',
        ]);

        $monday = now()->next('Monday')->format('Y-m-d');

        Appointment::create([
            'barber_id' => $barber->id,
            'client_name' => 'Juan',
            'client_phone' => '0981111111',
            'appointment_date' => $monday,
            'appointment_time' => '10:00:00',
            'status' => 'pending',
        ]);

        $response = $this->getJson("/api/barbers/{$barber->id}/slots?date={$monday}");

        $response->assertOk()
            ->assertJsonPath('slots', ['09:00', '11:00']);
    }

    public function test_slots_cancelled_appointment_frees_slot(): void
    {
        $barber = Barber::create(['name' => 'Kevin', 'active' => true]);

        Schedule::create([
            'barber_id' => $barber->id,
            'day_of_week' => 1,
            'is_open' => true,
            'open_time' => '09:00',
            'close_time' => '11:00',
        ]);

        $monday = now()->next('Monday')->format('Y-m-d');

        Appointment::create([
            'barber_id' => $barber->id,
            'client_name' => 'Juan',
            'client_phone' => '0981111111',
            'appointment_date' => $monday,
            'appointment_time' => '09:00:00',
            'status' => 'cancelled',
        ]);

        $response = $this->getJson("/api/barbers/{$barber->id}/slots?date={$monday}");

        $response->assertOk()
            ->assertJsonPath('slots', ['09:00', '10:00']);
    }

    public function test_slots_returns_empty_on_closed_day(): void
    {
        $barber = Barber::create(['name' => 'Kevin', 'active' => true]);

        // Sunday = 0, no schedule = closed
        $sunday = now()->next('Sunday')->format('Y-m-d');

        $response = $this->getJson("/api/barbers/{$barber->id}/slots?date={$sunday}");

        $response->assertOk()
            ->assertJsonPath('slots', []);
    }

    public function test_slots_uses_override_over_schedule(): void
    {
        $barber = Barber::create(['name' => 'Kevin', 'active' => true]);

        // Regular schedule: Monday 9-17
        Schedule::create([
            'barber_id' => $barber->id,
            'day_of_week' => 1,
            'is_open' => true,
            'open_time' => '09:00',
            'close_time' => '17:00',
        ]);

        $monday = now()->next('Monday')->format('Y-m-d');

        // Override: only 9-12 that Monday
        ScheduleOverride::create([
            'barber_id' => $barber->id,
            'date' => $monday,
            'is_open' => true,
            'open_time' => '09:00',
            'close_time' => '12:00',
        ]);

        $response = $this->getJson("/api/barbers/{$barber->id}/slots?date={$monday}");

        $response->assertOk()
            ->assertJsonPath('slots', ['09:00', '10:00', '11:00']);
    }

    public function test_slots_override_closed(): void
    {
        $barber = Barber::create(['name' => 'Kevin', 'active' => true]);

        Schedule::create([
            'barber_id' => $barber->id,
            'day_of_week' => 1,
            'is_open' => true,
            'open_time' => '09:00',
            'close_time' => '17:00',
        ]);

        $monday = now()->next('Monday')->format('Y-m-d');

        ScheduleOverride::create([
            'barber_id' => $barber->id,
            'date' => $monday,
            'is_open' => false,
        ]);

        $response = $this->getJson("/api/barbers/{$barber->id}/slots?date={$monday}");

        $response->assertOk()
            ->assertJsonPath('slots', []);
    }

    public function test_slots_requires_date(): void
    {
        $barber = Barber::create(['name' => 'Kevin', 'active' => true]);

        $response = $this->getJson("/api/barbers/{$barber->id}/slots");

        $response->assertUnprocessable();
    }

    public function test_slots_404_for_inactive_barber(): void
    {
        $barber = Barber::create(['name' => 'Inactivo', 'active' => false]);

        $monday = now()->next('Monday')->format('Y-m-d');

        $response = $this->getJson("/api/barbers/{$barber->id}/slots?date={$monday}");

        $response->assertNotFound();
    }
}
