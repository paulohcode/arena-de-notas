<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Arena das Notas')</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="game-body min-h-screen font-sans antialiased"
      data-game-root
      data-csrf="{{ csrf_token() }}"
      @auth
          @if(auth()->user()->isStudent())
              data-notify-url="{{ route('student.notifications') }}"
              data-mark-read-url="{{ route('student.notifications.read') }}"
              data-challenge-poll-url="{{ route('student.arena.pending') }}"
          @endif
      @endauth
      @isset($notifyUrl) data-notify-url="{{ $notifyUrl }}" @endisset
      @isset($markReadUrl) data-mark-read-url="{{ $markReadUrl }}" @endisset
      @isset($rankingUrl) data-ranking-url="{{ $rankingUrl }}" @endisset>
    <div class="arena-shell max-w-6xl mx-auto px-4 py-6">
        <header class="arena-header flex flex-wrap items-center justify-between gap-4 mb-8 reveal">
            <a href="{{ route('home') }}" class="brand-mark">
                <span class="brand-crest" aria-hidden="true">⚔</span>
                <span>
                    <span class="brand-title block">Arena das Notas</span>
                    <span class="brand-sub block">Campo de batalha acadêmico</span>
                </span>
            </a>
            <nav class="flex flex-wrap items-center gap-2 text-sm">
                <a class="game-btn-ghost" href="{{ route('home') }}">Mapa</a>
                @auth
                    @if(auth()->user()->isAdmin())
                        <a class="game-btn-ghost" href="{{ route('admin.dashboard') }}">Admin</a>
                        <a class="game-btn-ghost" href="{{ route('teacher.dashboard') }}">Turmas</a>
                        <a class="game-btn-ghost" href="{{ route('teacher.seasons.index') }}">Campanhas</a>
                    @elseif(auth()->user()->isTeacher())
                        <a class="game-btn-ghost" href="{{ route('teacher.dashboard') }}">Professor</a>
                        <a class="game-btn-ghost" href="{{ route('teacher.seasons.index') }}">Campanhas</a>
                    @else
                        <a class="game-btn-ghost" href="{{ route('student.dashboard') }}">Minha ficha</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="game-btn-ghost" type="submit">Sair</button>
                    </form>
                @else
                    <a class="game-btn" href="{{ route('login') }}">Entrar na arena</a>
                @endauth
            </nav>
        </header>

        @if(session('success'))
            <div class="hidden" data-flash-toast="{{ session('success') }}" data-flash-title="Arena"></div>
        @endif

        @if($errors->any())
            <div class="hidden" data-flash-toast="{{ $errors->first() }}" data-flash-title="Atenção" data-flash-tone="warn"></div>
            <div class="game-card p-4 mb-6 border-amber-400/50 text-amber-100 reveal" role="alert">
                <p class="font-semibold text-amber-200">Atenção</p>
                <p class="text-sm mt-1">{{ $errors->first() }}</p>
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
