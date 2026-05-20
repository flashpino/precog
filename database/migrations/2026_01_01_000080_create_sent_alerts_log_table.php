<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_alerts_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('alert_type', 50);
            $table->timestamp('sent_at')->useCurrent();

            $table->index(['contact_id', 'alert_type', 'sent_at'], 'idx_contact_alert');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_alerts_logs');
    }
};
