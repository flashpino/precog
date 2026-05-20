<!DOCTYPE html>
<html class="light" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>@yield('title', 'Precog | Admin Dashboard')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .grid-bg {
            background-image: radial-gradient(circle at 1px 1px, rgba(148, 163, 184, 0.1) 1px, transparent 0);
            background-size: 24px 24px;
        }
        .digital-font {
            font-family: 'JetBrains Mono', monospace;
        }
        .status-glow-green {
            box-shadow: 0 0 8px #16a34a;
        }
    </style>
</head>
<body class="bg-background-light font-display text-industrial-gray-800 min-h-screen grid-bg">
    <div class="relative flex h-auto min-h-screen w-full flex-col overflow-x-hidden">
        <!-- Header -->
        <header class="flex items-center justify-between border-b border-industrial-gray-300 bg-white/90 backdrop-blur-md px-6 py-3 sticky top-0 z-50 shadow-sm">
            <div class="flex items-center gap-8">
                <div class="flex items-center">
                    <img src="{{ asset('images/logosite.png') }}" alt="PrecogSystem" class="h-8 w-auto">
                </div>
                <nav class="hidden md:flex items-center gap-6 text-xs font-bold uppercase tracking-widest">
                    <a class="{{ request()->routeIs('dashboard') ? 'text-industrial-gray-900 border-b-2 border-primary' : 'text-industrial-gray-500 hover:text-industrial-gray-900 transition-colors' }} py-1" href="{{ route('dashboard') }}">Dashboard</a>
                    <a class="{{ request()->routeIs('clients.*') ? 'text-industrial-gray-900 border-b-2 border-primary' : 'text-industrial-gray-500 hover:text-industrial-gray-900 transition-colors' }} py-1" href="{{ route('clients.index') }}">Clientes</a>
                    <a class="{{ request()->routeIs('sensors.*') ? 'text-industrial-gray-900 border-b-2 border-primary' : 'text-industrial-gray-500 hover:text-industrial-gray-900 transition-colors' }} py-1" href="{{ route('sensors.index') }}">Sensores</a>
                    <a class="{{ request()->routeIs('admin-contacts.*') ? 'text-industrial-gray-900 border-b-2 border-primary' : 'text-industrial-gray-500 hover:text-industrial-gray-900 transition-colors' }} py-1" href="{{ route('admin-contacts.index') }}">Admins</a>
                    <a class="{{ request()->routeIs('settings.*') ? 'text-industrial-gray-900 border-b-2 border-primary' : 'text-industrial-gray-500 hover:text-industrial-gray-900 transition-colors' }} py-1" href="{{ route('settings.index') }}">Configurações</a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <div class="hidden lg:flex items-center gap-2 px-3 py-1 bg-industrial-gray-100 rounded border border-industrial-gray-200">
                    <span class="text-[9px] font-bold text-industrial-gray-500 uppercase tracking-widest">InfluxDB</span>
                    <div class="w-2 h-2 rounded-full bg-hmi-green status-glow-green"></div>
                </div>
                <form action="{{ route('admin.logout') }}" method="POST" class="inline hidden md:block">
                    @csrf
                    <button type="submit" class="bg-white border border-industrial-gray-300 p-2 rounded hover:bg-industrial-gray-50 transition-all">
                        <span class="material-symbols-outlined text-industrial-gray-600">logout</span>
                    </button>
                </form>
                <!-- Mobile Menu Button -->
                <button type="button" onclick="toggleMobileMenu()" class="md:hidden bg-white border border-industrial-gray-300 p-2 rounded hover:bg-industrial-gray-50 transition-all">
                    <span class="material-symbols-outlined text-industrial-gray-600">menu</span>
                </button>
            </div>
        </header>

        <!-- Mobile Navigation -->
        <div id="mobile-menu" class="hidden md:hidden bg-white border-b border-industrial-gray-300 shadow-sm px-6 py-4 flex flex-col gap-4 sticky top-[61px] z-40">
            <nav class="flex flex-col gap-4 text-xs font-bold uppercase tracking-widest">
                <a class="{{ request()->routeIs('dashboard') ? 'text-industrial-gray-900 border-l-2 border-primary pl-2' : 'text-industrial-gray-500' }}" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="{{ request()->routeIs('clients.*') ? 'text-industrial-gray-900 border-l-2 border-primary pl-2' : 'text-industrial-gray-500' }}" href="{{ route('clients.index') }}">Clientes</a>
                <a class="{{ request()->routeIs('sensors.*') ? 'text-industrial-gray-900 border-l-2 border-primary pl-2' : 'text-industrial-gray-500' }}" href="{{ route('sensors.index') }}">Sensores</a>
                <a class="{{ request()->routeIs('admin-contacts.*') ? 'text-industrial-gray-900 border-l-2 border-primary pl-2' : 'text-industrial-gray-500' }}" href="{{ route('admin-contacts.index') }}">Admins</a>
                <a class="{{ request()->routeIs('settings.*') ? 'text-industrial-gray-900 border-l-2 border-primary pl-2' : 'text-industrial-gray-500' }}" href="{{ route('settings.index') }}">Configurações</a>
            </nav>
            <hr class="border-industrial-gray-200">
            <form action="{{ route('admin.logout') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="text-xs font-bold uppercase tracking-widest text-hmi-red flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">logout</span> Sair
                </button>
            </form>
        </div>

        <main class="p-6 space-y-8 max-w-[1600px] mx-auto w-full">
            @if(session('success'))
                <div class="bg-hmi-green/10 border-l-4 border-hmi-green p-4 rounded-r">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <span class="material-symbols-outlined text-hmi-green">check_circle</span>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-hmi-green font-bold">
                                {{ session('success') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-hmi-red/10 border-l-4 border-hmi-red p-4 rounded-r">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <span class="material-symbols-outlined text-hmi-red">error</span>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-hmi-red font-bold">
                                {{ session('error') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="bg-hmi-red/10 border-l-4 border-hmi-red p-4 rounded-r">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <span class="material-symbols-outlined text-hmi-red">error</span>
                        </div>
                        <div class="ml-3">
                            <ul class="text-sm text-hmi-red font-bold list-disc pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="mt-auto border-t border-industrial-gray-200 bg-white p-4 text-center">
            <div class="flex justify-between items-center max-w-[1600px] mx-auto text-[9px] text-industrial-gray-400 uppercase tracking-widest font-bold">
                <span>© {{ date('Y') }} PrecogSystem</span>
                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[10px]">terminal</span> Admin Dashboard v3.0.0</span>
            </div>
        </footer>
    </div>
    
    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            if (menu.classList.contains('hidden')) {
                menu.classList.remove('hidden');
            } else {
                menu.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
