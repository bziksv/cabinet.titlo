<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="name">{{ __('Name') }}</label>
        {!! Form::text('name', null, ['class' => 'form-control', 'id' => 'name', 'placeholder' => __('Display name (optional)')]) !!}
        @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="code">{{ __('Code') }} <span class="text-danger">*</span></label>
        {!! Form::text('code', old('code', $suggestedCode ?? null), [
            'class' => 'form-control font-monospace',
            'id' => 'code',
            'required' => true,
            'pattern' => '^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$',
            'placeholder' => 'TextAnalyzer',
        ]) !!}
        @error('code') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text">{{ __('Used in code to read the limit, e.g. Tariffs::get…') }}</div>
    </div>
    <div class="col-12">
        <label class="form-label" for="description">{{ __('Description') }}</label>
        {!! Form::textarea('description', null, ['class' => 'form-control', 'id' => 'description', 'rows' => 2]) !!}
        @error('description') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="message">{{ __('User message') }}</label>
        {!! Form::textarea('message', null, ['class' => 'form-control font-monospace', 'id' => 'message', 'rows' => 3]) !!}
        @error('message') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text">{{ __('Placeholders') }}: <code>{TARIFF}</code> — {{ __('tariff name') }}, <code>{VALUE}</code> — {{ __('limit value') }}</div>
    </div>
</div>
