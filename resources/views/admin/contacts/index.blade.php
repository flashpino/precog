@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h3 class="text-xs font-bold text-industrial-gray-900 uppercase tracking-widest flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-sm">contacts</span> Contatos para Alertas
    </h3>
    <button onclick="openModal('create')" class="bg-primary hover:bg-blue-700 text-white text-xs font-bold uppercase tracking-widest py-2 px-4 rounded transition-colors flex items-center gap-2 shadow-sm">
        <span class="material-symbols-outlined text-sm">add</span> Novo Contato
    </button>
</div>

<p class="text-xs text-industrial-gray-500 mb-6 uppercase tracking-widest font-bold">Pessoas que receberão notificações de alertas</p>

<!-- Filtro por cliente -->
<div class="mb-4 flex items-center gap-3">
    <label class="text-xs font-bold text-industrial-gray-500 uppercase tracking-widest">Filtrar por cliente:</label>
    <select class="border border-industrial-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-primary text-industrial-gray-800 font-display min-w-[200px]" onchange="location.href='{{ route('contacts.index') }}' + (this.value ? '?client_id=' + this.value : '')">
        <option value="">Todos</option>
        @foreach($clients as $client)
            <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>{{ $client->name }} {{ $client->company ? '('.$client->company.')' : '' }}</option>
        @endforeach
    </select>
</div>

