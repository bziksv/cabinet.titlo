@php
    $isEdit = isset($settingValue) && $settingValue->exists;
@endphp
<div class="row g-3">
    <div class="col-12">
        <label class="form-label" for="tariff">{{ __('Tariff') }} <span class="text-danger">*</span></label>
        @if($isEdit)
            <input type="text"
                   class="form-control"
                   id="tariff"
                   value="{{ $select[$settingValue->tariff] ?? $settingValue->tariff }} ({{ $settingValue->tariff }})"
                   readonly
                   disabled>
            {!! Form::hidden('tariff', $settingValue->tariff) !!}
            <div class="form-text">{{ __('Tariff plan cannot be changed when editing; delete and add again if needed.') }}</div>
        @else
            {!! Form::select('tariff', $select, null, ['class' => 'form-select', 'id' => 'tariff', 'required' => true]) !!}
        @endif
        @error('tariff') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="value">{{ __('Value') }} <span class="text-danger">*</span></label>
        {!! Form::number('value', old('value', 0), ['class' => 'form-control', 'id' => 'value', 'min' => 0, 'required' => true]) !!}
        @error('value') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text">{{ __('Numeric limit for this tariff') }}</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="sort">{{ __('Sort') }}</label>
        {!! Form::number('sort', old('sort', 1), ['class' => 'form-control', 'id' => 'sort', 'min' => 1]) !!}
        @error('sort') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
</div>
