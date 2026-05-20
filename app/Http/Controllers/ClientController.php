<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::withCount('sensors')->get();
        return view('admin.clients.index', compact('clients'));
    }

    public function create()
    {
        return view('admin.clients.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'company' => 'nullable|string|max:100',
            'influx_org' => 'nullable|string|max:100',
            'influx_bucket' => 'nullable|string|max:100',
            'influx_token' => 'nullable|string',
            'alert_interval_connectivity' => 'nullable|integer|min:0',
            'alert_interval_threshold' => 'nullable|integer|min:0',
        ]);

        $validated['token'] = hash('sha256', Str::random(40) . microtime(true));

        Client::create($validated);

        return redirect()->route('clients.index')->with('success', 'Cliente criado com sucesso.');
    }

    public function edit(Client $client)
    {
        $client->load(['contacts.alertPreferences']);
        return view('admin.clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'company' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'influx_org' => 'nullable|string|max:100',
            'influx_bucket' => 'nullable|string|max:100',
            'influx_token' => 'nullable|string',
            'alert_interval_connectivity' => 'nullable|integer|min:0',
            'alert_interval_threshold' => 'nullable|integer|min:0',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $client->update($validated);

        return redirect()->route('clients.index')->with('success', 'Cliente atualizado com sucesso.');
    }

    public function destroy(Client $client)
    {
        // Proteção: impedir deleção se houver sensores ou contatos vinculados
        $sensorCount = $client->sensors()->count();
        $contactCount = $client->contacts()->count();

        if ($sensorCount > 0 || $contactCount > 0) {
            return redirect()->route('clients.index')->with('error',
                "Não é possível remover este cliente. Existem {$sensorCount} sensor(es) e {$contactCount} contato(s) vinculados. Remova-os primeiro ou desative o cliente."
            );
        }

        $client->delete();
        return redirect()->route('clients.index')->with('success', 'Cliente removido com sucesso.');
    }
}
