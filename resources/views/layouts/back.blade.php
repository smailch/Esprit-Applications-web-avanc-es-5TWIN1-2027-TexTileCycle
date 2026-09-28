@extends('layouts.app')

@section('body')
<div class="back-shell">
    <aside class="sidebar">
        <x-logo />
        <div class="role-switch">
            <div class="role-avatar">C</div>
            <div>
                <b>Couture Plus</b>
                <small>Atelier partenaire</small>
            </div>
            <i data-lucide="chevron-down"></i>
        </div>
        <nav>
            @foreach($menuItems ?? [] as $item)
                <a href="{{ route($item['route']) }}" @class(['active' => request()->routeIs($item['route'])])>
                    <i data-lucide="{{ $item['icon'] }}"></i>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
        <div class="sidebar-bottom">
            <a href="{{ route('back.parametres') }}"><i data-lucide="settings"></i> Paramètres</a>
            <a href="{{ route('front.home') }}"><i data-lucide="log-out"></i> Se déconnecter</a>
        </div>
    </aside>

    <main class="back-main">
        <header class="back-header">
            <div>
                <span class="breadcrumb">Couture Plus <i data-lucide="chevron-right"></i> @yield('page-title', 'Tableau de bord')</span>
                <h1>@yield('page-title', 'Tableau de bord')</h1>
            </div>
            <div class="back-header-actions">
                <div class="search-box compact">
                    <i data-lucide="search"></i>
                    <input type="search" placeholder="Rechercher...">
                </div>
                <button type="button" class="icon-btn notification" aria-label="Notifications">
                    <i data-lucide="bell"></i>
                    <i></i>
                </button>
                <div class="user-avatar">AM</div>
            </div>
        </header>

        @yield('content')
    </main>

    <a href="{{ route('front.home') }}" class="back-to-front">Voir le site citoyen</a>
</div>
@endsection
