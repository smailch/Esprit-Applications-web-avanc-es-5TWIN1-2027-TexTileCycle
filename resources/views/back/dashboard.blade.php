@extends('layouts.back')

@section('page-title', 'Tableau de bord')

@php
    $cartesStats = ! empty($statsAtelier)
        ? [
            ['icon' => 'calendar-days', 'label' => 'Rendez-vous en attente', 'value' => (string) ($statsAtelier['rdv']['en_attente'] ?? 0), 'detail' => 'à confirmer', 'tone' => 'orange'],
            ['icon' => 'check-circle', 'label' => 'Rendez-vous confirmés', 'value' => (string) ($statsAtelier['rdv']['confirme'] ?? 0), 'detail' => 'à honorer', 'tone' => 'green'],
            ['icon' => 'shirt', 'label' => 'Pièces reçues', 'value' => (string) ($statsAtelier['pieces']['total'] ?? 0), 'detail' => 'dans votre atelier', 'tone' => 'blue'],
            ['icon' => 'wrench', 'label' => 'En réparation', 'value' => (string) ($statsAtelier['pieces']['en_reparation'] ?? 0), 'detail' => 'en cours de traitement', 'tone' => 'purple'],
        ]
        : [
            ['icon' => 'shirt', 'label' => 'Vêtements déclarés', 'value' => '248', 'detail' => '+12% ce mois', 'tone' => 'green'],
            ['icon' => 'wrench', 'label' => 'Réparations', 'value' => '186', 'detail' => '+15% ce mois', 'tone' => 'blue'],
            ['icon' => 'gift', 'label' => 'Dons validés', 'value' => '62', 'detail' => '+8% ce mois', 'tone' => 'purple'],
            ['icon' => 'wind', 'label' => 'CO₂ évité', 'value' => '428 kg', 'detail' => '+18% ce mois', 'tone' => 'orange'],
        ];

    $activites = [
        ['title' => 'Nouveau vêtement déclaré', 'sub' => 'Veste en jean · par Yasmine B.', 'time' => 'Il y a 12 min', 'icon' => 'shirt', 'tone' => 'green'],
        ['title' => 'Réparation terminée', 'sub' => 'Pull en laine · par Sami K.', 'time' => 'Il y a 45 min', 'icon' => 'check', 'tone' => 'blue'],
        ['title' => 'Don accepté', 'sub' => 'Pantalon chino · Solidarité Mode', 'time' => 'Il y a 2 h', 'icon' => 'gift', 'tone' => 'purple'],
        ['title' => 'Rendez-vous confirmé', 'sub' => 'Jeudi 19 sept. · 10:30', 'time' => 'Hier', 'icon' => 'calendar-days', 'tone' => 'orange'],
    ];
@endphp

@section('content')
<div class="dashboard">
    <div class="dashboard-intro">
        <p class="muted">Bonjour {{ auth()->user()->name ?? '' }}, voici ce qui se passe aujourd'hui.</p>
        @if(! empty($atelier))
            <a href="{{ route('back.rdv', ['statut' => 'en_attente']) }}" class="btn btn-primary small"><i data-lucide="calendar-plus"></i> Demandes en attente</a>
        @endif
    </div>

    <div class="stats-grid four">
        @foreach($cartesStats as $carte)
            <div class="stat-card">
                <div @class(['stat-icon', $carte['tone']])><i data-lucide="{{ $carte['icon'] }}"></i></div>
                <div>
                    <p class="eyebrow">{{ $carte['label'] }}</p>
                    <strong>{{ $carte['value'] }}</strong>
                    <small>{{ $carte['detail'] }}</small>
                </div>
            </div>
        @endforeach
    </div>

    <div class="dashboard-grid">
        <div class="panel chart-panel">
            <div class="panel-head">
                <div>
                    <h2>Activité mensuelle</h2>
                    <p>
                        Vêtements traités
                        @if(! empty($atelier))
                            par {{ $atelier->nom }}
                        @else
                            par la plateforme
                        @endif
                    </p>
                </div>
                <button type="button" class="select-btn">6 derniers mois <i data-lucide="chevron-down"></i></button>
            </div>
            <div class="chart">
                <div class="y-labels"><span>60</span><span>40</span><span>20</span><span>0</span></div>
                <div class="chart-lines">
                    <i></i><i></i><i></i><i></i>
                    <svg viewBox="0 0 600 180" preserveAspectRatio="none">
                        <path d="M0,140 C55,125 60,150 115,105 S170,120 225,72 S280,93 335,48 S400,77 450,52 S500,28 600,16" fill="none" stroke="#2E7D32" stroke-width="3" />
                        <path d="M0,140 C55,125 60,150 115,105 S170,120 225,72 S280,93 335,48 S400,77 450,52 S500,28 600,16 L600,180 L0,180Z" fill="url(#chartFill)" opacity=".18" />
                        <defs>
                            <linearGradient id="chartFill" x1="0" x2="0" y1="0" y2="1">
                                <stop stop-color="#2E7D32" />
                                <stop offset="1" stop-color="#2E7D32" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="x-labels"><span>Avril</span><span>Mai</span><span>Juin</span><span>Juil.</span><span>Août</span><span>Sept.</span></div>
                </div>
            </div>
        </div>

        <div class="panel ai-panel">
            <div class="ai-panel-icon"><i data-lucide="sparkles"></i></div>
            <p class="kicker">Analyse intelligente</p>
            <h2>Une tendance à saisir</h2>
            <p>Les réparations denim sont en hausse de <strong>23%</strong> ce mois-ci. Pensez à mettre ce service en avant.</p>
            <a href="{{ route('back.rdv') }}" class="link-button">Voir le détail <i data-lucide="arrow-right"></i></a>
        </div>
    </div>

    <div class="panel activity-panel">
        <div class="panel-head">
            <div>
                <h2>Activité récente</h2>
                <p>Les dernières actions sur la plateforme</p>
            </div>
            <a href="{{ route('back.rdv') }}" class="link-button">Tout voir <i data-lucide="arrow-right"></i></a>
        </div>
        @foreach($activites as $activite)
            <div class="activity-row">
                <div @class(['activity-icon', $activite['tone']])><i data-lucide="{{ $activite['icon'] }}"></i></div>
                <div><b>{{ $activite['title'] }}</b><span>{{ $activite['sub'] }}</span></div>
                <time>{{ $activite['time'] }}</time>
            </div>
        @endforeach
    </div>
</div>
@endsection
