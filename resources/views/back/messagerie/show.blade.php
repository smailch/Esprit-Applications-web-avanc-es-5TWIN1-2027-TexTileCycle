@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/messagerie.css') }}">
@endpush

@section('content')
<div class="module">
    @include('back.partials.flash')

    <div class="msg-app est-ouverte">
        @include('messagerie.partials.liste', ['routePrefixe' => 'back.messagerie', 'estAdmin' => true, 'actuelle' => $conversation])
        @include('messagerie.partials.fil', ['routePrefixe' => 'back.messagerie', 'estAdmin' => true])
    </div>
</div>
@endsection
