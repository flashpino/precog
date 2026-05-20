@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h3 class="text-xs font-bold text-industrial-gray-900 uppercase tracking-widest flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-sm">person_add</span> Cadastrar Cliente
    </h3>
    <a href="{{ route('clients.index') }}" class="text-industrial-gray-500 hover:text-industrial-gray-900 text-xs font-bold uppercase tracking-widest flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">arrow_back</span> Voltar
    </a>
</div>

<div class="bg-white border border-industrial-gray-300 rounded shadow-sm p-6 max-w-2xl">
    <form action="{{ route('clients.store') }}" method="POST" class="space-y-6">
        @csrf
        
        <div class="space-y-4">
            <div>
                <label for="company" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Empresa / Razão Social</label>
                <input type="text" name="company" id="company" value="{{ old('company') }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm" placeholder="Ex: Indústria XYZ Ltda">
            </div>

            <div>
                <label for="name" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Nome do Responsável *</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm" placeholder="Ex: João Silva">
            </div>

            <div class="border-t border-industrial-gray-200 pt-4 mt-4 space-y-4">
                <h4 class="text-xs font-bold text-industrial-gray-700 uppercase tracking-wider mb-2 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">database</span> Configurações do InfluxDB e Alertas
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="influx_org" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">InfluxDB Org</label>
                        <input type="text" name="influx_org" id="influx_org" value="{{ old('influx_org') }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm" placeholder="Ex: Organizacao">
                    </div>
                    <div>
                        <label for="influx_bucket" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">InfluxDB Bucket</label>
                        <input type="text" name="influx_bucket" id="influx_bucket" value="{{ old('influx_bucket') }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm" placeholder="Ex: bucket_cliente">
                    </div>
                </div>

                <div>
                    <label for="influx_token" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">InfluxDB Token</label>
                    <textarea name="influx_token" id="influx_token" rows="2" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm font-mono" placeholder="Insira o Token do InfluxDB para este cliente...">{{ old('influx_token') }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="alert_interval_connectivity" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Intervalo de Verificação (Minutos)</label>
                        <input type="number" name="alert_interval_connectivity" id="alert_interval_connectivity" value="{{ old('alert_interval_connectivity', 5) }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm">
                    </div>
                    <div>
                        <label for="alert_interval_threshold" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Tolerância Offline (Minutos)</label>
                        <input type="number" name="alert_interval_threshold" id="alert_interval_threshold" value="{{ old('alert_interval_threshold', 5) }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm">
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-industrial-gray-100 flex justify-end">
            <button type="submit" class="bg-primary hover:bg-blue-700 text-white text-xs font-bold uppercase tracking-widest py-2 px-6 rounded transition-colors flex items-center gap-2 shadow-sm">
                <span class="material-symbols-outlined text-sm">save</span> Salvar Cliente
            </button>
        </div>
    </form>
</div>
@endsection
