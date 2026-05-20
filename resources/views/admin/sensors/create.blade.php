@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h3 class="text-xs font-bold text-industrial-gray-900 uppercase tracking-widest flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-sm">add_circle</span> Cadastrar Equipamento (Sensor)
    </h3>
    <a href="{{ route('sensors.index') }}" class="text-industrial-gray-500 hover:text-industrial-gray-900 text-xs font-bold uppercase tracking-widest flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">arrow_back</span> Voltar
    </a>
</div>

<div class="bg-white border border-industrial-gray-300 rounded shadow-sm p-6 max-w-4xl">
    <form action="{{ route('sensors.store') }}" method="POST" class="space-y-6">
        @csrf
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Coluna 1 -->
            <div class="space-y-4">
                <div>
                    <label for="client_id" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Cliente Vinculado *</label>
                    <select name="client_id" id="client_id" required class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm">
                        <option value="">Selecione um cliente...</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" data-token="{{ $client->influx_token }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                {{ $client->company ?: $client->name }} (ID: {{ $client->id }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="device_id" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">ID do Dispositivo (Device ID) *</label>
                    <input type="text" name="device_id" id="device_id" value="{{ old('device_id') }}" required class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary font-mono text-sm uppercase" placeholder="Ex: PRECOG_ESTOQUE_01">
                    <p class="text-[10px] text-industrial-gray-400 mt-1">Deve ser exatamente o mesmo configurado no ESP32.</p>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Token do InfluxDB</label>
                    <div class="flex items-center gap-2">
                        <input type="text" id="influx_token_input" readonly value="" class="w-full bg-industrial-gray-50 border border-industrial-gray-300 rounded text-sm font-mono text-industrial-gray-500 py-2 px-3 focus:outline-none cursor-pointer" onclick="this.select()" title="Clique para selecionar tudo">
                        <button type="button" id="copy_token_btn" class="bg-industrial-gray-200 hover:bg-industrial-gray-300 text-industrial-gray-700 text-xs font-bold py-2 px-3 rounded flex items-center gap-1 transition-colors" title="Copiar Token">
                            <span class="material-symbols-outlined text-sm">content_copy</span>
                        </button>
                    </div>
                    <p class="text-[10px] text-industrial-gray-400 mt-1">Token de autenticação necessário para o ESP32 enviar dados ao InfluxDB.</p>
                </div>

                <div>
                    <label for="label" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Nome de Exibição / Rótulo</label>
                    <input type="text" name="label" id="label" value="{{ old('label') }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm" placeholder="Ex: Sensor Estoque A">
                </div>

                <div>
                    <label for="location" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Localização Física</label>
                    <input type="text" name="location" id="location" value="{{ old('location') }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm" placeholder="Ex: Galpão 3 - Corredor B">
                </div>
            </div>

            <!-- Coluna 2 (Limites) -->
            <div class="space-y-4 bg-industrial-gray-50 p-4 rounded border border-industrial-gray-200">
                <h4 class="text-xs font-bold text-industrial-gray-800 uppercase tracking-widest border-b border-industrial-gray-200 pb-2 mb-4">Limites de Alerta</h4>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="temp_min" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Temp. Mínima (°C)</label>
                        <input type="number" step="0.1" name="temp_min" id="temp_min" value="{{ old('temp_min', '2.0') }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm">
                    </div>
                    <div>
                        <label for="temp_max" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Temp. Máxima (°C)</label>
                        <input type="number" step="0.1" name="temp_max" id="temp_max" value="{{ old('temp_max', '8.0') }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 pt-2">
                    <div>
                        <label for="hum_min" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Umidade Mínima (%)</label>
                        <input type="number" step="0.1" name="hum_min" id="hum_min" value="{{ old('hum_min', '0.0') }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm">
                    </div>
                    <div>
                        <label for="hum_max" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Umidade Máxima (%)</label>
                        <input type="number" step="0.1" name="hum_max" id="hum_max" value="{{ old('hum_max', '100.0') }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm">
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-industrial-gray-100 flex justify-end">
            <button type="submit" class="bg-primary hover:bg-blue-700 text-white text-xs font-bold uppercase tracking-widest py-2 px-6 rounded transition-colors flex items-center gap-2 shadow-sm">
                <span class="material-symbols-outlined text-sm">save</span> Salvar Equipamento
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('client_id');
    const input = document.getElementById('influx_token_input');
    const copyBtn = document.getElementById('copy_token_btn');

    function updateInfluxToken() {
        const selectedOption = select.options[select.selectedIndex];
        const token = selectedOption ? selectedOption.getAttribute('data-token') : '';
        input.value = token || 'Nenhum token configurado para este cliente';
    }

    if (select) {
        select.addEventListener('change', updateInfluxToken);
        updateInfluxToken();
    }

    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            const token = input.value;
            if (token && token !== 'Nenhum token configurado para este cliente') {
                navigator.clipboard.writeText(token).then(() => {
                    const originalText = copyBtn.innerHTML;
                    copyBtn.innerHTML = '<span class="material-symbols-outlined text-sm text-green-600">check</span>';
                    setTimeout(() => {
                        copyBtn.innerHTML = originalText;
                    }, 1500);
                }).catch(err => {
                    console.error('Falha ao copiar token:', err);
                });
            }
        });
    }
});
</script>
@endsection
