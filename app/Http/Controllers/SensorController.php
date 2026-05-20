<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use App\Models\Client;
use Illuminate\Http\Request;

class SensorController extends Controller
{
    public function index()
    {
        $sensors = Sensor::with('client')->get();
        return view('admin.sensors.index', compact('sensors'));
    }

    public function create()
    {
        $clients = Client::where('is_active', true)->get();
        return view('admin.sensors.create', compact('clients'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'device_id' => 'required|string|max:50|unique:sensors,device_id',
            'location' => 'nullable|string|max:100',
            'label' => 'nullable|string|max:100',
            'temp_min' => 'numeric',
            'temp_max' => 'numeric',
            'hum_min' => 'numeric',
            'hum_max' => 'numeric',
        ]);

        Sensor::create($validated);

        return redirect()->route('sensors.index')->with('success', 'Sensor cadastrado com sucesso.');
    }

    public function edit(Sensor $sensor)
    {
        $clients = Client::where('is_active', true)->get();
        return view('admin.sensors.edit', compact('sensor', 'clients'));
    }

    public function update(Request $request, Sensor $sensor)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'device_id' => 'required|string|max:50|unique:sensors,device_id,' . $sensor->id,
            'location' => 'nullable|string|max:100',
            'label' => 'nullable|string|max:100',
            'temp_min' => 'numeric',
            'temp_max' => 'numeric',
            'hum_min' => 'numeric',
            'hum_max' => 'numeric',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $sensor->update($validated);

        return redirect()->route('sensors.index')->with('success', 'Sensor atualizado com sucesso.');
    }

    public function destroy(Sensor $sensor)
    {
        // Proteção: impedir deleção se houver alertas ou eventos vinculados
        $alertCount = $sensor->alerts()->count();
        $eventCount = $sensor->events()->count();

        if ($alertCount > 0 || $eventCount > 0) {
            return redirect()->route('sensors.index')->with('error',
                "Não é possível remover este sensor. Existem {$alertCount} alerta(s) e {$eventCount} evento(s) vinculados. Desative o sensor em vez de removê-lo."
            );
        }

        $sensor->delete();
        return redirect()->route('sensors.index')->with('success', 'Sensor removido com sucesso.');
    }
}
