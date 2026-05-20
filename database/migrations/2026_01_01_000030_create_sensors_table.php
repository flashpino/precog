<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('device_id', 50)->unique();
            $table->string('location', 100)->default('CPD');
            $table->string('label', 100)->default('Sensor');
            $table->decimal('temp_min', 5, 2)->default(18.00);
            $table->decimal('temp_max', 5, 2)->default(28.00);
            $table->decimal('hum_min', 5, 2)->default(40.00);
            $table->decimal('hum_max', 5, 2)->default(70.00);
            $table->date('activation_date')->nullable();
            $table->string('alert_state_temp', 20)->default('normal');
            $table->string('alert_state_hum', 20)->default('normal');
            $table->string('last_status', 20)->default('offline');
            $table->timestamp('last_seen')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensors');
    }
};
