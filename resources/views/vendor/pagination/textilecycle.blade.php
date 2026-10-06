@if ($paginator->hasPages())
    <nav class="tc-pagination" aria-label="Pagination des résultats">
        <p class="tc-pagination__summary">
            Page {{ $paginator->currentPage() }} sur {{ $paginator->lastPage() }}
        </p>
        <ul class="tc-pagination__list">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="tc-pagination__link is-disabled" aria-disabled="true">
                        <i data-lucide="chevron-left" aria-hidden="true"></i><span class="tc-pagination__label">Précédent</span>
                    </span>
                @else
                    <a class="tc-pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev" data-ajax-link>
                        <i data-lucide="chevron-left" aria-hidden="true"></i><span class="tc-pagination__label">Précédent</span>
                    </a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="tc-pagination__gap" aria-hidden="true">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="tc-pagination__page is-current" aria-current="page">
                                    <span class="sr-only">Page </span>{{ $page }}
                                </span>
                            @else
                                <a class="tc-pagination__page" href="{{ $url }}" data-ajax-link>
                                    <span class="sr-only">Page </span>{{ $page }}
                                </a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a class="tc-pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next" data-ajax-link>
                        <span class="tc-pagination__label">Suivant</span><i data-lucide="chevron-right" aria-hidden="true"></i>
                    </a>
                @else
                    <span class="tc-pagination__link is-disabled" aria-disabled="true">
                        <span class="tc-pagination__label">Suivant</span><i data-lucide="chevron-right" aria-hidden="true"></i>
                    </span>
                @endif
            </li>
        </ul>
    </nav>
@endif
