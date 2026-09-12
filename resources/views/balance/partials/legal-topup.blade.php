@php
    $editCompany = $editCompany ?? null;
    $companyLogs = $companyLogs ?? collect();
    $fieldLabels = \App\UserCompanyLog::fieldLabels();
    $companyFieldErrors = $errors->has('name')
        || $errors->has('inn')
        || $errors->has('email')
        || $errors->has('legal_address')
        || $errors->has('postal_address')
        || $errors->has('phone');
    $openCompanyModal = (bool) $editCompany
        || $companyFieldErrors
        || request('open') === 'company';
@endphp

<div class="card card-outline card-primary mb-4">
    <div class="card-header">
        <h3 class="card-title mb-0">
            <i class="bi bi-building me-1"></i>{{ __('Legal entity top up') }}
        </h3>
    </div>
    <div class="card-body">
        <p class="text-secondary small mb-3">{{ __('Legal entity top up lead') }}</p>

        <div class="row g-3 mb-4">
            @forelse($companies as $company)
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="border rounded p-3 h-100 d-flex flex-column {{ $editCompany && (int) $editCompany->id === (int) $company->id ? 'border-primary' : '' }}">
                        <div class="fw-semibold">{{ $company->name }}</div>
                        <div class="small text-secondary">ИНН {{ $company->inn }}</div>
                        <div class="mt-2 fs-5 fw-bold">{{ number_format((int) $company->balance, 0, '', ' ') }} ₽</div>
                        <div class="small text-secondary mt-1">{{ $company->email }}</div>
                        <div class="mt-auto pt-3 d-flex flex-wrap gap-2">
                            <a href="{{ route('balance.index', ['tab' => 'legal', 'edit' => $company->id]) }}"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil me-1"></i>{{ __('Edit') }}
                            </a>
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#company-logs-modal-{{ $company->id }}">
                                <i class="bi bi-clock-history me-1"></i>{{ __('Company change log short') }}
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-light border mb-0">{{ __('No companies yet') }}</div>
                </div>
            @endforelse

            <div class="col-12 col-md-6 col-lg-4">
                @if($editCompany)
                    <a href="{{ route('balance.index', ['tab' => 'legal', 'open' => 'company']) }}"
                       class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 py-4 border-dashed">
                        <i class="bi bi-plus-lg fs-4"></i>
                        <span>{{ __('Add company') }}</span>
                    </a>
                @else
                    <button type="button"
                            class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 py-4 border-dashed"
                            data-bs-toggle="modal"
                            data-bs-target="#company-form-modal">
                        <i class="bi bi-plus-lg fs-4"></i>
                        <span>{{ __('Add company') }}</span>
                    </button>
                @endif
            </div>
        </div>

        @if($companies->isNotEmpty())
            <hr>
            <h4 class="h6 mb-3">{{ __('Issue invoice') }}</h4>
            <form method="post" action="{{ route('balance.invoices.store') }}" class="mb-2" id="invoice-create-form">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="invoice-company">{{ __('Company') }}</label>
                    <select name="user_company_id"
                            id="invoice-company"
                            class="form-select @error('user_company_id') is-invalid @enderror"
                            required
                            data-placeholder="{{ __('Select company') }}">
                        <option value=""></option>
                        @php
                            $selectedInvoiceCompanyId = old('user_company_id');
                            if ($selectedInvoiceCompanyId === null && $companies->count() === 1) {
                                $selectedInvoiceCompanyId = $companies->first()->id;
                            }
                        @endphp
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @if((string) $selectedInvoiceCompanyId === (string) $company->id) selected @endif>
                                {{ $company->label() }} · {{ number_format((int) $company->balance, 0, '', ' ') }} ₽
                            </option>
                        @endforeach
                    </select>
                    @error('user_company_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="row g-3 align-items-start">
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="form-label" for="invoice-sum">{{ __('Sum') }}</label>
                        @php
                            $minInvoiceSum = (int) config('cabinet-billing.min_invoice_amount', 10000);
                            $invoiceSumValue = old('sum');
                            if ($invoiceSumValue !== null && $invoiceSumValue !== '') {
                                $invoiceSumValue = number_format((int) preg_replace('/\D+/', '', (string) $invoiceSumValue), 0, '', ' ');
                            }
                        @endphp
                        <div class="input-group">
                            <input type="text"
                                   name="sum"
                                   id="invoice-sum"
                                   inputmode="numeric"
                                   autocomplete="off"
                                   class="form-control sa-num-space @error('sum') is-invalid @enderror"
                                   value="{{ $invoiceSumValue }}"
                                   required
                                   data-min="{{ $minInvoiceSum }}"
                                   data-max="10000000"
                                   placeholder="{{ number_format($minInvoiceSum, 0, '', ' ') }}">
                            <span class="input-group-text">₽</span>
                        </div>
                        @error('sum')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @else
                            <div class="form-text">{{ __('Invoice min sum hint', ['min' => number_format($minInvoiceSum, 0, '', ' ')]) }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="form-label d-none d-md-block" aria-hidden="true">&nbsp;</label>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-file-earmark-text me-1"></i>{{ __('Create invoice') }}
                        </button>
                    </div>
                </div>
            </form>
            <p class="form-text mb-0">{{ __('Invoice after pay admin') }}</p>
        @endif
    </div>
</div>

<div class="modal fade" id="company-form-modal" tabindex="-1" aria-labelledby="company-form-modal-title" aria-hidden="true"
     data-open-on-load="{{ $openCompanyModal ? '1' : '0' }}">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post"
                  action="{{ $editCompany ? route('balance.companies.update', $editCompany) : route('balance.companies.store') }}">
                @csrf
                @if($editCompany)
                    @method('PUT')
                    <input type="hidden" name="_company_edit_id" value="{{ $editCompany->id }}">
                @endif
                <div class="modal-header">
                    <h5 class="modal-title" id="company-form-modal-title">
                        {{ $editCompany ? __('Edit company') : __('Add company') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="company-name">{{ __('Company name') }}</label>
                            <input type="text" name="name" id="company-name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', optional($editCompany)->name) }}" required maxlength="255">
                            @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="company-inn">ИНН</label>
                            <input type="text" name="inn" id="company-inn" class="form-control @error('inn') is-invalid @enderror"
                                   value="{{ old('inn', optional($editCompany)->inn) }}" required maxlength="12" inputmode="numeric">
                            @error('inn')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="company-email">Email</label>
                            <input type="email" name="email" id="company-email" class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email', optional($editCompany)->email) }}" required maxlength="255">
                            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="company-legal-address">{{ __('Legal address') }}</label>
                            <input type="text" name="legal_address" id="company-legal-address" class="form-control @error('legal_address') is-invalid @enderror"
                                   value="{{ old('legal_address', optional($editCompany)->legal_address) }}" required maxlength="500">
                            @error('legal_address')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="company-postal-same" value="1"
                                       @if(old('postal_same_as_legal')) checked @endif>
                                <label class="form-check-label" for="company-postal-same">{{ __('Postal same as legal') }}</label>
                            </div>
                            <label class="form-label" for="company-postal-address">{{ __('Postal address') }}</label>
                            <input type="text" name="postal_address" id="company-postal-address" class="form-control @error('postal_address') is-invalid @enderror"
                                   value="{{ old('postal_address', optional($editCompany)->postal_address) }}" required maxlength="500">
                            @error('postal_address')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="company-phone">{{ __('Phone') }}</label>
                            <input type="text" name="phone" id="company-phone" class="form-control @error('phone') is-invalid @enderror"
                                   value="{{ old('phone', optional($editCompany)->phone) }}" required maxlength="64">
                            @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    @if($editCompany)
                        <a href="{{ route('balance.index', ['tab' => 'legal']) }}" class="btn btn-outline-secondary">
                            {{ __('Cancel') }}
                        </a>
                    @else
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    @endif
                    <button type="submit" class="btn btn-primary">
                        {{ $editCompany ? __('Save company') : __('Add company') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach($companies as $company)
    @php
        $logsForCompany = $companyLogs->where('user_company_id', (int) $company->id)->values();
    @endphp
    <div class="modal fade" id="company-logs-modal-{{ $company->id }}" tabindex="-1"
         aria-labelledby="company-logs-modal-title-{{ $company->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="company-logs-modal-title-{{ $company->id }}">
                        <i class="bi bi-clock-history me-1"></i>{{ __('Company change log') }}: {{ $company->name }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body p-0">
                    @if($logsForCompany->isEmpty())
                        <div class="p-4 text-secondary small mb-0">{{ __('Company change log empty') }}</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Action') }}</th>
                                    <th>{{ __('Changes') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($logsForCompany as $log)
                                    <tr>
                                        <td class="text-nowrap small">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                                        <td>
                                            @if($log->action === 'created')
                                                <span class="badge text-bg-success">{{ __('Company log created') }}</span>
                                            @elseif($log->action === 'deleted')
                                                <span class="badge text-bg-danger">{{ __('Company log deleted') }}</span>
                                            @else
                                                <span class="badge text-bg-primary">{{ __('Company log updated') }}</span>
                                            @endif
                                        </td>
                                        <td class="small">
                                            @if($log->action === 'updated' && is_array($log->changes))
                                                <ul class="mb-0 ps-3">
                                                    @foreach($log->changes as $field => $pair)
                                                        @if(is_array($pair))
                                                            <li>
                                                                <strong>{{ $fieldLabels[$field] ?? $field }}:</strong>
                                                                <span class="text-secondary">{{ ($pair['from'] ?? '') !== '' ? $pair['from'] : '—' }}</span>
                                                                →
                                                                <span>{{ ($pair['to'] ?? '') !== '' ? $pair['to'] : '—' }}</span>
                                                            </li>
                                                        @endif
                                                    @endforeach
                                                </ul>
                                            @elseif($log->action === 'created')
                                                {{ __('Company log created detail') }}
                                            @elseif($log->action === 'deleted')
                                                {{ __('Company log deleted detail') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>
@endforeach

@if($companyInvoices->isNotEmpty())
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title mb-0">
                <i class="bi bi-receipt me-1"></i>{{ __('Company invoices') }}
            </h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>{{ __('Invoice number') }}</th>
                        <th>{{ __('Company') }}</th>
                        <th class="text-end">{{ __('Sum') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Date') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($companyInvoices as $invoice)
                        <tr>
                            <td class="text-nowrap">{{ $invoice->number }}</td>
                            <td>{{ optional($invoice->company)->name }}</td>
                            <td class="text-end text-nowrap">{{ number_format((int) $invoice->amount, 0, '', ' ') }} ₽</td>
                            <td>
                                @if($invoice->isPaid())
                                    <span class="badge text-bg-success">{{ __('Invoice paid') }}</span>
                                @elseif($invoice->isCancelled())
                                    <span class="badge text-bg-secondary">{{ __('Invoice cancelled status') }}</span>
                                @else
                                    <span class="badge text-bg-warning">{{ __('Invoice pending') }}</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap small">{{ optional($invoice->issued_at)->format('d.m.Y H:i') }}</td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('balance.invoice.download', $invoice) }}">PDF</a>
                                @if($invoice->isPaid())
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('balance.invoice.act', $invoice) }}">{{ __('Act') }}</a>
                                @endif
                                @if($invoice->isPending())
                                    <form method="post" action="{{ route('balance.invoices.cancel', $invoice) }}" class="d-inline js-confirm-submit"
                                          data-confirm="{{ __('Cancel invoice confirm') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Cancel') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
