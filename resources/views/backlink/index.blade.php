@component('component.card', ['title' => __('Link tracking')])
    @slot('css')
        @include('backlink.partials.styles')
    @endslot

    @php
        $projects = collect($backlinks);
        $linksTotal = (int) $projects->sum('total_link');
        $linksBroken = (int) $projects->sum('total_broken_link');
    @endphp

    <div class="cabinet-backlink-page">
        @include('backlink.partials.module-nav', ['active' => 'projects'])

        @include('backlink.partials.free-tariff-email-notice')

        <div class="cabinet-bl-lead px-4 py-3">
            <div class="d-flex gap-3 align-items-start">
                <span class="cabinet-bl-lead__icon" aria-hidden="true">
                    <i class="bi bi-link-45deg"></i>
                </span>
                <div>
                    <p class="mb-1 fw-semibold text-body">{{ __('Backlink index lead title') }}</p>
                    <p class="mb-0 small text-secondary">{{ __('Backlink index lead hint') }}</p>
                </div>
            </div>
        </div>

        @if($projects->isNotEmpty())
            <div class="cabinet-bl-kpi-row">
                <div class="cabinet-bl-kpi">
                    <div class="cabinet-bl-kpi__value">{{ number_format($projects->count(), 0, ',', ' ') }}</div>
                    <div class="cabinet-bl-kpi__label">{{ __('Backlink projects count') }}</div>
                </div>
                <div class="cabinet-bl-kpi">
                    <div class="cabinet-bl-kpi__value">{{ number_format($linksTotal, 0, ',', ' ') }}</div>
                    <div class="cabinet-bl-kpi__label">{{ __('Backlink links total') }}</div>
                </div>
                <div class="cabinet-bl-kpi{{ $linksBroken > 0 ? ' cabinet-bl-kpi--danger' : '' }}">
                    <div class="cabinet-bl-kpi__value">{{ number_format($linksBroken, 0, ',', ' ') }}</div>
                    <div class="cabinet-bl-kpi__label">{{ __('Backlink links broken') }}</div>
                </div>
            </div>
        @endif

        <div class="cabinet-bl-toolbar">
            <p class="mb-0 small text-secondary">{{ __('My Projects') }}</p>
            <div class="cabinet-bl-toolbar__actions d-flex flex-wrap gap-2">
                <a href="{{ route('add.backlink.view') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Add link tracking') }}
                </a>
            </div>
        </div>

        @if($projects->isEmpty())
            <div class="cabinet-bl-empty">
                <i class="bi bi-inbox display-6 text-secondary opacity-50 d-block mb-2" aria-hidden="true"></i>
                <p class="mb-0">{{ __('Backlink empty projects') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle cabinet-bl-index-table mb-0">
                    <thead class="table-light">
                    <tr>
                        <th scope="col">{{ __('Project name') }}</th>
                        <th scope="col" class="text-center">{{ __('Broken links/Total links') }}</th>
                        <th scope="col" class="text-end" style="width: 5rem;">{{ __('Backlink col actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($projects as $backlink)
                        <tr>
                            <td>
                                <a href="{{ route('show.backlink', $backlink->id) }}"
                                   class="cabinet-bl-project-link click_tracking"
                                   data-click="Show project">
                                    {{ $backlink->project_name }}
                                </a>
                            </td>
                            <td class="text-center">
                                @if($backlink->total_broken_link != 0)
                                    <span class="badge text-bg-danger">
                                        {{ $backlink->total_broken_link }}/{{ $backlink->total_link }}
                                    </span>
                                @else
                                    <span class="badge text-bg-success">
                                        {{ $backlink->total_broken_link }}/{{ $backlink->total_link }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form action="{{ route('delete.backlink', $backlink->id) }}"
                                      method="post"
                                      class="d-inline"
                                      onsubmit='return confirm(@json(__('Backlink confirm delete project', ['name' => $backlink->project_name])))'>
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger click_tracking"
                                            type="submit"
                                            data-click="Remove Project"
                                            title="{{ __('Backlink delete project') }}"
                                            aria-label="{{ __('Backlink delete project') }}">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endcomponent
