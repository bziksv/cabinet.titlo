<div class="mb-3">
    {!! Form::label('tariff', __('Tariff'), ['class' => 'form-label']) !!}
    {!! Form::select('tariff', $select['tariffs'], null, ['class' => 'form-select', 'id' => 'tariff']) !!}
    @error('tariff')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    {!! Form::label('period', __('Period'), ['class' => 'form-label']) !!}
    <select name="period" id="period" class="form-select">
        @foreach($select['periods'] as $key => $value)
            <option value="{{ $key }}">{{ __($value) }}</option>
        @endforeach
    </select>
    @error('period')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

@php $payCompanies = $payCompanies ?? collect(); @endphp
<div class="mb-0">
    <label class="form-label" for="tariff-wallet">{{ __('Tariff pay wallet') }}</label>
    <select name="wallet" id="tariff-wallet" class="form-select">
        <option value="personal" data-company="">
            {{ __('Tariff pay personal wallet', ['sum' => number_format((int) Auth::user()->balance, 0, '', ' ') . ' ₽']) }}
        </option>
        @foreach($payCompanies as $company)
            <option value="company" data-company="{{ $company->id }}">
                {{ __('Tariff pay company wallet', [
                    'name' => $company->name,
                    'sum' => number_format((int) $company->balance, 0, '', ' ') . ' ₽',
                ]) }}
            </option>
        @endforeach
    </select>
    <input type="hidden" name="user_company_id" id="tariff-wallet-company-id" value="">
</div>
<script>
    (function () {
        var sel = document.getElementById('tariff-wallet');
        var hid = document.getElementById('tariff-wallet-company-id');
        if (!sel || !hid) return;
        function sync() {
            var opt = sel.options[sel.selectedIndex];
            hid.value = opt && opt.getAttribute('data-company') ? opt.getAttribute('data-company') : '';
        }
        sel.addEventListener('change', sync);
        sync();
    })();
</script>
