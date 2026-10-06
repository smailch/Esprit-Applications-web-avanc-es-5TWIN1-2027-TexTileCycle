@extends('layouts.app')

@section('body')
@php
    $user = $backUser ?? auth()->user();
    $canParametres = $user && \App\Modules\Auth\Services\RoleNavigationService::canAccessRoute($user, 'back.parametres');
@endphp
<div class="back-shell">
    <aside class="sidebar">
        <x-logo />
        @if($user)
            <div class="role-switch">
                <div class="role-avatar">{{ $user->initials() }}</div>
                <div>
                    <b>{{ $user->name }}</b>
                    <small>{{ $user->roleLabel() }}</small>
                </div>
            </div>
        @endif
        <nav>
            @foreach($menuItems ?? [] as $item)
                <a href="{{ route($item['route']) }}" @class(['active' => request()->routeIs($item['route']) || (substr_count($item['route'], '.') > 1 && request()->routeIs(str($item['route'])->beforeLast('.').'.*'))])>
                    <i data-lucide="{{ $item['icon'] }}"></i>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
        <div class="sidebar-bottom">
            @if($canParametres)
                <a href="{{ route('back.parametres') }}" @class(['active' => request()->routeIs('back.parametres')])><i data-lucide="settings"></i> Paramètres</a>
            @endif
            <a href="{{ route('front.home') }}"><i data-lucide="external-link"></i> Site citoyen</a>
            <form method="post" action="{{ route('front.logout') }}">
                @csrf
                <button type="submit" class="sidebar-logout"><i data-lucide="log-out"></i> Se déconnecter</button>
            </form>
        </div>
    </aside>

    <main class="back-main">
        <header class="back-header">
            <div>
                <span class="breadcrumb">{{ $user?->name ?? 'TexTileCycle' }} <i data-lucide="chevron-right"></i> @yield('page-title', 'Tableau de bord')</span>
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
                @if($user)
                    <div class="user-avatar" title="{{ $user->roleLabel() }}">{{ $user->initials() }}</div>
                @endif
            </div>
        </header>

        @yield('content')
    </main>

    <a href="{{ route('front.home') }}" class="back-to-front">Voir le site citoyen</a>
</div>
@endsection
