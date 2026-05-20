<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings.index');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $admin = Auth::guard('admin')->user();

        if (!Hash::check($request->current_password, $admin->getAuthPassword())) {
            return back()->withErrors(['current_password' => 'A senha atual está incorreta.']);
        }

        $admin->password_hash = Hash::make($request->new_password);
        $admin->save();

        return back()->with('success', 'Senha alterada com sucesso!');
    }

    public function runMigrations()
    {
        if (app()->environment('production') && !env('ALLOW_WEB_MIGRATIONS', false)) {
            return back()->withErrors(['error' => 'Por motivos de segurança, a execução de migrações via web está desativada em produção. Caso necessário, adicione ALLOW_WEB_MIGRATIONS=true no seu arquivo .env.']);
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            return back()->with('success', 'Migrações do banco de dados executadas com sucesso! Retorno: ' . nl2br($output));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Erro ao executar migrações: ' . $e->getMessage()]);
        }
    }

    public function clearCache()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            $output = \Illuminate\Support\Facades\Artisan::output();
            return back()->with('success', 'Cache do sistema limpo com sucesso! Retorno: ' . nl2br($output));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Erro ao limpar cache: ' . $e->getMessage()]);
        }
    }
}
