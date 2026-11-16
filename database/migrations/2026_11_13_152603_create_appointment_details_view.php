<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE VIEW appointment_details AS
            SELECT
                appointments.id AS appointment_id,
                appointments.client_id,
                clients.name AS client_name,
                clients.email AS client_email,
                appointments.photographer_id,
                photographers.name AS photographer_name,
                photographers.email AS photographer_email,
                appointments.shoot_type,
                appointments.appointment_date,
                appointments.status,
                appointments.notes,
                appointments.created_at,
                appointments.updated_at
            FROM appointments
            INNER JOIN users AS clients ON clients.id = appointments.client_id
            INNER JOIN users AS photographers ON photographers.id = appointments.photographer_id
        ');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS appointment_details');
    }
};
