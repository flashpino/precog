@extends('layouts.auth')

@section('title', 'Precog | Login')

@section('content')
<div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo Card -->
        <div class="bg-white rounded-xl shadow-lg border border-industrial-gray-200 overflow-hidden">
            <!-- Header -->
            <div class="bg-white border-b border-industrial-gray-200 px-8 py-6 text-center">
                <img src="{{ asset('images/logosite.png') }}" alt="PrecogSystem" class="h-10 mx-auto mb-3">
                <p class="text-[10px] text-industrial-gray-500 uppercase tracking-[0.3em] font-bold">Sistema de Monitoramento Industrial</p>
            </div>

            <!-- Form -->
            <div class="px-8 py-8">
                <h2 class="text-lg font-bold text-industrial-gray-800 mb-1">Acesso Administrativo</h2>
                <p class="text-xs text-industrial-gray-500 mb-6">Insira suas credenciais para continuar</p>

                @if($errors->any())
                    <div class="bg-hmi-red/10 border-l-4 border-hmi-red p-3 rounded-r mb-6">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-hmi-red text-lg">error</span>
                            <p class="text-sm text-hmi-red font-semibold">{{ $errors->first() }}</p>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="username" class="block text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest mb-2">Usuário</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-industrial-gray-400 text-lg">person</span>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                value="{{ old('username') }}"
                                required
                                autofocus
                                autocomplete="username"
                                class="w-full pl-10 pr-4 py-3 border border-industrial-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all bg-industrial-gray-50"
                                placeholder="admin"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-[10px] font-bold text-industrial-gray-500 uppercase tracking-widest mb-2">Senha</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-industrial-gray-400 text-lg">lock</span>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                class="w-full pl-10 pr-4 py-3 border border-industrial-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all bg-industrial-gray-50"
                                placeholder="••••••••"
                            >
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full bg-primary hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-all duration-200 text-sm uppercase tracking-widest flex items-center justify-center gap-2 shadow-md hover:shadow-lg"
                    >
                        <span class="material-symbols-outlined text-lg">login</span>
                        Entrar
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <div class="bg-industrial-gray-50 border-t border-industrial-gray-200 px-8 py-3 text-center">
                <span class="text-[9px] text-industrial-gray-400 uppercase tracking-widest font-bold">
                    © {{ date('Y') }} PrecogSystem v3.0.0
                </span>
            </div>
        </div>
    </div>
</div>
@endsection
