@extends('layouts.back')

@section('page-title', 'Tableau de bord')

@section('content')
<div class="dashboard">
    <div class="dashboard-intro">
        <p class="muted">Bonjour Amel, voici ce qui se passe aujourd'hui.</p>
        <button type="button" class="btn btn-primary small"><i data-lucide="plus"></i> Nouvelle action</button>
    </div>

    <div class="stats-grid four">
        @foreach([
            ['shirt', 'Vêtements déclarés', '248', '+12% ce mois', 'green'],
            ['wrench', 'Réparations', '186', '+15% ce mois', 'blue'],
            ['gift', 'Dons validés', '62', '+8% ce mois', 'purple'],
            ['wind', 'CO₂ évité', '428 kg', '+18% ce mois', 'orange'],
        ] as [$icon, $label, $value, $detail, $tone])
            <div class="stat-card">
                <div @class(['stat-icon', $tone])><i data-lucide="{{ $icon }}"></i></div>
                <div>
                    <p class="eyebrow">{{ $label }}</p>
                    <strong>{{ $value }}</strong>
                    <small>{{ $detail }}</small>
                </div>
            </div>
        @endforeach
    </div>

    <div class="dashboard-grid">
        <div class="panel chart-panel">
            <div class="panel-head">
                <div>
                    <h2>Activité mensuelle</h2>
                    <p>Vêtements traités par votre atelier</p>
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
            <a href="#" class="link-button">Voir le détail <i data-lucide="arrow-right"></i></a>
        </div>
    </div>

    <div class="panel activity-panel">
        <div class="panel-head">
            <div>
                <h2>Activité récente</h2>
                <p>Les dernières actions sur la plateforme</p>
            </div>
            <a href="#" class="link-button">Tout voir <i data-lucide="arrow-right"></i></a>
        </div>
        @foreach([
            ['Nouveau vêtement déclaré', 'Veste en jean · par Yasmine B.', 'Il y a 12 min', 'shirt', 'green'],
            ['Réparation terminée', 'Pull en laine · par Sami K.', 'Il y a 45 min', 'check', 'blue'],
            ['Don accepté', 'Pantalon chino · Solidarité Mode', 'Il y a 2 h', 'gift', 'purple'],
            ['Rendez-vous confirmé', 'Jeudi 19 sept. · 10:30', 'Hier', 'calendar-days', 'orange'],
        ] as [$title, $sub, $time, $icon, $tone])
            <div class="activity-row">
                <div @class(['activity-icon', $tone])><i data-lucide="{{ $icon }}"></i></div>
                <div><b>{{ $title }}</b><span>{{ $sub }}</span></div>
                <time>{{ $time }}</time>
            </div>
        @endforeach
    </div>
</div>
@endsection
