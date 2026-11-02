{{--
    Panneau « Analyse IA » du détail d'un signalement (TriageSignalementService).
    « Appliquer la suggestion » préremplit le formulaire de modération : la décision reste à l'admin.
--}}
@php
    use App\Modules\Signalements\Services\TriageSignalementService as Triage;
    $t = $signalement->triage;
@endphp

<section class="panel detail-card ia-triage" aria-labelledby="ia-triage-titre">
    <div class="ia-triage__tete">
        <h2 id="ia-triage-titre"><i data-lucide="sparkles" aria-hidden="true"></i> Analyse IA</h2>
        <form method="post" action="{{ route('back.signalements.analyser', $signalement->getKey()) }}">
            @csrf
            <button type="submit" class="link-button">
                <i data-lucide="refresh-cw" aria-hidden="true"></i> {{ $t ? 'Relancer' : 'Analyser' }}
            </button>
        </form>
    </div>

    @if($t)
        <div class="ia-triage__gravite">
            <span class="ia-gravite ia-gravite--{{ Triage::toneGravite($t['gravite']) }}">{{ $t['priorite'] }}</span>
            <div class="ia-jauge" role="img" aria-label="Gravité {{ $t['gravite'] }} sur 5">
                @for($i = 1; $i <= 5; $i++)
                    <span @class(['est-pleine' => $i <= $t['gravite']])></span>
                @endfor
            </div>
            <b>{{ $t['gravite'] }}/5</b>
        </div>

        <dl class="ia-triage__liste">
            <dt>Catégorie</dt>
            <dd>{{ Triage::libelleCategorie($t['categorie']) }}</dd>

            @if(! empty($t['resume']))
                <dt>Résumé</dt>
                <dd>{{ $t['resume'] }}</dd>
            @endif

            <dt>Décision suggérée</dt>
            <dd><b>{{ Triage::DECISIONS[$t['decision']] ?? $t['decision'] }}</b>
                @if(! empty($t['justification']))<span class="cell-sub">{{ $t['justification'] }}</span>@endif
            </dd>

            <dt>Récurrence</dt>
            <dd>
                {{ $t['nb_autres_signalements'] ? $t['nb_autres_signalements'].' autre(s) signalement(s) sur cet élément (90 jours)' : 'Premier signalement sur cet élément' }}
                @foreach($t['doublons'] ?? [] as $doublon)
                    <a href="{{ route('back.signalements.show', $doublon['id']) }}" class="cell-sub ia-doublon">
                        <i data-lucide="copy" aria-hidden="true"></i> Doublon probable ({{ $doublon['similarite'] }} % similaire)
                    </a>
                @endforeach
            </dd>
        </dl>

        @if($signalement->estEnAttente() && ! empty($t['note_suggeree']))
            <div class="ia-note">
                <span class="cell-sub">Note suggérée</span>
                <p data-ia-note>{{ $t['note_suggeree'] }}</p>
                <button type="button" class="btn btn-secondary small"
                        data-ia-appliquer data-decision="{{ $t['decision'] }}">
                    <i data-lucide="wand-sparkles" aria-hidden="true"></i> Appliquer la suggestion
                </button>
            </div>
        @endif

        <p class="ia-triage__source">
            {{ $t['source'] === 'gemini' ? 'Analyse Gemini' : 'Analyse locale (règles)' }}
            · {{ \Carbon\Carbon::parse($t['analyse_le'])->locale('fr')->diffForHumans() }}
            · l'IA propose, l'administrateur décide.
        </p>
    @else
        <p class="muted" style="margin:8px 0 0">Ce signalement n'a pas encore été analysé.</p>
    @endif
</section>

@push('scripts')
<script>
// « Appliquer la suggestion » : préremplit la note et la sanction, et met en avant le bon bouton.
document.querySelectorAll('[data-ia-appliquer]').forEach((bouton) => {
    bouton.addEventListener('click', () => {
        const form = document.querySelector('form[action$="/moderation"]');
        if (!form) return;
        const decision = bouton.dataset.decision;
        form.querySelector('textarea[name="note_admin"]').value = document.querySelector('[data-ia-note]').textContent.trim();
        const sanction = form.querySelector('[data-ia-sanction]');
        if (sanction) sanction.checked = decision === 'sanctionner';
        form.querySelectorAll('button[name="statut"]').forEach((b) => {
            b.classList.toggle('ia-suggere', b.value === (decision === 'rejeter' ? 'rejete' : 'traite'));
        });
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        form.querySelector('textarea[name="note_admin"]').focus();
    });
});
</script>
@endpush
