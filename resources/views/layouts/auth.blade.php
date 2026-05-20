<!DOCTYPE html>
<html class="light" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>@yield('title', 'Precog | Autenticação')</title>
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
    </style>
</head>
<body class="bg-background-light font-display text-industrial-gray-800 min-h-screen grid-bg">
    @yield('content')
</body>
</html>
