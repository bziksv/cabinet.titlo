<nav class="mt-2" role="navigation" aria-label="{{ __('Modules') }}">
    <div class="pb-2 cabinet-sidebar-search">
        <div class="input-group input-group-sm">
            <input type="text"
                   class="form-control cabinet-sidebar-search__input"
                   autocomplete="off"
                   placeholder="{{ __('Search') }}"
                   value="">
            <button type="button" class="btn btn-outline-secondary" tabindex="-1" aria-hidden="true">
                <i class="bi bi-search"></i>
            </button>
        </div>
    </div>

    <ul class="nav sidebar-menu flex-column cabinet-sidebar-menu"
        data-lte-toggle="treeview"
        role="menu"
        data-accordion="false">
        @if(isset($modules))
            @foreach($modules as $key => $module)
                @if(!array_key_exists('configurationInfo', $module))
                    @php $itemActive = \App\Support\CabinetSidebarActive::isLinkActive($module['link'] ?? null); @endphp
                    <li class="nav-item menu-item {{ $itemActive ? 'cabinet-sidebar-item--active' : '' }}" data-id="{{ $module['id'] }}">
                        <a class="nav-link search-link {{ $itemActive ? 'active' : '' }}"
                           href="{{ $module['link'] }}"
                           @if($itemActive) aria-current="page" @endif>
                            <span class="nav-icon cabinet-sidebar-menu__icon">{!! $module['icon'] !!}</span>
                            <p class="module-name mb-0">{{ $module['title'] }}</p>
                        </a>
                    </li>
                @elseif(count($module) > 1)
                    @php $folderOpen = \App\Support\CabinetSidebarActive::folderShouldOpen($module); @endphp
                    <li class="nav-item folder menu-item {{ $folderOpen ? 'menu-open' : '' }}"
                        data-action="{{ $module['configurationInfo']['show'] }}">
                        <a href="#" class="nav-link sidebar-folder-toggle">
                            <i class="nav-icon bi bi-folder-fill"></i>
                            <p class="mb-0">
                                {{ $key }}
                                <i class="nav-arrow bi bi-chevron-right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview"
                            @if(!$folderOpen) style="display: none;" @endif>
                            @foreach($module as $k => $elem)
                                @if($k === 'configurationInfo')
                                    @continue
                                @endif
                                @php $childActive = \App\Support\CabinetSidebarActive::isLinkActive($elem['link'] ?? null); @endphp
                                <li class="nav-item {{ $childActive ? 'cabinet-sidebar-item--active' : '' }}" data-id="{{ $elem['id'] }}">
                                    <a class="nav-link search-link {{ $childActive ? 'active' : '' }}"
                                       href="{{ $elem['link'] }}"
                                       @if($childActive) aria-current="page" @endif>
                                        <span class="nav-icon cabinet-sidebar-menu__icon">{!! $elem['icon'] !!}</span>
                                        <p class="module-name mb-0">{{ $elem['title'] }}</p>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endif
            @endforeach
            @php $partnersActive = \App\Support\CabinetSidebarActive::isLinkActive(route('partners')); @endphp
            <li class="nav-item menu-item {{ $partnersActive ? 'cabinet-sidebar-item--active' : '' }}">
                <a class="nav-link search-link {{ $partnersActive ? 'active' : '' }}"
                   href="{{ route('partners') }}"
                   @if($partnersActive) aria-current="page" @endif>
                    <i class="nav-icon bi bi-handshake"></i>
                    <p class="module-name mb-0">{{ __('Partners') }}</p>
                </a>
            </li>
        @else
            <li class="nav-item menu-item">
                <a class="nav-link search-link" href="{{ route('login') }}">
                    <i class="nav-icon bi bi-box-arrow-in-right"></i>
                    <p class="module-name mb-0">{{ __('Login page') }}</p>
                </a>
            </li>
        @endif
    </ul>
</nav>
