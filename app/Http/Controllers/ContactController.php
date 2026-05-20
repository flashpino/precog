<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Client;
use App\Models\ContactAlertPreference;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $query = Contact::with(['client', 'alertPreferences'])->where('is_admin', false)->whereNotNull('client_id');
        
        if ($request->has('client_id') && $request->client_id != '') {
            $query->where('client_id', $request->client_id);
        }
        
        $contacts = $query->orderBy('created_at', 'desc')->get();
        $clients = Client::where('is_active', true)->orderBy('name')->get();

        return view('admin.contacts.index', compact('contacts', 'clients'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $contact = Contact::create($validated);
        $contact->is_admin = false;
        $contact->save();

        if ($request->has('prefs')) {
            $this->savePreferences($contact, $request->input('prefs'));
        }

        return redirect()->route('clients.edit', $contact->client_id)->with('success', 'Contato cadastrado com sucesso!');
    }

    public function update(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $contact->update($validated);

        // Atualizar preferências
        $contact->alertPreferences()->delete();
        if ($request->has('prefs')) {
            $this->savePreferences($contact, $request->input('prefs'));
        }

        return redirect()->route('clients.edit', $contact->client_id)->with('success', 'Contato atualizado com sucesso!');
    }

    public function destroy(Contact $contact)
    {
        $clientId = $contact->client_id;
        $contact->delete();
        return redirect()->route('clients.edit', $clientId)->with('success', 'Contato removido!');
    }

    private function savePreferences(Contact $contact, array $prefsPost)
    {
        foreach ($prefsPost as $type => $p) {
            if (!empty($p['enabled'])) {
                $days = isset($p['days']) && is_array($p['days']) ? implode(',', $p['days']) : '0,1,2,3,4,5,6';
                $tstart = empty($p['start']) ? '00:00:00' : (strlen($p['start']) === 5 ? $p['start'] . ':00' : $p['start']);
                $tend = empty($p['end']) ? '23:59:00' : (strlen($p['end']) === 5 ? $p['end'] . ':00' : $p['end']);
                $interval = empty($p['interval']) ? 30 : intval($p['interval']);

                ContactAlertPreference::create([
                    'contact_id' => $contact->id,
                    'alert_type' => $type,
                    'days_of_week' => $days,
                    'time_start' => $tstart,
                    'time_end' => $tend,
                    'min_interval' => $interval
                ]);
            }
        }
    }
}
