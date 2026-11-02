{{-- Étiquette QR à imprimer, coudre ou agrafer sur la pièce. Page autonome : rien d'autre ne s'imprime. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Étiquette {{ $passeport->numeroCourt() }} · TexTileCycle</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('css/passeport-etiquette.css') }}">
</head>
<body>
    <main class="pe-page">
        <div class="pe-barre">
            <p>Étiquette du passeport <strong>{{ $passeport->numeroCourt() }}</strong> · {{ $passeport->vetementAffiche() }}</p>
            <button type="button" class="pe-bouton" data-imprimer>Imprimer</button>
            <p class="pe-aide">Sans le bouton : Ctrl + P. Découpez le long des pointillés.</p>
        </div>

        <article class="pe-etiquette" aria-label="Étiquette à imprimer">
            <header class="pe-etiquette__tete">
                <span class="pe-logo" aria-hidden="true">TC</span>
                <div>
                    <p class="pe-kicker">TexTileCycle</p>
                    <h1>Passeport de réparation</h1>
                </div>
            </header>

            <img class="pe-qr" src="{{ route('front.passeport.qr', ['code' => $passeport->code]) }}" alt="QR code du passeport {{ $passeport->numeroCourt() }}">

            <p class="pe-numero">{{ $passeport->numeroCourt() }}</p>
            <p class="pe-consigne">Scannez pour voir l'historique de cette pièce</p>

            <footer class="pe-etiquette__pied">
                <span>{{ $passeport->reparationAffichee() }}</span>
                <span>{{ $passeport->info('atelier') }} · {{ $passeport->dateReparationAffichee() }}</span>
                @if ($passeport->garantieJours() > 0)
                    <span>Garantie {{ $passeport->garantieJours() }} jours</span>
                @endif
            </footer>
        </article>

        <p class="pe-url">{{ $url }}</p>
    </main>

    <script src="{{ asset('js/passeport-etiquette.js') }}" defer></script>
</body>
</html>
