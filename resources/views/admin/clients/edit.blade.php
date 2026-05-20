@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h3 class="text-xs font-bold text-industrial-gray-900 uppercase tracking-widest flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-sm">edit</span> Editar Cliente: {{ $client->name }}
    </h3>
    <a href="{{ route('clients.index') }}" class="text-industrial-gray-500 hover:text-industrial-gray-900 text-xs font-bold uppercase tracking-widest flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">arrow_back</span> Voltar
    </a>
</div>

<div class="bg-white border border-industrial-gray-300 rounded shadow-sm p-6 max-w-2xl">
    <form action="{{ route('clients.update', $client) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')
        
        <div class="space-y-4">
            <div>
                <label class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Token de Acesso (Leitura)</label>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ $client->token }}" class="w-full bg-industrial-gray-50 border-industrial-gray-300 rounded text-industrial-gray-500 text-sm font-mono cursor-not-allowed">
                </div>
            </div>

            <div>
                <label for="company" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Empresa / Razão Social</label>
                <input type="text" name="company" id="company" value="{{ old('company', $client->company) }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm">
            </div>

            <div>
                <label for="name" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Nome do Responsável *</label>
                <input type="text" name="name" id="name" value="{{ old('name', $client->name) }}" required class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm">
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" value="1" {{ $client->is_active ? 'checked' : '' }} class="rounded border-industrial-gray-300 text-primary focus:ring-primary">
                <label for="is_active" class="text-sm font-bold text-industrial-gray-700">Cliente Ativo no Sistema</label>
            </div>

            <div class="border-t border-industrial-gray-200 pt-4 mt-4 space-y-4">
                <h4 class="text-xs font-bold text-industrial-gray-700 uppercase tracking-wider mb-2 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">database</span> Configurações do InfluxDB e Alertas
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="influx_org" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">InfluxDB Org</label>
                        <input type="text" name="influx_org" id="influx_org" value="{{ old('influx_org', $client->influx_org) }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm" placeholder="Ex: Organizacao">
                    </div>
                    <div>
                        <label for="influx_bucket" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">InfluxDB Bucket</label>
                        <input type="text" name="influx_bucket" id="influx_bucket" value="{{ old('influx_bucket', $client->influx_bucket) }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm" placeholder="Ex: bucket_cliente">
                    </div>
                </div>

                <div>
                    <label for="influx_token" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">InfluxDB Token</label>
                    <textarea name="influx_token" id="influx_token" rows="2" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm font-mono" placeholder="Insira o Token do InfluxDB para este cliente...">{{ old('influx_token', $client->influx_token) }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="alert_interval_connectivity" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Intervalo de Verificação (Minutos)</label>
                        <input type="number" name="alert_interval_connectivity" id="alert_interval_connectivity" value="{{ old('alert_interval_connectivity', $client->alert_interval_connectivity ?? 5) }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm">
                    </div>
                    <div>
                        <label for="alert_interval_threshold" class="block text-[10px] font-bold text-industrial-gray-600 uppercase tracking-widest mb-1">Tolerância Offline (Minutos)</label>
                        <input type="number" name="alert_interval_threshold" id="alert_interval_threshold" value="{{ old('alert_interval_threshold', $client->alert_interval_threshold ?? 5) }}" class="w-full border-industrial-gray-300 rounded focus:border-primary focus:ring-primary text-sm">
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-industrial-gray-100 flex justify-end">
            <button type="submit" class="bg-primary hover:bg-blue-700 text-white text-xs font-bold uppercase tracking-widest py-2 px-6 rounded transition-colors flex items-center gap-2 shadow-sm">
                <span class="material-symbols-outlined text-sm">save</span> Atualizar Cliente
            </button>
        </div>
    </form>
</div>

