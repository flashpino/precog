<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(): void
    {
        $url = config('services.n8n.webhook_url');

        if (!$url) {
            Log::error('N8N_WEBHOOK_URL not configured.');
            return;
        }

        try {
            $response = Http::timeout(10)->post($url, $this->payload);

            if ($response->failed()) {
                Log::error('Webhook rejected: Code ' . $response->status() . ' | Resp: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Webhook error: ' . $e->getMessage());
            // Se falhar por timeout, o Laravel tenta novamente baseado na configuração de fila
            throw $e;
        }
    }
}
