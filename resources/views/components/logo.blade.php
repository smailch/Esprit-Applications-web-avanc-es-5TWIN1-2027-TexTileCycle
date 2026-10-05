@props(['compact' => false, 'light' => false])

<a href="{{ route('front.home') }}" class="logo" @if($light) style="color:white" @endif>
    <span class="logo-mark"><i data-lucide="leaf"></i></span>
    @unless($compact)
        <span>TexTile<span>Cycle</span></span>
    @endunless
</a>
