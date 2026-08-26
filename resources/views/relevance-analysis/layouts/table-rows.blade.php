<tr>
    <th class="table-header ra-hist-date-th">
        <input class="form form-control form-control-sm ra-hist-date-filter" type="date" name="dateMin"
               id="dateMin"
               value="{{ Carbon\Carbon::parse('2022-03-01')->toDateString() }}">
        <input class="form form-control form-control-sm ra-hist-date-filter" type="date" name="dateMax" id="dateMax"
               value="{{ Carbon\Carbon::now()->toDateString() }}">
    </th>
    <th class="ra-hist-actions-th"></th>
    <th>
        <input class="w-100 form form-control search-input" type="text"
               name="projectComment" id="projectComment" placeholder="{{ __('comment') }}">
    </th>
    <th>
        <input class="w-100 form form-control search-input" type="text"
               name="phraseSearch" id="phraseSearch" placeholder="{{ __('phrase') }}">
    </th>
    <th>
        <input class="w-100 form form-control search-input" type="text"
               name="regionSearch" id="regionSearch" placeholder="{{ __('region') }}">
    </th>
    <th>
        <input class="w-100 form form-control search-input" type="text"
               name="mainPageSearch" id="mainPageSearch" placeholder="{{ __('link') }}">
    </th>
    <th>
        <input class="w-100 form form-control search-input" type="number"
               name="minPosition" id="minPosition" placeholder="{{ __('min') }}">
        <input class="w-100 form form-control search-input" type="number"
               name="maxPosition" id="maxPosition" placeholder="{{ __('max') }}">
    </th>
    <th>
        <input class="w-100 form form-control search-input" type="number"
               name="minPoints" id="minPoints" placeholder="{{ __('min') }}">
        <input class="w-100 form form-control search-input" type="number"
               name="maxPoints" id="maxPoints" placeholder="{{ __('max') }}">
    </th>
    <th>
        <input class="w-100 form form-control search-input" type="number"
               name="minCoverage" id="minCoverage" placeholder="{{ __('min') }}">
        <input class="w-100 form form-control search-input" type="number"
               name="maxCoverage" id="maxCoverage" placeholder="{{ __('max') }}">
    </th>
    <th>
        <input class="w-100 form form-control search-input" type="number"
               name="minCoverageTf" id="minCoverageTf" placeholder="{{ __('min') }}">
        <input class="w-100 form form-control search-input" type="number"
               name="maxCoverageTf" id="maxCoverageTf" placeholder="{{ __('max') }}">
    </th>
    <th>
        <input class="w-100 form form-control search-input" type="number" name="minWidth"
               id="minWidth" placeholder="{{ __('min') }}">
        <input class="w-100 form form-control search-input" type="number"
               name="maxWidth" id="maxWidth" placeholder="{{ __('max') }}">
    </th>
    <th>
        <input class="w-100 form form-control search-input" type="number"
               name="minDensity" id="minDensity" placeholder="{{ __('min') }}">
        <input class="w-100 form form-control search-input" type="number"
               name="maxDensity" id="maxDensity" placeholder="{{ __('max') }}">
    </th>
    <th>
        <div>
            {{ __('Switch everything') }}
            <div class='d-flex w-100'>
                <div class='__helper-link ui_tooltip_w'>
                    <div
                        class='custom-control custom-switch custom-switch-off-danger custom-switch-on-success changeAllState'>
                        <input type='checkbox' class='custom-control-input'
                               id='changeAllState'>
                        <label class='custom-control-label' for='changeAllState'></label>
                    </div>
                </div>
            </div>
        </div>

    </th>
</tr>
<tr>
    <th class="table-header">Дата</th>
    <th class="table-header ra-hist-actions-th">Действия</th>
    <th class="table-header">Комментарий</th>
    <th class="table-header">Фраза</th>
    <th class="table-header">Регион</th>
    <th class="table-header">URL</th>
    <th class="table-header">Поз.</th>
    <th class="table-header">Баллы</th>
    <th class="table-header">Покр.</th>
    <th class="table-header">TF</th>
    <th class="table-header">Шир.</th>
    <th class="table-header">Плотн.</th>
    <th class="table-header">В балле</th>
</tr>
