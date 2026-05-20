<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->nullable()->constrained('sensors')->nullOnDelete();
            $table->enum('type', ['info', 'warning', 'error'])->default('info');
            $table->text('message');
            $table->boolean('is_admin_only')->default(false); // Eventos internos (ex: reboot ESP32)
            $table->timestamps();

            $table->index(['sensor_id', 'created_at'], 'idx_sensor_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
