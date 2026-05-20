@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h3 class="text-xs font-bold text-industrial-gray-900 uppercase tracking-widest flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-sm">groups</span> Gerenciar Clientes
    </h3>
    <a href="{{ route('clients.create') }}" class="bg-primary hover:bg-blue-700 text-white text-xs font-bold uppercase tracking-widest py-2 px-4 rounded transition-colors flex items-center gap-2 shadow-sm">
        <span class="material-symbols-outlined text-sm">add</span> Novo Cliente
    </a>
</div>

<div class="bg-white border border-industrial-gray-300 rounded shadow-sm overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-industrial-gray-50 border-b border-industrial-gray-200">
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">ID</th>
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Empresa / Nome</th>
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Token</th>
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Sensores</th>
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Status</th>
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest text-right">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-industrial-gray-200 text-sm">
            @forelse($clients as $client)
            <tr class="hover:bg-industrial-gray-50 transition-colors group">
                <td class="p-4 font-mono text-industrial-gray-500 text-xs">{{ $client->id }}</td>
                <td class="p-4">
                    <div class="flex flex-col">
                        <span class="font-bold text-industrial-gray-900">{{ $client->company ?: $client->name }}</span>
                        @if($client->company)
                            <span class="text-[10px] text-industrial-gray-500">{{ $client->name }}</span>
                        @endif
                    </div>
                </td>
                <td class="p-4">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs text-industrial-gray-500 truncate max-w-[120px]">{{ substr($client->token, 0, 16) }}...</span>
                        <button type="button" onclick="navigator.clipboard.writeText('{{ $client->token }}').then(() => { this.querySelector('span').textContent = 'check'; setTimeout(() => this.querySelector('span').textContent = 'content_copy', 1500); })" class="text-primary hover:text-blue-700" title="Copiar Token">
                            <span class="material-symbols-outlined text-sm">content_copy</span>
                        </button>
                    </div>
                </td>
                <td class="p-4 font-mono text-industrial-gray-900">{{ $client->sensors_count }}</td>
                <td class="p-4">
                    @if($client->is_active)
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-widest bg-green-100 text-green-800 border border-green-200">
                            <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div> Ativo
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-widest bg-gray-100 text-gray-800 border border-gray-200">
                            <div class="w-1.5 h-1.5 rounded-full bg-gray-500"></div> Inativo
                        </span>
                    @endif
                </td>
                <td class="p-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('client.dashboard', ['token' => $client->token]) }}" target="_blank" class="text-industrial-gray-400 hover:text-hmi-green transition-colors p-1" title="Visualizar Painel do Cliente">
                            <span class="material-symbols-outlined text-sm">visibility</span>
                        </a>
                        <a href="{{ route('clients.edit', $client) }}" class="text-industrial-gray-400 hover:text-primary transition-colors p-1" title="Editar">
                            <span class="material-symbols-outlined text-sm">edit</span>
                        </a>
                        <form action="{{ route('clients.destroy', $client) }}" method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja remover este cliente?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-industrial-gray-400 hover:text-hmi-red transition-colors p-1" title="Remover">
                                <span class="material-symbols-outlined text-sm">delete</span>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="p-8 text-center text-industrial-gray-500 text-sm">Nenhum cliente cadastrado.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
