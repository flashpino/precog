@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h3 class="text-xs font-bold text-industrial-gray-900 uppercase tracking-widest flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-sm">sensors</span> Gerenciar Sensores
    </h3>
    <a href="{{ route('sensors.create') }}" class="bg-primary hover:bg-blue-700 text-white text-xs font-bold uppercase tracking-widest py-2 px-4 rounded transition-colors flex items-center gap-2 shadow-sm">
        <span class="material-symbols-outlined text-sm">add</span> Novo Sensor
    </a>
</div>

<div class="bg-white border border-industrial-gray-300 rounded shadow-sm overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-industrial-gray-50 border-b border-industrial-gray-200">
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Device ID</th>
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Cliente</th>
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Label / Local</th>
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Limites (Temp / Hum)</th>
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Status / Last Seen</th>
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest text-right">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-industrial-gray-200 text-sm">
            @forelse($sensors as $sensor)
            <tr class="hover:bg-industrial-gray-50 transition-colors group">
                <td class="p-4 font-mono text-industrial-gray-900 font-bold text-xs">{{ $sensor->device_id }}</td>
                <td class="p-4">
                    <span class="text-industrial-gray-800">{{ $sensor->client->company ?: $sensor->client->name }}</span>
                </td>
                <td class="p-4">
                    <div class="flex flex-col">
                        <span class="font-bold text-industrial-gray-900">{{ $sensor->label ?: '-' }}</span>
                        <span class="text-[10px] text-industrial-gray-500">{{ $sensor->location ?: 'Local não definido' }}</span>
                    </div>
                </td>
                <td class="p-4 font-mono text-xs text-industrial-gray-600">
                    <div>T: {{ $sensor->temp_min }}°C ~ {{ $sensor->temp_max }}°C</div>
                    <div>H: {{ $sensor->hum_min }}% ~ {{ $sensor->hum_max }}%</div>
                </td>
                <td class="p-4">
                    <div class="flex flex-col gap-1">
                        @if($sensor->is_active)
                            @if($sensor->last_status === 'offline')
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-widest bg-yellow-100 text-yellow-800 border border-yellow-200 w-fit">
                                    <div class="w-1.5 h-1.5 rounded-full bg-yellow-500"></div> Offline
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-widest bg-green-100 text-green-800 border border-green-200 w-fit">
                                    <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div> Online
                                </span>
                            @endif
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-widest bg-gray-100 text-gray-800 border border-gray-200 w-fit">
                                <div class="w-1.5 h-1.5 rounded-full bg-gray-500"></div> Inativo
                            </span>
                        @endif
                        <span class="text-[9px] font-mono text-industrial-gray-400 mt-1">
                            {{ $sensor->last_seen ? $sensor->last_seen->diffForHumans() : 'Nunca visto' }}
                        </span>
                    </div>
                </td>
                <td class="p-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('sensors.edit', $sensor) }}" class="text-industrial-gray-400 hover:text-primary transition-colors p-1" title="Editar">
                            <span class="material-symbols-outlined text-sm">edit</span>
                        </a>
                        <form action="{{ route('sensors.destroy', $sensor) }}" method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja remover este sensor?');">
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
                <td colspan="6" class="p-8 text-center text-industrial-gray-500 text-sm">Nenhum sensor cadastrado.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
