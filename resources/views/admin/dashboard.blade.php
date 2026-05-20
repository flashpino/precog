@extends('layouts.app')

@section('content')
<!-- Global Stats -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white border border-industrial-gray-200 p-4 rounded shadow-sm">
        <p class="text-[9px] font-bold text-industrial-gray-400 uppercase tracking-widest mb-1">Clientes Ativos</p>
        <p class="text-2xl font-bold text-industrial-gray-900 digital-font">{{ $stats['total_clients'] }}</p>
    </div>
    <div class="bg-white border border-industrial-gray-200 p-4 rounded shadow-sm">
        <p class="text-[9px] font-bold text-industrial-gray-400 uppercase tracking-widest mb-1">Sensores Online</p>
        <p class="text-2xl font-bold text-industrial-gray-900 digital-font">{{ $stats['active_sensors'] - $stats['offline_sensors'] }}<span class="text-sm text-industrial-gray-300 ml-1">/{{ $stats['active_sensors'] }}</span></p>
    </div>
    <div class="bg-white border border-industrial-gray-200 p-4 rounded shadow-sm">
        <p class="text-[9px] font-bold text-industrial-gray-400 uppercase tracking-widest mb-1">Equipamentos Offline</p>
        <p class="text-2xl font-bold {{ $stats['offline_sensors'] > 0 ? 'text-hmi-yellow' : 'text-industrial-gray-900' }} digital-font">{{ $stats['offline_sensors'] }}</p>
    </div>
    <div class="bg-white border border-industrial-gray-200 p-4 rounded shadow-sm">
        <p class="text-[9px] font-bold text-industrial-gray-400 uppercase tracking-widest mb-1">Alertas (7d)</p>
        <p class="text-2xl font-bold {{ $stats['recent_alerts'] > 0 ? 'text-hmi-red' : 'text-industrial-gray-900' }} digital-font">{{ $stats['recent_alerts'] }}</p>
    </div>
</div>

