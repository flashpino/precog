<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained('sensors')->cascadeOnDelete();
            $table->enum('type', ['temp_high', 'temp_low', 'hum_high', 'hum_low']);
            $table->text('message')->nullable();
            $table->decimal('value', 5, 2)->nullable();
            $table->decimal('threshold', 5, 2)->nullable();
            $table->boolean('webhook_sent')->default(false);
            $table->timestamps();

            $table->index(['sensor_id', 'type', 'created_at'], 'idx_sensor_type_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