<div class="mt-8">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-xs font-bold text-industrial-gray-900 uppercase tracking-widest flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-sm">contacts</span> Contatos para Alertas
        </h3>
        <button type="button" onclick="openContactModal('create')" class="bg-primary hover:bg-blue-700 text-white text-[10px] font-bold uppercase tracking-widest py-1.5 px-3 rounded transition-colors flex items-center gap-1 shadow-sm">
            <span class="material-symbols-outlined text-[12px]">add</span> Novo Contato
        </button>
    </div>

    <div class="bg-white border border-industrial-gray-300 rounded shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-industrial-gray-50 border-b border-industrial-gray-200">
                    <th class="p-3 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Nome</th>
                    <th class="p-3 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Telefone</th>
                    <th class="p-3 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest">Status</th>
                    <th class="p-3 text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-industrial-gray-200 text-sm">
                @forelse($client->contacts as $contact)
                <tr class="hover:bg-industrial-gray-50 transition-colors group">
                    <td class="p-3 font-bold text-industrial-gray-900">{{ $contact->name }}</td>
                    <td class="p-3 font-mono text-industrial-gray-500 text-xs">{{ $contact->phone }}</td>
                    <td class="p-3">
                        @if($contact->is_active)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest bg-green-100 text-green-800 border border-green-200">
                                <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div> Ativo
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest bg-gray-100 text-gray-800 border border-gray-200">
                                <div class="w-1.5 h-1.5 rounded-full bg-gray-500"></div> Inativo
                            </span>
                        @endif
                    </td>
                    <td class="p-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button type="button" onclick='openContactModal("edit", @json($contact))' class="bg-white border border-industrial-gray-300 text-industrial-gray-600 hover:text-primary hover:bg-industrial-gray-50 px-2 py-1 rounded text-[9px] font-bold uppercase tracking-widest transition-colors inline-flex items-center gap-1">
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
                    <td colspan="4" class="p-6 text-center text-industrial-gray-500 text-xs">Nenhum contato cadastrado para este cliente.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="contact-modal" class="fixed inset-0 bg-industrial-gray-900/50 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
    <div class="bg-white rounded shadow-lg max-w-lg w-full p-6 border border-industrial-gray-200 max-h-[90vh] overflow-y-auto">
        <h2 id="modal-title" class="text-lg font-bold text-industrial-gray-900 mb-4 uppercase tracking-tight">Novo Contato</h2>
        
        <form id="contact-form" method="POST" action="{{ route('contacts.store') }}">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            
            <div class="flex gap-4 mb-4">
                <div class="flex-1">
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-1">Nome *</label>
                    <input type="text" name="name" id="m-name" class="w-full border border-industrial-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary" placeholder="João Silva" required>
                </div>
                <div class="flex-1">
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-1">Telefone (WhatsApp) *</label>
                    <input type="text" name="phone" id="m-phone" class="w-full border border-industrial-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary" placeholder="+5511999999999" required>
                </div>
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
                <button type="button" onclick="closeContactModal()" class="bg-white border border-industrial-gray-300 text-industrial-gray-600 hover:bg-industrial-gray-50 px-4 py-2 rounded text-[10px] font-bold uppercase tracking-widest transition-colors">
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
        const el = document.getElementById('pref-' + type + '-enabled');
        if(el) {
            el.checked = false;
            togglePrefDetails(type);
        }
        for(let i=0; i<=6; i++) {
            const dEl = document.getElementById('pref-' + type + '-d' + i);
            if(dEl) dEl.checked = true;
        }
        const sEl = document.getElementById('pref-' + type + '-start');
        const eEl = document.getElementById('pref-' + type + '-end');
        const iEl = document.getElementById('pref-' + type + '-interval');
        if(sEl) sEl.value = '';
        if(eEl) eEl.value = '';
        if(iEl) iEl.value = '30';
    });
}

function openContactModal(mode, data = {}) {
    const modal = document.getElementById('contact-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    resetPrefs();
    
    const form = document.getElementById('contact-form');
    const title = document.getElementById('modal-title');
    const method = document.getElementById('form-method');
    
    if (mode === 'edit') {
        title.textContent = 'Editar Contato';
        form.action = `{{ url('contacts') }}/${data.id}`;
        method.value = 'PUT';
        
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
        
        document.getElementById('m-name').value = '';
        document.getElementById('m-phone').value = '';
        document.getElementById('fg-active').classList.add('hidden');
        document.getElementById('m-active').checked = true;
    }
}

function closeContactModal() {
    const modal = document.getElementById('contact-modal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}

document.getElementById('contact-modal').addEventListener('click', e => {
    if (e.target.id === 'contact-modal') closeContactModal();
});
</script>
@endsection
