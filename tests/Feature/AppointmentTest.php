<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private Barber $barber;
    private string $monday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barber = Barber::create(['name' => 'Kevin', 'active' => true]);

        Schedule::create([
            'barber_id' => $this->barber->id,
            'day_of_week' => 1, // Monday
            'is_open' => true,
            'open_time' => '09:00',
            'close_time' => '19:00',
        ]);

        $this->monday = now()->next('Monday')->format('Y-m-d');
    }

    public function test_create_appointment_success(): void
    {
        $response = $this->postJson('/api/appointments', [
            'barber_id' => $this->barber->id,
            'client_name' => 'Juan Pérez',
            'client_phone' => '0981123456',
            'appointment_date' => $this->monday,
            'appointment_time' => '10:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('barber.name', 'Kevin');

        $this->assertDatabaseHas('appointments', [
            'client_name' => 'Juan Pérez',
            'appointment_time' => '10:00:00',
            'status' => 'pending',
        ]);
    }

    public function test_create_appointment_rejects_double_booking(): void
    {
        Appointment::create([
            'barber_id' => $this->barber->id,
            'client_name' => 'Juan',
            'client_phone' => '0981111111',
            'appointment_date' => $this->monday,
            'appointment_time' => '10:00:00',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/appointments', [
            'barber_id' => $this->barber->id,
            'client_name' => 'Pedro',
            'client_phone' => '0981222222',
            'appointment_date' => $this->monday,
            'appointment_time' => '10:00',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_appointment_allows_cancelled_slot(): void
    {
        Appointment::create([
            'barber_id' => $this->barber->id,
            'client_name' => 'Juan',
            'client_phone' => '0981111111',
            'appointment_date' => $this->monday,
            'appointment_time' => '10:00:00',
            'status' => 'cancelled',
        ]);

        $response = $this->postJson('/api/appointments', [
            'barber_id' => $this->barber->id,
            'client_name' => 'Pedro',
            'client_phone' => '0981222222',
            'appointment_date' => $this->monday,
            'appointment_time' => '10:00',
        ]);

        $response->assertCreated();
    }

    public function test_create_appointment_rejects_outside_hours(): void
    {
        $response = $this->postJson('/api/appointments', [
            'barber_id' => $this->barber->id,
            'client_name' => 'Juan',
            'client_phone' => '0981111111',
            'appointment_date' => $this->monday,
            'appointment_time' => '07:00',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_appointment_rejects_closed_day(): void
    {
        $sunday = now()->next('Sunday')->format('Y-m-d');

        $response = $this->postJson('/api/appointments', [
            'barber_id' => $this->barber->id,
            'client_name' => 'Juan',
            'client_phone' => '0981111111',
            'appointment_date' => $sunday,
            'appointment_time' => '10:00',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_appointment_rejects_inactive_barber(): void
    {
        $inactive = Barber::create(['name' => 'Inactivo', 'active' => false]);

        $response = $this->postJson('/api/appointments', [
            'barber_id' => $inactive->id,
            'client_name' => 'Juan',
            'client_phone' => '0981111111',
            'appointment_date' => $this->monday,
            'appointment_time' => '10:00',
        ]);

        $response->assertNotFound();
    }

    public function test_create_appointment_validates_required_fields(): void
    {
        $response = $this->postJson('/api/appointments', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['barber_id', 'client_name', 'client_phone', 'appointment_date', 'appointment_time']);
    }

    // --- Protected endpoints ---

    public function test_list_appointments_requires_auth(): void
    {
        $response = $this->getJson('/api/appointments');

        $response->assertUnauthorized();
    }

    public function test_list_appointments_with_valid_token(): void
    {
        config(['app.barbman_token' => 'test-secret-token']);

        Appointment::create([
            'barber_id' => $this->barber->id,
            'client_name' => 'Juan',
            'client_phone' => '0981111111',
            'appointment_date' => $this->monday,
            'appointment_time' => '10:00:00',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/appointments', [
            'Authorization' => 'Bearer test-secret-token',
        ]);

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['client_name' => 'Juan']);
    }

    public function test_list_appointments_rejects_wrong_token(): void
    {
        config(['app.barbman_token' => 'test-secret-token']);

        $response = $this->getJson('/api/appointments', [
            'Authorization' => 'Bearer wrong-token',
        ]);

        $response->assertUnauthorized();
    }

    public function test_update_appointment_status(): void
    {
        config(['app.barbman_token' => 'test-secret-token']);

        $appointment = Appointment::create([
            'barber_id' => $this->barber->id,
            'client_name' => 'Juan',
            'client_phone' => '0981111111',
            'appointment_date' => $this->monday,
            'appointment_time' => '10:00:00',
            'status' => 'pending',
        ]);

        $response = $this->patchJson("/api/appointments/{$appointment->id}", [
            'status' => 'confirmed',
        ], [
            'Authorization' => 'Bearer test-secret-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'confirmed');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_update_appointment_rejects_invalid_status(): void
    {
        config(['app.barbman_token' => 'test-secret-token']);

        $appointment = Appointment::create([
            'barber_id' => $this->barber->id,
            'client_name' => 'Juan',
            'client_phone' => '0981111111',
            'appointment_date' => $this->monday,
            'appointment_time' => '10:00:00',
            'status' => 'pending',
        ]);

        $response = $this->patchJson("/api/appointments/{$appointment->id}", [
            'status' => 'invalid_status',
        ], [
            'Authorization' => 'Bearer test-secret-token',
        ]);

        $response->assertUnprocessable();
    }

    public function test_update_appointment_requires_auth(): void
    {
        $appointment = Appointment::create([
            'barber_id' => $this->barber->id,
            'client_name' => 'Juan',
            'client_phone' => '0981111111',
            'appointment_date' => $this->monday,
            'appointment_time' => '10:00:00',
            'status' => 'pending',
        ]);

        $response = $this->patchJson("/api/appointments/{$appointment->id}", [
            'status' => 'confirmed',
        ]);

        $response->assertUnauthorized();
    }
}
