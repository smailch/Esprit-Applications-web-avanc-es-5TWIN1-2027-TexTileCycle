@extends('layouts.back')

@section('page-title', 'Statistiques & impact')

@section('content')
<div class="dashboard">
    <div class="stats-grid four">
        @foreach([
            ['shirt', 'Vêtements sauvés', '1 240', 'Cumul 2026', 'green'],
            ['wind', 'CO₂ évité', '2,3 t', 'Équivalent carburant', 'orange'],
            ['droplets', 'Eau économisée', '18 400 L', 'Production textile évitée', 'blue'],
            ['recycle', 'Taux de réutilisation', '78%', 'Pièces réparées ou données', 'purple'],
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

    <div class="dashboard-grid" style="margin-top:21px">
        <div class="panel chart-panel">
            <div class="panel-head">
                <div>
                    <h2>Impact écologique mensuel</h2>
                    <p>CO₂ évité et eau économisée</p>
                </div>
            </div>
            <div class="chart">
                <div class="y-labels"><span>100</span><span>75</span><span>50</span><span>0</span></div>
                <div class="chart-lines">
                    <i></i><i></i><i></i><i></i>
                    <svg viewBox="0 0 600 180" preserveAspectRatio="none">
                        <path d="M0,120 C80,100 120,130 200,90 S320,110 400,60 S480,80 600,40" fill="none" stroke="#2E7D32" stroke-width="3" />
                    </svg>
                    <div class="x-labels"><span>Avril</span><span>Mai</span><span>Juin</span><span>Juil.</span><span>Août</span><span>Sept.</span></div>
                </div>
            </div>
        </div>

        <div class="panel ai-panel">
            <div class="ai-panel-icon"><i data-lucide="sparkles"></i></div>
            <p class="kicker">Prévisions IA</p>
            <h2>Anomalie détectée</h2>
            <p>Les dons de manteaux augmentent de <strong>15%</strong> en septembre. Anticipez les besoins des associations partenaires.</p>
            <a href="#" class="link-button">Voir les prévisions <i data-lucide="arrow-right"></i></a>
        </div>
    </div>
</div>
@endsection