<!-- Monitoring Grid -->
<section class="mt-8">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-xs font-bold text-industrial-gray-900 uppercase tracking-widest flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-sm">sensors</span> Monitoramento de Dispositivos
        </h3>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @forelse($sensors as $sensor)
        @php
            $data = $influxData[$sensor->device_id] ?? null;
            $temp = $data['temperature'] ?? null;
            $hum = $data['humidity'] ?? null;
            $mac = $data['mac'] ?? 'N/A';
            $uptime = $data['uptime'] ?? 'N/A';
            $rssi = $data['rssi'] ?? 'N/A';
            
            // Format uptime if it's in seconds (example)
            if (is_numeric($uptime)) {
                $days = floor($uptime / 86400);
                $hours = floor(($uptime % 86400) / 3600);
                $mins = floor(($uptime % 3600) / 60);
                if ($days > 0) {
                    $uptimeFormatted = "{$days}d {$hours}h";
                } else {
                    $uptimeFormatted = "{$hours}h {$mins}m";
                }
            } else {
                $uptimeFormatted = $uptime;
            }
        @endphp
        <div class="bg-white border {{ $sensor->last_status === 'offline' ? 'border-hmi-yellow' : 'border-industrial-gray-300 hover:border-industrial-gray-400' }} rounded p-4 flex flex-col gap-4 transition-colors relative overflow-hidden shadow-sm group">
            <div class="flex justify-between items-start">
                <div class="flex flex-col">
                    <span class="text-industrial-gray-900 font-mono text-sm font-bold tracking-tight">{{ $sensor->client ? ($sensor->client->company ?: $sensor->client->name) : 'Sem Cliente' }}</span>
                    <span class="text-industrial-gray-500 text-xs uppercase tracking-tighter">{{ $sensor->location ?: 'Sem Local' }} - {{ $sensor->device_id }}</span>
                    <span class="text-industrial-gray-900 text-[8px] font-mono font-bold mt-1 uppercase tracking-tighter">MAC: {{ $mac }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full {{ $sensor->last_status === 'offline' ? 'bg-hmi-yellow' : 'bg-hmi-green status-glow-green' }}" title="{{ $sensor->last_status }}"></div>
                </div>
            </div>
                <div class="grid grid-cols-2 gap-4 border-y border-industrial-gray-100 py-4">
                    <div class="flex flex-col">
                        <span class="text-[10px] text-industrial-gray-400 uppercase font-bold mb-1">Temperatura</span>
                        <span id="temp-{{ $sensor->device_id }}" class="digital-font text-2xl font-bold {{ $sensor->alert_state_temp !== 'normal' ? 'text-hmi-red' : 'text-industrial-gray-900' }} leading-none">
                            {{ $temp !== null ? number_format($temp, 1) : '--' }} °C
                        </span>
                    </div>
                    <div class="flex flex-col border-l border-industrial-gray-200 pl-4">
                        <span class="text-[10px] text-industrial-gray-400 uppercase font-bold mb-1">Umidade</span>
                        <span id="hum-{{ $sensor->device_id }}" class="digital-font text-2xl font-bold {{ $sensor->alert_state_hum !== 'normal' ? 'text-hmi-red' : 'text-industrial-gray-600' }} leading-none">
                            {{ $hum !== null ? number_format($hum, 1) : '--' }} %
                        </span>
                    </div>
                </div>
                <div class="flex justify-between items-center text-[10px] font-mono text-industrial-gray-400">
                    <span id="uptime-{{ $sensor->device_id }}">Uptime: {{ $uptimeFormatted }}</span>
                    <span id="rssi-{{ $sensor->device_id }}">WiFi: {{ $rssi }}{{ $rssi !== 'N/A' ? 'dBm' : '' }}</span>
                </div>
        </div>
        @empty
        <div class="col-span-full p-8 text-center text-industrial-gray-500 bg-white border border-industrial-gray-200 rounded">
            Nenhum equipamento cadastrado. <a href="{{ route('sensors.create') }}" class="text-primary hover:underline">Adicionar Sensor</a>
        </div>
        @endforelse
    </div>
</section>

<!-- Bottom Content -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
    <!-- Recent Events -->
    <div class="bg-white border border-industrial-gray-200 rounded p-5 shadow-sm">
        <h3 class="text-[10px] font-bold text-industrial-gray-900 uppercase tracking-widest mb-4 flex items-center gap-2 border-b border-industrial-gray-100 pb-2">
            <span class="material-symbols-outlined text-industrial-gray-500 text-sm">history</span> Eventos Recentes
        </h3>
        <ul class="space-y-3">
            @forelse($recentEvents as $event)
            <li class="flex items-center justify-between p-2 hover:bg-industrial-gray-50 rounded transition-colors group">
                <div class="flex items-center gap-3">
                    <div class="w-1.5 h-1.5 rounded-full {{ $event->type === 'warning' ? 'bg-hmi-yellow' : ($event->type === 'danger' ? 'bg-hmi-red' : 'bg-hmi-green') }}"></div>
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-industrial-gray-800">{{ $event->message }}</span>
                        <span class="text-[10px] text-industrial-gray-400 uppercase">{{ $event->sensor->device_id ?? 'Sistema' }}</span>
                    </div>
                </div>
                <span class="text-[9px] font-mono text-industrial-gray-400">{{ $event->created_at->diffForHumans() }}</span>
            </li>
            @empty
            <li class="text-xs text-industrial-gray-400 p-2">Nenhum evento registrado.</li>
            @endforelse
        </ul>
        <a href="#" class="mt-4 block text-center text-[9px] font-bold text-primary uppercase tracking-widest hover:underline">Ver Histórico de Eventos</a>
    </div>

    <!-- Recent Alerts -->
    <div class="bg-white border border-industrial-gray-200 rounded p-5 shadow-sm">
        <h3 class="text-[10px] font-bold text-industrial-gray-900 uppercase tracking-widest mb-4 flex items-center gap-2 border-b border-industrial-gray-100 pb-2">
            <span class="material-symbols-outlined text-industrial-gray-500 text-sm">warning</span> Últimos Alertas
        </h3>
        <ul class="space-y-3">
            @forelse($recentAlerts as $alert)
            <li class="flex items-center justify-between p-2 bg-red-50/50 rounded border-l-2 border-hmi-red">
                <div class="flex flex-col">
                    <span class="text-xs font-bold text-industrial-gray-800">{{ $alert->message }} ({{ $alert->value }})</span>
                    <span class="text-[10px] text-industrial-gray-400 uppercase">Sensor {{ $alert->sensor->device_id ?? 'N/A' }}</span>
                </div>
                <span class="text-[9px] font-mono text-industrial-gray-400">{{ $alert->created_at->diffForHumans() }}</span>
            </li>
            @empty
            <li class="text-xs text-industrial-gray-400 p-2">Nenhum alerta recente.</li>
            @endforelse
        </ul>
        <a href="#" class="mt-4 block text-center text-[9px] font-bold text-primary uppercase tracking-widest hover:underline">Ver Todos os Alertas</a>
    </div>
</div>
<script>
    // Auto-refresh da página a cada 60s para manter as listas de Eventos e Alertas atualizadas
    setTimeout(() => {
        window.location.reload();
    }, 60000);

    // Fetch de telemetria a cada 10s para os cards
    setInterval(() => {
        fetch("{{ route('api.telemetry') }}")
            .then(res => res.json())
            .then(data => {
                for (const [deviceId, metrics] of Object.entries(data)) {
                    const elTemp = document.getElementById('temp-' + deviceId);
                    const elHum = document.getElementById('hum-' + deviceId);
                    const elUptime = document.getElementById('uptime-' + deviceId);
                    const elRssi = document.getElementById('rssi-' + deviceId);

                    if (elTemp) {
                        elTemp.innerHTML = (metrics.temperature !== undefined && metrics.temperature !== null) ? metrics.temperature + ' &deg;C' : '-- &deg;C';
                    }
                    if (elHum) {
                        elHum.innerHTML = (metrics.humidity !== undefined && metrics.humidity !== null) ? metrics.humidity + ' %' : '-- %';
                    }
                    if (elUptime) {
                        elUptime.innerText = (metrics.uptime !== undefined && metrics.uptime !== null) ? 'Uptime: ' + metrics.uptime : 'Uptime: N/A';
                    }
                    if (elRssi) {
                        elRssi.innerText = (metrics.rssi !== undefined && metrics.rssi !== null) ? 'WiFi: ' + metrics.rssi + 'dBm' : 'WiFi: N/A';
                    }

                    // Update status dot and border colors live
                    const card = elTemp ? elTemp.closest('.bg-white') : null;
                    if (card) {
                        const statusDot = card.querySelector('.w-3.h-3');
                        if (metrics.status === 'offline') {
                            card.classList.remove('border-industrial-gray-300', 'hover:border-industrial-gray-400');
                            card.classList.add('border-hmi-yellow');
                            if (statusDot) {
                                statusDot.className = 'w-3 h-3 rounded-full bg-hmi-yellow';
                            }
                        } else {
                            card.classList.remove('border-hmi-yellow');
                            card.classList.add('border-industrial-gray-300', 'hover:border-industrial-gray-400');
                            if (statusDot) {
                                statusDot.className = 'w-3 h-3 rounded-full bg-hmi-green status-glow-green';
                            }
                        }
                    }
                }
            })
            .catch(err => console.error("Erro ao buscar telemetria live", err));
    }, 10000);
</script>
@endsection