<div class="bg-white border border-industrial-gray-300 rounded shadow-sm overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-industrial-gray-50 border-b border-industrial-gray-200">
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Nome</th>
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Telefone</th>
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Cliente</th>
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Status</th>
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Criado em</th>
                <th class="p-4 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest text-right">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-industrial-gray-200 text-sm">
            @forelse($contacts as $contact)
            <tr class="hover:bg-industrial-gray-50 transition-colors group">
                <td class="p-4 font-bold text-industrial-gray-900">{{ $contact->name }}</td>
                <td class="p-4 font-mono text-industrial-gray-500 text-xs">{{ $contact->phone }}</td>
                <td class="p-4 text-industrial-gray-600">{{ $contact->client->name ?? 'Desconhecido' }}</td>
                <td class="p-4">
                    @if($contact->is_active)
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-widest bg-green-100 text-green-800 border border-green-200">
                            <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div> Ativo
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-widest bg-gray-100 text-gray-800 border border-gray-200">
                            <div class="w-1.5 h-1.5 rounded-full bg-gray-500"></div> Inativo
                        </span>
                    @endif
                </td>
                <td class="p-4 text-industrial-gray-500 text-xs">{{ $contact->created_at->format('d/m/Y H:i') }}</td>
                <td class="p-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <button onclick='openModal("edit", @json($contact))' class="bg-white border border-industrial-gray-300 text-industrial-gray-600 hover:text-primary hover:bg-industrial-gray-50 px-2 py-1 rounded text-[9px] font-bold uppercase tracking-widest transition-colors inline-flex items-center gap-1">
                            Editar
                        </button>
                        <form action="{{ route('contacts.destroy', $contact) }}" method="POST" class="inline" onsubmit="return confirm('Excluir este contato?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="bg-white border border-hmi-red text-hmi-red hover:bg-red-50 px-2 py-1 rounded text-[9px] font-bold uppercase tracking-widest transition-colors inline-flex items-center gap-1">
                                Excluir
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="p-8 text-center text-industrial-gray-500 text-sm">Nenhum contato cadastrado.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="modal" class="fixed inset-0 bg-industrial-gray-900/50 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
    <div class="bg-white rounded shadow-lg max-w-lg w-full p-6 border border-industrial-gray-200 max-h-[90vh] overflow-y-auto">
        <h2 id="modal-title" class="text-lg font-bold text-industrial-gray-900 mb-4 uppercase tracking-tight">Novo Contato</h2>
        
        <form id="contact-form" method="POST" action="{{ route('contacts.store') }}">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">
            
            <div class="flex gap-4 mb-4">
                <div class="flex-1">
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-1">Cliente *</label>
                    <select name="client_id" id="m-client" class="w-full border border-industrial-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary bg-white text-industrial-gray-800 font-display" required>
                        <option value="">Selecione...</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }} {{ $client->company ? '('.$client->company.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-1">Telefone (WhatsApp) *</label>
                    <input type="text" name="phone" id="m-phone" class="w-full border border-industrial-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary" placeholder="+5511999999999" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-1">Nome *</label>
                <input type="text" name="name" id="m-name" class="w-full border border-industrial-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary" placeholder="João Silva" required>
            </div>

            <hr class="border-industrial-gray-200 my-6">
            <h3 class="text-sm font-bold text-industrial-gray-900 mb-2 uppercase tracking-tight">Preferências de Alertas</h3>
            <p class="text-xs text-industrial-gray-500 mb-4">Selecione quais alertas este contato deseja receber. Se não marcar, receberá tudo por padrão.</p>

            @php
            $alertTypes = [
                'connectivity' => 'Conectividade (Online/Offline)',
                'temperature'  => 'Temperatura',
                'humidity'     => 'Umidade'
            ];
            @endphp

            @foreach($alertTypes as $type => $label)
            <div class="bg-industrial-gray-50 border border-industrial-gray-200 p-4 rounded mb-4">
                <div class="mb-2">
                    <label class="font-bold text-sm text-industrial-gray-900 flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="prefs[{{ $type }}][enabled]" id="pref-{{ $type }}-enabled" value="1" onchange="togglePrefDetails('{{ $type }}')" class="rounded border-industrial-gray-300 text-primary focus:ring-primary">
                        {{ $label }}
                    </label>
                </div>
                
                <div id="pref-{{ $type }}-details" class="hidden pl-6 border-l-2 border-industrial-gray-200 mt-3">
                    <div class="mb-3">
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-2">Dias de Envio</label>
                        <div class="flex gap-3 flex-wrap text-xs text-industrial-gray-700">
                            <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" name="prefs[{{ $type }}][days][]" id="pref-{{ $type }}-d1" value="1" class="rounded border-industrial-gray-300 text-primary"> Seg</label>
                            <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" name="prefs[{{ $type }}][days][]" id="pref-{{ $type }}-d2" value="2" class="rounded border-industrial-gray-300 text-primary"> Ter</label>
                            <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" name="prefs[{{ $type }}][days][]" id="pref-{{ $type }}-d3" value="3" class="rounded border-industrial-gray-300 text-primary"> Qua</label>
                            <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" name="prefs[{{ $type }}][days][]" id="pref-{{ $type }}-d4" value="4" class="rounded border-industrial-gray-300 text-primary"> Qui</label>
                            <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" name="prefs[{{ $type }}][days][]" id="pref-{{ $type }}-d5" value="5" class="rounded border-industrial-gray-300 text-primary"> Sex</label>
                            <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" name="prefs[{{ $type }}][days][]" id="pref-{{ $type }}-d6" value="6" class="rounded border-industrial-gray-300 text-primary"> Sáb</label>
                            <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" name="prefs[{{ $type }}][days][]" id="pref-{{ $type }}-d0" value="0" class="rounded border-industrial-gray-300 text-primary"> Dom</label>
                        </div>
                    </div>
                    
                    <div class="flex gap-4">
                        <div class="flex-2">
                            <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-1">Horário</label>
                            <div class="flex items-center gap-2">
                                <input type="time" name="prefs[{{ $type }}][start]" id="pref-{{ $type }}-start" class="border border-industrial-gray-300 rounded p-1 text-sm focus:ring-primary">
                                <span class="text-industrial-gray-500">-</span>
                                <input type="time" name="prefs[{{ $type }}][end]" id="pref-{{ $type }}-end" class="border border-industrial-gray-300 rounded p-1 text-sm focus:ring-primary">
                            </div>
                        </div>
                        <div class="flex-1">
                            <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-1">Intervalo (min)</label>
                            <input type="number" name="prefs[{{ $type }}][interval]" id="pref-{{ $type }}-interval" class="w-full border border-industrial-gray-300 rounded p-1 text-sm focus:ring-primary" value="30" min="1">
                        </div>
                    </div>
                </div>
            </div>
            @endforeach

            <div id="fg-active" class="hidden mt-4">
                <label class="font-bold text-sm text-industrial-gray-900 flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" id="m-active" value="1" class="rounded border-industrial-gray-300 text-primary focus:ring-primary">
                    Contato Ativo
                </label>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeModal()" class="bg-white border border-industrial-gray-300 text-industrial-gray-600 hover:bg-industrial-gray-50 px-4 py-2 rounded text-[10px] font-bold uppercase tracking-widest transition-colors">
                    Cancelar
                </button>
                <button type="submit" class="bg-primary hover:bg-blue-700 text-white px-4 py-2 rounded text-[10px] font-bold uppercase tracking-widest transition-colors shadow-sm">
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePrefDetails(type) {
    const isChecked = document.getElementById('pref-' + type + '-enabled').checked;
    const details = document.getElementById('pref-' + type + '-details');
    if (isChecked) {
        details.classList.remove('hidden');
    } else {
        details.classList.add('hidden');
    }
}

function resetPrefs() {
    ['connectivity', 'temperature', 'humidity'].forEach(type => {
        document.getElementById('pref-' + type + '-enabled').checked = false;
        togglePrefDetails(type);
        for(let i=0; i<=6; i++) {
            document.getElementById('pref-' + type + '-d' + i).checked = true;
        }
        document.getElementById('pref-' + type + '-start').value = '';
        document.getElementById('pref-' + type + '-end').value = '';
        document.getElementById('pref-' + type + '-interval').value = '30';
    });
}

function openModal(mode, data = {}) {
    const modal = document.getElementById('modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    resetPrefs();
    
    const form = document.getElementById('contact-form');
    const title = document.getElementById('modal-title');
    const method = document.getElementById('form-method');
    
    if (mode === 'edit') {
        title.textContent = 'Editar Contato';
        form.action = `/admin-panel/contacts/${data.id}`; // Adjusted by route resource
        method.value = 'PUT';
        
        document.getElementById('m-client').value = data.client_id;
        document.getElementById('m-name').value = data.name;
        document.getElementById('m-phone').value = data.phone;
        
        const mActive = document.getElementById('m-active');
        mActive.checked = data.is_active == 1;
        document.getElementById('fg-active').classList.remove('hidden');
        
        if (data.alert_preferences) {
            data.alert_preferences.forEach(p => {
                const type = p.alert_type;
                const enabledCb = document.getElementById('pref-' + type + '-enabled');
                if(enabledCb) {
                    enabledCb.checked = true;
                    togglePrefDetails(type);
                    
                    let days = p.days_of_week ? p.days_of_week.split(',') : [];
                    for(let i=0; i<=6; i++) {
                        let dCb = document.getElementById('pref-' + type + '-d' + i);
                        if(dCb) dCb.checked = days.includes(i.toString());
                    }
                    
                    document.getElementById('pref-' + type + '-start').value = (p.time_start && p.time_start != '00:00:00') ? p.time_start.substring(0,5) : '';
                    document.getElementById('pref-' + type + '-end').value = (p.time_end && p.time_end != '23:59:00') ? p.time_end.substring(0,5) : '';
                    document.getElementById('pref-' + type + '-interval').value = p.min_interval || 30;
                }
            });
        }
    } else {
        title.textContent = 'Novo Contato';
        form.action = "{{ route('contacts.store') }}";
        method.value = 'POST';
        
        document.getElementById('m-client').value = '';
        document.getElementById('m-name').value = '';
        document.getElementById('m-phone').value = '';
        document.getElementById('fg-active').classList.add('hidden');
        document.getElementById('m-active').checked = true;
    }
}

function closeModal() {
    const modal = document.getElementById('modal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}

document.getElementById('modal').addEventListener('click', e => {
    if (e.target.id === 'modal') closeModal();
});
</script>
@endsection
