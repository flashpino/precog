<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_alert_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('alert_type', 50);
            $table->string('days_of_week', 30); // ex: "1,2,3,4,5"
            $table->time('time_start');
            $table->time('time_end');
            $table->integer('min_interval')->default(30); // minutos entre reenvios
            $table->timestamps();

            $table->unique(['contact_id', 'alert_type'], 'unique_contact_alert');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_alert_preferences');
    }
};
