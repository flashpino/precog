<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactAlertPreference;
use Illuminate\Http\Request;

class AdminContactController extends Controller
{
    public function index()
    {
        $contacts = Contact::with('alertPreferences')
            ->where('is_admin', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.admin_contacts.index', compact('contacts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'prefs' => 'nullable|array',
        ]);

        $contact = Contact::create([
            'client_id' => null,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'is_admin' => true,
            'is_active' => true,
        ]);

        $this->syncPreferences($contact, $request->input('prefs', []));

        return back()->with('success', 'Administrador cadastrado com sucesso!');
    }

    public function update(Request $request, Contact $admin_contact)
    {
        // Certificar de que apenas altera admins
        if (!$admin_contact->is_admin) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'is_active' => 'boolean',
            'prefs' => 'nullable|array',
        ]);

        $admin_contact->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->syncPreferences($admin_contact, $request->input('prefs', []));

        return back()->with('success', 'Administrador atualizado com sucesso!');
    }

    public function destroy(Contact $admin_contact)
    {
        if (!$admin_contact->is_admin) {
            abort(403);
        }

        $admin_contact->delete();

        return back()->with('success', 'Administrador removido com sucesso!');
    }

    private function syncPreferences(Contact $contact, array $prefsInput)
    {
        // Remove existing preferences
        $contact->alertPreferences()->delete();

        $types = ['connectivity', 'temperature', 'humidity'];

        foreach ($types as $type) {
            if (!empty($prefsInput[$type]['enabled'])) {
                $days = isset($prefsInput[$type]['days']) && is_array($prefsInput[$type]['days']) 
                            ? implode(',', $prefsInput[$type]['days']) 
                            : '0,1,2,3,4,5,6';
                            
                $pStart = $prefsInput[$type]['start'] ?? '';
                $start = empty($pStart) ? '00:00:00' : (strlen($pStart) === 5 ? $pStart . ':00' : $pStart);
                
                $pEnd = $prefsInput[$type]['end'] ?? '';
                $end = empty($pEnd) ? '23:59:00' : (strlen($pEnd) === 5 ? $pEnd . ':00' : $pEnd);
                
                $interval = empty($prefsInput[$type]['interval']) ? 30 : intval($prefsInput[$type]['interval']);

                ContactAlertPreference::create([
                    'contact_id' => $contact->id,
                    'alert_type' => $type,
                    'days_of_week' => $days,
                    'time_start' => $start,
                    'time_end' => $end,
                    'min_interval' => $interval,
                ]);
            }
        }
    }
}
