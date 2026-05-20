@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-industrial-gray-900 tracking-tight uppercase">Configurações do Sistema</h1>
</div>

@if (session('success'))
    <div class="p-3 rounded mb-4 text-xs font-bold uppercase tracking-widest border bg-green-50 text-hmi-green border-green-200">
        {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div class="p-3 rounded mb-4 text-xs font-bold uppercase tracking-widest border bg-red-50 text-hmi-red border-red-200">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
    <!-- Alterar Senha -->
    <div class="bg-white border border-industrial-gray-200 rounded shadow-sm">
        <div class="bg-industrial-gray-50 border-b border-industrial-gray-200 p-4 flex items-center gap-2">
            <span>🔐</span>
            <h3 class="text-sm font-bold text-industrial-gray-800 uppercase tracking-widest">Alterar Minha Senha</h3>
        </div>
        <div class="p-6">
            <form action="{{ route('settings.password.update') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-1">Senha Atual</label>
                    <input type="password" name="current_password" class="w-full border border-industrial-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary bg-white text-industrial-gray-800 font-display" required>
                </div>
                <div class="mb-4">
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-1">Nova Senha</label>
                    <input type="password" name="new_password" class="w-full border border-industrial-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary bg-white text-industrial-gray-800 font-display" required minlength="6">
                </div>
                <div class="mb-6">
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-1">Confirmar Nova Senha</label>
                    <input type="password" name="new_password_confirmation" class="w-full border border-industrial-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary bg-white text-industrial-gray-800 font-display" required minlength="6">
                </div>
                <button type="submit" class="w-full bg-primary text-white hover:bg-blue-700 border border-transparent px-4 py-2 rounded text-[10px] font-bold uppercase tracking-widest transition-colors cursor-pointer inline-flex items-center justify-center gap-1">Atualizar Senha</button>
            </form>
        </div>
    </div>

    <!-- Informações do Sistema -->
    <div class="bg-white border border-industrial-gray-200 rounded shadow-sm h-fit">
        <div class="bg-industrial-gray-50 border-b border-industrial-gray-200 p-4 flex items-center gap-2">
            <span>ℹ️</span>
            <h3 class="text-sm font-bold text-industrial-gray-800 uppercase tracking-widest">Informações do Sistema</h3>
        </div>
        <div class="p-6 text-industrial-gray-600 text-sm">
            <p class="mb-2"><strong>Versão:</strong> 1.0.0 (Laravel)</p>
            <p class="mb-2"><strong>PHP:</strong> {{ PHP_VERSION }}</p>
            <p class="mb-2"><strong>InfluxDB:</strong> {{ config('database.connections.influxdb.url', env('INFLUXDB_URL')) }}</p>
            <p class="mb-2"><strong>Webhook n8n:</strong> {{ env('N8N_WEBHOOK_URL') ?: 'Não configurado' }}</p>
            
            <div class="mt-6 border-t border-industrial-gray-200 pt-4">
                <h4 class="text-xs font-bold text-industrial-gray-800 uppercase tracking-widest mb-3 flex items-center gap-1">
                    <span>🗄️</span> Banco de Dados
                </h4>
                <form action="{{ route('settings.migrate') }}" method="POST" onsubmit="return confirm('Tem certeza que deseja executar as migrações pendentes do banco de dados?');">
                    @csrf
                    <button type="submit" class="bg-industrial-gray-800 hover:bg-industrial-gray-900 text-white border border-transparent px-3 py-1.5 rounded text-[10px] font-bold uppercase tracking-widest transition-colors cursor-pointer inline-flex items-center gap-1 shadow-sm">
                        <span>🚀</span> Executar Migrações do Sistema
                    </button>
                </form>
            </div>

            <div class="mt-6 border-t border-industrial-gray-200 pt-4">
                <h4 class="text-xs font-bold text-industrial-gray-800 uppercase tracking-widest mb-3 flex items-center gap-1">
                    <span>⚡</span> Otimização e Cache
                </h4>
                <form action="{{ route('settings.clear-cache') }}" method="POST" onsubmit="return confirm('Tem certeza que deseja limpar o cache do sistema? Isso ajudará se alguma alteração no arquivo .env ou de configuração não estiver refletindo na produção.');">
                    @csrf
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white border border-transparent px-3 py-1.5 rounded text-[10px] font-bold uppercase tracking-widest transition-colors cursor-pointer inline-flex items-center gap-1 shadow-sm">
                        <span>🧹</span> Limpar Cache do Sistema
                    </button>
                </form>
            </div>

            <p class="mt-4 text-industrial-gray-400">
                Desenvolvido para monitoramento de ambientes críticos via ESP32.
            </p>
        </div>
    </div>
</div>
@endsection
