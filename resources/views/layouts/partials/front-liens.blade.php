<a href="{{ route('front.home') }}" @class(['active' => request()->routeIs('front.home')])>Accueil</a>
<a href="{{ route('front.ateliers') }}" @class(['active' => request()->routeIs('front.ateliers')])>Ateliers</a>
<a href="{{ route('front.associations') }}" @class(['active' => request()->routeIs('front.associations')])>Associations</a>
@auth
    <a href="{{ route('front.vetements') }}" @class(['active' => request()->routeIs('front.vetements')])>Mes vêtements</a>
    <a href="{{ route('front.historique') }}" @class(['active' => request()->routeIs('front.historique')])>Historique</a>
    <a href="{{ route('front.rdv') }}" @class(['active' => request()->routeIs('front.rdv*')])>Rendez-vous</a>
    <a href="{{ route('front.passeports') }}" @class(['active' => request()->routeIs('front.passeports')])>Passeports</a>
    <a href="{{ route('front.dons') }}" @class(['active' => request()->routeIs('front.dons')])>Dons</a>
    <a href="{{ route('front.signalements.index') }}" @class(['active' => request()->routeIs('front.signalements.*')])>Mes signalements</a>
@endauth
