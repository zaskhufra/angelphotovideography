<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
            $table->string('photographer_name')->nullable();
            $table->string('photographer_email')->nullable();
        });

        DB::table('appointments')
            ->join('users as clients', 'clients.id', '=', 'appointments.client_id')
            ->join('users as photographers', 'photographers.id', '=', 'appointments.photographer_id')
            ->select([
                'appointments.id as id',
                'clients.name as client_name',
                'clients.email as client_email',
                'photographers.name as photographer_name',
                'photographers.email as photographer_email',
            ])
            ->orderBy('appointments.id')
            ->chunkById(100, function ($appointments): void {
                foreach ($appointments as $appointment) {
                    DB::table('appointments')
                        ->where('id', $appointment->id)
                        ->update([
                            'client_name' => $appointment->client_name,
                            'client_email' => $appointment->client_email,
                            'photographer_name' => $appointment->photographer_name,
                            'photographer_email' => $appointment->photographer_email,
                        ]);
                }
            }, 'appointments.id', 'id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'client_name',
                'client_email',
                'photographer_name',
                'photographer_email',
            ]);
        });
    }
};
