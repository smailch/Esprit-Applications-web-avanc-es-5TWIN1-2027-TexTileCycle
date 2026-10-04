{{-- Une plage horaire. Utilisé aussi dans un <template> avec les jetons __JOUR__, __INDEX__ et __NUM__ remplacés en JS. --}}
<div class="ab-slot" data-slot>
    <label class="sr-only" for="h-{{ $jour }}-{{ $index }}-debut" data-slot-label="debut">Début de la plage {{ $num }} du {{ $jour }}</label>
    <input type="time" step="300" class="ab-time" id="h-{{ $jour }}-{{ $index }}-debut"
           name="horaires[{{ $jour }}][plages][{{ $index }}][debut]" value="{{ $debut }}">
    <span class="ab-slot__sep" aria-hidden="true">–</span>
    <label class="sr-only" for="h-{{ $jour }}-{{ $index }}-fin" data-slot-label="fin">Fin de la plage {{ $num }} du {{ $jour }}</label>
    <input type="time" step="300" class="ab-time" id="h-{{ $jour }}-{{ $index }}-fin"
           name="horaires[{{ $jour }}][plages][{{ $index }}][fin]" value="{{ $fin }}">
    <button type="button" class="icon-btn ab-slot__remove" data-remove-slot aria-label="Supprimer la plage {{ $num }} du {{ $jour }}">
        <i data-lucide="x" aria-hidden="true"></i>
    </button>
</div>
