{{-- Pied de page du site public. $trame = true : version « trame » (fond sombre, aligné sur la trame). --}}
<footer @class(['footer', 'tr-pied' => $trame ?? false])>
    <div class="container footer-grid">
        <div>
            <x-logo :light="true" />
            <p>La plateforme tunisienne de l'économie circulaire textile.</p>
        </div>
        <div>
            <b>Explorer</b>
            <a href="{{ route('front.ateliers') }}">Ateliers</a>
            <a href="{{ route('front.associations') }}">Associations</a>
            <a href="{{ route('front.home') }}">Notre impact</a>
        </div>
        <div>
            <b>Ressources</b>
            <a href="{{ route('front.home') }}">Comment ça marche</a>
            <a href="#">FAQ</a>
            <a href="#">Contact</a>
        </div>
        <div>
            <b>Suivez-nous</b>
            <p>hello@textilecycle.tn</p>
            <p>Tunis, Tunisie</p>
        </div>
    </div>
    <div class="container footer-bottom">© 2026 TexTileCycle <span>Fait avec soin pour la planète.</span></div>
</footer>
