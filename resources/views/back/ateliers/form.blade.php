@php
    $estEdition = $atelier->exists;
    $action = $estEdition
        ? route('back.ateliers.update', ['id' => (string) $atelier->getKey()])
        : route('back.ateliers.store');
    $statutCourant = old('statut', $atelier->statut ?? \App\Modules\Ateliers\Models\Atelier::STATUT_EN_ATTENTE);
@endphp

<form method="post" action="{{ $action }}" class="ab-form" data-atelier-form>
    @csrf
    @if ($estEdition)
        @method('PUT')
    @endif

    <div class="ab-form__grid">
        <div class="ab-form__col">
            {{-- a) Identité --}}
            <section class="panel ab-section" aria-labelledby="sec-identite">
                <div class="panel-head">
                    <div>
                        <h2 id="sec-identite">Identité</h2>
                        <p>Compte propriétaire et présentation publique de l'atelier</p>
                    </div>
                </div>
                <div class="ab-section__body">
                    @if ($estEdition)
                        @include('back.ateliers.partials.compte-lecture', ['compte' => $atelier->user, 'userIdCache' => $atelier->user_id])
                    @else
                        <div @class(['ab-field', 'has-error' => $errors->has('user_id')])>
                            <label for="f-user_id" class="ab-label">Compte utilisateur <span class="ab-required" aria-hidden="true">*</span></label>
                            <select id="f-user_id" name="user_id" class="ab-input" required
                                    aria-describedby="f-user_id-hint @error('user_id') f-user_id-error @enderror"
                                    @error('user_id') aria-invalid="true" @enderror
                                    @disabled($comptes->isEmpty())>
                                <option value="">{{ $comptes->isEmpty() ? 'Aucun compte disponible' : 'Choisir un compte atelier' }}</option>
                                @foreach ($comptes as $compte)
                                    <option value="{{ $compte->getKey() }}" @selected(old('user_id') === (string) $compte->getKey())>{{ $compte->name }} · {{ $compte->email }}</option>
                                @endforeach
                            </select>
                            <p id="f-user_id-hint" class="ab-hint">
                                @if ($comptes->isEmpty())
                                    Tous les comptes de rôle atelier ont déjà un atelier. Créez d'abord un compte dans
                                    <a href="{{ route('back.users.index') }}">Utilisateurs</a>.
                                @else
                                    Seuls les comptes de rôle atelier sans atelier sont proposés.
                                @endif
                            </p>
                            @error('user_id')
                                <p id="f-user_id-error" class="ab-error"><i data-lucide="alert-circle" aria-hidden="true"></i> {{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    @include('back.ateliers.partials.identite-champs')
                </div>
            </section>

            {{-- c) Horaires --}}
            @include('back.ateliers.partials.horaires-editeur')
        </div>

        <div class="ab-form__col">
            {{-- d) Statut --}}
            <section class="panel ab-section" aria-labelledby="sec-statut">
                <div class="panel-head">
                    <div>
                        <h2 id="sec-statut">Statut</h2>
                        <p>Visibilité de l'atelier sur le site</p>
                    </div>
                    @if ($estEdition)
                        <x-status-badge :tone="$atelier->statutTone()">{{ $atelier->statutLabel() }}</x-status-badge>
                    @endif
                </div>
                <div class="ab-section__body">
                    <div @class(['ab-field', 'has-error' => $errors->has('statut')])>
                        <label for="f-statut" class="ab-label">Statut <span class="ab-required" aria-hidden="true">*</span></label>
                        <select id="f-statut" name="statut" class="ab-input" required aria-describedby="f-statut-hint @error('statut') f-statut-error @enderror" @error('statut') aria-invalid="true" @enderror>
                            @foreach ($statuts as $statut)
                                <option value="{{ $statut }}" @selected($statutCourant === $statut)>{{ \App\Modules\Ateliers\Models\Atelier::libelleStatut($statut) }}</option>
                            @endforeach
                        </select>
                        <ul id="f-statut-hint" class="ab-status-help">
                            <li><span class="status status-orange">En attente</span> invisible, en cours de validation</li>
                            <li><span class="status status-green">Actif</span> visible sur le site et réservable</li>
                            <li><span class="status status-purple">Suspendu</span> masqué temporairement</li>
                        </ul>
                        @error('statut')
                            <p id="f-statut-error" class="ab-error"><i data-lucide="alert-circle" aria-hidden="true"></i> {{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            {{-- b) Localisation --}}
            @include('back.ateliers.partials.localisation')

            {{-- e) Services (lecture seule) --}}
            @if ($estEdition)
                <section class="panel ab-section" aria-labelledby="sec-services">
                    <div class="panel-head">
                        <div>
                            <h2 id="sec-services">Services</h2>
                            <p>Gérés par l'atelier depuis son espace : consultation seule.</p>
                        </div>
                        <span class="ab-count">{{ $atelier->services->count() }}</span>
                    </div>
                    @if ($atelier->services->isEmpty())
                        <p class="ab-section__empty">Cet atelier n'a encore publié aucun service.</p>
                    @else
                        <ul class="ab-services">
                            @foreach ($atelier->services as $service)
                                <li>
                                    <span class="ab-services__name">{{ $service->nom }}</span>
                                    <span class="ab-services__meta">
                                        @if ($service->dureeFormatee())
                                            <span><i data-lucide="clock" aria-hidden="true"></i><span class="sr-only">Durée : </span>{{ $service->dureeFormatee() }}</span>
                                        @endif
                                        @if ($service->prixFormate())
                                            <b><span class="sr-only">Prix : </span>{{ $service->prixFormate() }}</b>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        </div>
    </div>

    @include('back.ateliers.partials.form-actions', [
        'annulerUrl' => route('back.ateliers'),
        'libelle' => $estEdition ? 'Enregistrer les modifications' : "Créer l'atelier",
    ])
</form>
