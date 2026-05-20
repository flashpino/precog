<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Cliente - {{ $clientModel->company ?: $clientModel->name }}</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .status-glow-green { box-shadow: 0 0 8px rgba(16, 185, 129, 0.4); }
        .digital-font { font-family: 'JetBrains Mono', monospace; letter-spacing: -0.05em; }
    </style>
</head>
<body class="client-theme bg-industrial-gray-50 text-industrial-gray-800 min-h-screen flex flex-col font-sans selection:bg-primary selection:text-white">

    <!-- Header -->
    <header class="bg-industrial-gray-900 text-white border-b border-industrial-gray-800 sticky top-0 z-50 shadow-md">
        <div class="container mx-auto px-4 h-14 flex items-center justify-between">
            <div class="flex items-center">
                <img src="{{ asset('images/logo-cliente.png') }}" alt="PrecogSystem" class="h-8 w-auto">
            </div>
            
            <div class="flex items-center gap-4">
                <span class="text-xs text-industrial-gray-300 font-mono hidden md:block">{{ $clientModel->company ?: $clientModel->name }}</span>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-6 flex flex-col gap-6">
        
        <!-- Welcome Message -->
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-bold text-industrial-gray-900 uppercase tracking-widest flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-sm">monitor</span> Meus Sensores
            </h2>
            <div class="text-[10px] text-industrial-gray-500 font-mono">
                Última atualização: <span id="clock">{{ now()->format('H:i:s') }}</span>
            </div>
        </div>

        <!-- Monitoring Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @forelse($sensors as $sensor)
            @php
                $data = $influxData[$sensor->device_id] ?? null;
                $temp = $data['temperature'] ?? null;
                $hum = $data['humidity'] ?? null;
                $mac = $data['mac'] ?? 'N/A';
                $uptime = $data['uptime'] ?? 'N/A';
                $rssi = $data['rssi'] ?? 'N/A';
                
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
            <div class="bg-white border {{ $sensor->last_status === 'offline' ? 'border-hmi-yellow' : 'border-industrial-gray-300 hover:border-industrial-gray-400' }} rounded p-4 flex flex-col gap-4 shadow-sm relative group overflow-hidden transition-colors">
                <div class="flex justify-between items-start">
                    <div class="flex flex-col">
                        <span class="text-industrial-gray-900 font-mono text-sm font-bold tracking-tight">{{ $sensor->label ?: $sensor->device_id }}</span>
                        <span class="text-industrial-gray-500 text-xs uppercase tracking-tighter">{{ $sensor->location ?: 'Sem Local' }}</span>
                        <span class="text-industrial-gray-900 text-[8px] font-mono font-bold mt-1 uppercase tracking-tighter">MAC: {{ $mac }}</span>
                    </div>
                    <div class="w-3 h-3 rounded-full {{ $sensor->last_status === 'offline' ? 'bg-hmi-yellow' : 'bg-hmi-green status-glow-green' }}" title="{{ $sensor->last_status }}"></div>
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
            <div class="col-span-full bg-white border border-industrial-gray-300 rounded p-8 text-center text-industrial-gray-500 shadow-sm">
                Nenhum sensor vinculado a esta conta no momento.
            </div>
            @endforelse
        </div>

        <!-- Recent Alerts -->
        <div class="mt-4">
            <h2 class="text-sm font-bold text-industrial-gray-900 uppercase tracking-widest flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-primary text-sm">history</span> Últimos Alertas
            </h2>
            <div class="bg-white border border-industrial-gray-300 rounded shadow-sm overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-industrial-gray-50 border-b border-industrial-gray-200 text-[10px] uppercase tracking-widest text-industrial-gray-500 font-bold">
                        <tr>
                            <th class="p-4">Data/Hora</th>
                            <th class="p-4">Equipamento</th>
                            <th class="p-4">Mensagem</th>
                            <th class="p-4">Valores</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-industrial-gray-100 text-industrial-gray-700">
                        @forelse($recentAlerts as $alert)
                        <tr class="hover:bg-industrial-gray-50 transition-colors">
                            <td class="p-4 font-mono">{{ $alert->created_at->format('d/m/y H:i:s') }}</td>
                            <td class="p-4 font-bold">{{ $alert->sensor->device_id }}</td>
                            <td class="p-4">
                                <span class="font-bold text-hmi-red uppercase tracking-widest">{{ $alert->type }}</span> - {{ $alert->message }}
                            </td>
                            <td class="p-4 font-mono text-[10px]">
                                Valor: <span class="text-hmi-red font-bold">{{ $alert->value }}</span> / Limite: {{ $alert->threshold }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="p-4 text-center text-industrial-gray-400">Nenhum alerta recente.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="bg-industrial-gray-900 border-t border-industrial-gray-800 text-industrial-gray-400 py-4 mt-auto">
        <div class="container mx-auto px-4 flex flex-col md:flex-row justify-between items-center gap-2">
            <div class="text-[10px] font-mono uppercase tracking-widest">
                &copy; {{ date('Y') }} PrecogSystem. Todos os direitos reservados.
            </div>
            <div class="text-[10px] font-mono flex gap-4">
                <span>STATUS: <span class="text-hmi-green font-bold">ONLINE</span></span>
            </div>
        </div>
    </footer>

    <script>
        // Relógio
        setInterval(() => {
            const now = new Date();
            document.getElementById('clock').innerText = now.toLocaleTimeString('pt-BR');
        }, 1000);
        
        // Auto-refresh da página a cada 60s para atualizar Eventos e Status Offline/Online
        setTimeout(() => {
            window.location.reload();
        }, 60000);
        // Fetch de telemetria a cada 10s para os cards
        setInterval(() => {
            fetch("{{ route('api.telemetry') }}?token={{ $clientModel->token }}")
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
</body>
</html>
