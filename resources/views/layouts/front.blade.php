@extends('layouts.app')

@section('body')
<div class="front-shell">
    {{-- Une page avec un hero plein écran (accueil) y met sa propre barre du haut à la place de l'en-tête. --}}
    @hasSection('hero')
        @yield('hero')
    @else
        <header class="front-header">
            <x-logo />
            <nav data-front-nav>
                @include('layouts.partials.front-liens')
            </nav>
            <div class="header-actions">
                <button type="button" class="icon-btn mobile-menu" data-mobile-menu aria-label="Menu"><i data-lucide="menu"></i></button>
                @include('layouts.partials.front-compte')
            </div>
        </header>
    @endif

    @if(session('success') || session('info') || session('error') || $errors->any())
        <div class="container" style="padding-top:16px">
            @if(session('success'))
                <div class="panel" style="padding:12px 16px;background:var(--green-50);border:1px solid var(--green);margin-bottom:8px">{{ session('success') }}</div>
            @endif
            @if(session('info'))
                <div class="panel" style="padding:12px 16px;background:#e3f2fd;border:1px solid #64b5f6;margin-bottom:8px">{{ session('info') }}</div>
            @endif
            @if(session('error'))
                <div class="panel" style="padding:12px 16px;background:#ffebee;border:1px solid #ef5350;margin-bottom:8px;color:#c62828">{{ session('error') }}</div>
            @endif
            @foreach($errors->all() as $error)
                <div class="panel" style="padding:12px 16px;background:#fff3e0;border:1px solid #ffb74d;margin-bottom:8px">{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @yield('content')

    @include('layouts.partials.front-pied')
</div>
@endsection
