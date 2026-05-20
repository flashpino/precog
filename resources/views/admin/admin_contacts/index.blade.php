@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-industrial-gray-900 tracking-tight uppercase">Contatos Administrativos</h1>
        <p class="text-industrial-gray-500 text-[0.85rem] mt-1">Pessoas que receberão notificações via n8n para eventos do sistema e alertas</p>
    </div>
    <button class="bg-primary text-white hover:bg-blue-700 border border-transparent px-4 py-2 rounded text-[10px] font-bold uppercase tracking-widest transition-colors cursor-pointer inline-flex items-center justify-center gap-1" onclick="openModal('create')">
        <span class="material-symbols-outlined text-sm">add</span> Novo Contato
    </button>
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

<div class="bg-white border border-industrial-gray-200 rounded shadow-sm overflow-hidden">
    <table class="w-full text-left border-collapse text-sm text-industrial-gray-800">
        <thead>
            <tr>
                <th class="bg-industrial-gray-100 p-3 font-bold uppercase tracking-widest text-[10px] text-industrial-gray-500 border-b border-industrial-gray-200">Nome</th>
                <th class="bg-industrial-gray-100 p-3 font-bold uppercase tracking-widest text-[10px] text-industrial-gray-500 border-b border-industrial-gray-200">Telefone</th>
                <th class="bg-industrial-gray-100 p-3 font-bold uppercase tracking-widest text-[10px] text-industrial-gray-500 border-b border-industrial-gray-200">Status</th>
                <th class="bg-industrial-gray-100 p-3 font-bold uppercase tracking-widest text-[10px] text-industrial-gray-500 border-b border-industrial-gray-200">Criado em</th>
                <th class="bg-industrial-gray-100 p-3 font-bold uppercase tracking-widest text-[10px] text-industrial-gray-500 border-b border-industrial-gray-200 text-right">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contacts as $co)
            <tr class="hover:bg-industrial-gray-50">
                <td class="p-3 border-b border-industrial-gray-100"><strong>{{ $co->name }}</strong></td>
                <td class="p-3 border-b border-industrial-gray-100 font-mono text-[0.9rem]">{{ $co->phone }}</td>
                <td class="p-3 border-b border-industrial-gray-100">
                    <span class="px-2 py-1 rounded text-[10px] font-bold uppercase tracking-widest border {{ $co->is_active ? 'bg-green-50 text-hmi-green border-green-200' : 'bg-red-50 text-hmi-red border-red-200' }}">
                        {{ $co->is_active ? 'Ativo' : 'Inativo' }}
                    </span>
                </td>
                <td class="p-3 border-b border-industrial-gray-100 text-xs text-industrial-gray-500">{{ $co->created_at->format('d/m/Y H:i') }}</td>
                <td class="p-3 border-b border-industrial-gray-100 text-right">
                    <button class="bg-white border border-industrial-gray-300 text-industrial-gray-600 hover:bg-industrial-gray-50 px-2 py-1 rounded text-[9px] font-bold uppercase tracking-widest transition-colors cursor-pointer inline-flex items-center justify-center" onclick="openModal('edit', {{ json_encode($co) }})">Editar</button>
                    <form action="{{ route('admin-contacts.destroy', $co->id) }}" method="POST" class="inline" onsubmit="return confirm('Excluir este administrador?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="bg-white border border-hmi-red text-hmi-red hover:bg-red-50 px-2 py-1 rounded text-[9px] font-bold uppercase tracking-widest transition-colors cursor-pointer inline-flex items-center justify-center">Excluir</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="p-8 text-center text-industrial-gray-500">Nenhum administrador cadastrado</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="modal" class="fixed inset-0 bg-industrial-gray-900/50 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
    <div class="bg-white rounded shadow-lg max-w-2xl w-full p-6 border border-industrial-gray-200 max-h-[90vh] overflow-y-auto">
        <h2 id="modal-title" class="text-lg font-bold text-industrial-gray-900 mb-4 uppercase tracking-tight">Novo Contato</h2>
        <form id="contact-form" method="POST" action="{{ route('admin-contacts.store') }}">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">
            
            <div class="mb-4">
                <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-1">Telefone (WhatsApp) *</label>
                <input type="text" name="phone" id="m-phone" class="w-full border border-industrial-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary bg-white text-industrial-gray-800 font-display" placeholder="+5511999999999" required>
            </div>

            <div class="mb-4">
                <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-1">Nome *</label>
                <input type="text" name="name" id="m-name" class="w-full border border-industrial-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary bg-white text-industrial-gray-800 font-display" placeholder="João Silva" required>
            </div>

            <hr class="my-6 border-industrial-gray-200">
            <h3 class="mb-2 text-md font-bold text-industrial-gray-800 uppercase tracking-widest">Preferências de Alertas</h3>
            <p class="text-xs text-industrial-gray-500 mb-4">Selecione quais alertas este administrador deseja receber. (Se não selecionar nenhum, ele receberá alertas gerais do sistema).</p>

            @php
            $alertTypes = [
                'connectivity' => 'Conectividade (Online / Offline / Queda)',
                'temperature'  => 'Temperatura (Alta / Baixa / Normalizou)',
                'humidity'     => 'Umidade (Alta / Baixa / Normalizou)'
            ];
            @endphp
            
            @foreach ($alertTypes as $type => $label)
            <div class="bg-industrial-gray-50 border border-industrial-gray-200 p-4 rounded mb-4">
                <div class="mb-2">
                    <label class="font-bold text-sm text-industrial-gray-800 flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="prefs[{{ $type }}][enabled]" id="pref-{{ $type }}-enabled" value="1" onchange="togglePrefDetails('{{ $type }}')" class="rounded border-industrial-gray-300 text-primary focus:ring-primary">
                        {{ $label }}
                    </label>
                </div>
                
                <div id="pref-{{ $type }}-details" class="hidden pl-6 border-l-2 border-industrial-gray-300 mt-3">
                    <div class="mb-3">
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-1">Dias de Envio</label>
                        <div class="flex flex-wrap gap-3 text-xs text-industrial-gray-700">
                            @foreach([1=>'Seg', 2=>'Ter', 3=>'Qua', 4=>'Qui', 5=>'Sex', 6=>'Sáb', 0=>'Dom'] as $val => $day)
                            <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" name="prefs[{{ $type }}][days][]" id="pref-{{ $type }}-d{{ $val }}" value="{{ $val }}" checked class="rounded border-industrial-gray-300 text-primary focus:ring-primary"> {{ $day }}</label>
                            @endforeach
                        </div>
                    </div>
                    
                    <div class="flex gap-4">
                        <div class="flex-2">
                            <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-1">Horário (vazio = 24h)</label>
                            <div class="flex items-center gap-2">
                                <input type="time" name="prefs[{{ $type }}][start]" id="pref-{{ $type }}-start" class="border border-industrial-gray-300 rounded p-1 text-sm bg-white text-industrial-gray-800 font-display">
                                <span class="text-industrial-gray-400">-</span>
                                <input type="time" name="prefs[{{ $type }}][end]" id="pref-{{ $type }}-end" class="border border-industrial-gray-300 rounded p-1 text-sm bg-white text-industrial-gray-800 font-display">
                            </div>
                        </div>
                        <div class="flex-1">
                            <label class="block text-[10px] font-bold uppercase tracking-widest text-industrial-gray-500 mb-1">Intervalo (min)</label>
                            <input type="number" name="prefs[{{ $type }}][interval]" id="pref-{{ $type }}-interval" class="w-full border border-industrial-gray-300 rounded p-1 text-sm focus:ring-2 focus:ring-primary focus:border-primary bg-white text-industrial-gray-800 font-display" value="30" min="1">
                        </div>
                    </div>
                </div>
            </div>
            @endforeach

            <div id="fg-active" class="hidden mt-4">
                <label class="font-bold text-sm text-industrial-gray-800 flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="m-active" value="1" class="rounded border-industrial-gray-300 text-primary focus:ring-primary">
                    Contato Ativo
                </label>
            </div>
            
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" class="bg-white border border-industrial-gray-300 text-industrial-gray-600 hover:bg-industrial-gray-50 px-4 py-2 rounded text-[10px] font-bold uppercase tracking-widest transition-colors cursor-pointer" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="bg-primary text-white hover:bg-blue-700 border border-transparent px-4 py-2 rounded text-[10px] font-bold uppercase tracking-widest transition-colors cursor-pointer">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePrefDetails(type) {
    const isChecked = document.getElementById('pref-' + type + '-enabled').checked;
    document.getElementById('pref-' + type + '-details').style.display = isChecked ? 'block' : 'none';
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
    
    if (mode === 'edit') {
        document.getElementById('modal-title').textContent = 'Editar Contato';
        document.getElementById('form-method').value = 'PUT';
        form.action = `/admin-contacts/${data.id}`;
        
        document.getElementById('m-name').value = data.name;
        document.getElementById('m-phone').value = data.phone;
        document.getElementById('m-active').checked = !!data.is_active;
        document.getElementById('fg-active').classList.remove('hidden');
        
        if (data.alert_preferences) {
            data.alert_preferences.forEach(p => {
                const type = p.alert_type;
                document.getElementById('pref-' + type + '-enabled').checked = true;
                togglePrefDetails(type);
                
                let days = p.days_of_week ? p.days_of_week.split(',') : [];
                for(let i=0; i<=6; i++) {
                    document.getElementById('pref-' + type + '-d' + i).checked = days.includes(i.toString());
                }
                
                document.getElementById('pref-' + type + '-start').value = (p.time_start && p.time_start !== '00:00:00') ? p.time_start.substring(0,5) : '';
                document.getElementById('pref-' + type + '-end').value = (p.time_end && p.time_end !== '23:59:00') ? p.time_end.substring(0,5) : '';
                document.getElementById('pref-' + type + '-interval').value = p.min_interval || 30;
            });
        }
    } else {
        document.getElementById('modal-title').textContent = 'Novo Contato';
        document.getElementById('form-method').value = 'POST';
        form.action = `{{ route('admin-contacts.store') }}`;
        
        document.getElementById('m-name').value = '';
        document.getElementById('m-phone').value = '';
        document.getElementById('fg-active').classList.add('hidden');
    }
}

function closeModal() { 
    const modal = document.getElementById('modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

document.getElementById('modal').addEventListener('click', e => { 
    if (e.target.id === 'modal') closeModal(); 
});
</script>
@endsection
