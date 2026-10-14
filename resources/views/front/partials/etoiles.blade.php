{{-- Note en étoiles (lecture seule). $note : 0 à 5 (arrondie à l'étoile la plus proche) ; $taille : '' ou 'lg'. --}}
@php $pleines = (int) round((float) $note); @endphp
<span @class(['etoiles', 'etoiles--'.($taille ?? '') => filled($taille ?? null)])>
    <span class="sr-only">Note : {{ number_format((float) $note, 1, ',', '') }} sur 5</span>
    @foreach (range(1, 5) as $i)
        <i data-lucide="star" aria-hidden="true" @class(['etoile', 'is-pleine' => $i <= $pleines])></i>
    @endforeach
</span>
