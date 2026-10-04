<section class="panel ab-section" aria-labelledby="sec-horaires">
    <div class="panel-head">
        <div>
            <h2 id="sec-horaires">Horaires</h2>
            <p>Heure de Tunis. Cochez « Fermé » pour un jour de repos.</p>
        </div>
    </div>
    <div class="ab-section__body">
        @if ($errors->has('horaires') || $errors->has('horaires.*'))
            <div class="ab-error ab-error--block" id="horaires-error" role="alert">
                <i data-lucide="alert-circle" aria-hidden="true"></i>
                <ul>
                    @foreach (array_merge($errors->get('horaires'), ...array_values($errors->get('horaires.*'))) as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="ab-hours" data-hours-editor>
            @foreach ($horaires as $jour => $info)
                <fieldset @class(['ab-day', 'is-closed' => $info['ferme']]) data-day="{{ $jour }}" data-next-index="{{ count($info['plages']) }}">
                    <legend class="ab-day__name">{{ ucfirst($jour) }}</legend>

                    <div class="ab-day__toggle">
                        <input type="hidden" name="horaires[{{ $jour }}][ferme]" value="0">
                        <input type="checkbox" class="ab-switch" id="ferme-{{ $jour }}" name="horaires[{{ $jour }}][ferme]" value="1" @checked($info['ferme']) data-day-closed>
                        <label for="ferme-{{ $jour }}">Fermé</label>
                    </div>

                    <div class="ab-day__slots" data-day-slots>
                        @foreach ($info['plages'] as $i => [$debut, $fin])
                            @include('back.ateliers.partials.slot', ['jour' => $jour, 'index' => $i, 'num' => $i + 1, 'debut' => $debut, 'fin' => $fin])
                        @endforeach
                        <p class="ab-day__empty" data-day-empty @if (count($info['plages']) > 0) hidden @endif>Aucune plage : ce jour sera enregistré comme fermé.</p>
                    </div>

                    <button type="button" class="link-button ab-day__add" data-add-slot aria-label="Ajouter une plage horaire le {{ $jour }}">
                        <i data-lucide="plus" aria-hidden="true"></i> Ajouter une plage
                    </button>
                    <p class="ab-day__closed-note" aria-hidden="true">Fermé toute la journée</p>
                </fieldset>
            @endforeach
        </div>

        <template data-slot-template>
            @include('back.ateliers.partials.slot', ['jour' => '__JOUR__', 'index' => '__INDEX__', 'num' => '__NUM__', 'debut' => '', 'fin' => ''])
        </template>
    </div>
</section>
