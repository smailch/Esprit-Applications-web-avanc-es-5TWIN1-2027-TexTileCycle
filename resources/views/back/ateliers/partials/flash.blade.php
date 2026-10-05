@if (session('success'))
    <div class="ab-alert ab-alert--success" role="status">
        <i data-lucide="check-circle-2" aria-hidden="true"></i>
        <p>{{ session('success') }}</p>
    </div>
@endif

@if (session('info'))
    <div class="ab-alert ab-alert--info" role="status">
        <i data-lucide="info" aria-hidden="true"></i>
        <p>{{ session('info') }}</p>
    </div>
@endif

@if (session('error'))
    <div class="ab-alert ab-alert--error" role="alert">
        <i data-lucide="alert-triangle" aria-hidden="true"></i>
        <p>{{ session('error') }}</p>
    </div>
@endif

@if ($errors->any() && empty($masquerErreurs))
    <div class="ab-alert ab-alert--error" role="alert">
        <i data-lucide="alert-triangle" aria-hidden="true"></i>
        <div>
            <p><strong>{{ $errors->count() > 1 ? 'Certains champs sont à corriger :' : 'Un champ est à corriger :' }}</strong></p>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
