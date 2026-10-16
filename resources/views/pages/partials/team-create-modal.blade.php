{{-- Единый попап создания команды (профиль / чеклист / аудит): название + участники --}}
@php
    $returnTo = $returnTo ?? null;
    $modalId = $modalId ?? 'cabinet-team-create-modal';
    $showManageLink = isset($showManageLink) ? (bool) $showManageLink : ($returnTo !== 'profile');
    $lead = $lead ?? 'Команда общая для Чеклиста, SEO-отчётов и Аудита сайта.';
    $teamRoleLabels = $teamRoleLabels ?? \App\SeoChecklist\SeoChecklistTeam::roleLabels();
    $teamCandidates = $teamCandidates ?? collect();
    $inviteRoles = collect($teamRoleLabels)->except(['owner']);
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="post" action="{{ route('pages.seo-checklist.teams.store') }}" data-team-create-form>
                @csrf
                @if($returnTo)
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                @endif
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $modalId }}-title">Новая команда</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-3">{{ $lead }}</p>

                    <div class="mb-3">
                        <label class="form-label fw-medium" for="{{ $modalId }}-title-input">Название</label>
                        <input type="text"
                               name="title"
                               id="{{ $modalId }}-title-input"
                               class="form-control"
                               required
                               maxlength="120"
                               placeholder="Например: Команда клиента X"
                               autocomplete="off"
                               value="{{ old('title') }}">
                    </div>

                    <div class="cabinet-team-create-members">
                        <div class="d-flex align-items-baseline justify-content-between gap-2 mb-2">
                            <label class="form-label fw-medium mb-0">Участники</label>
                            <span class="small text-secondary">Вы уже Owner. Остальных добавьте здесь.</span>
                        </div>

                        <ul class="list-group list-group-flush border rounded mb-2 d-none" data-team-members-list></ul>
                        <p class="small text-secondary mb-2" data-team-members-empty>Пока никого в списке — добавьте участников ниже или создайте команду и дозаполните позже.</p>

                        <div class="row g-2 align-items-end" data-team-member-row>
                            <div class="col-md-4">
                                <label class="form-label small mb-1" for="{{ $modalId }}-user">Из известных</label>
                                <select id="{{ $modalId }}-user" name="user_id" class="form-select form-select-sm" data-team-member-user>
                                    <option value="">Выберите…</option>
                                    @foreach($teamCandidates as $cand)
                                        @php
                                            $candLabel = trim(($cand->name ?? '') . ' ' . ($cand->last_name ?? '')) ?: $cand->email;
                                        @endphp
                                        <option value="{{ $cand->id }}"
                                                data-email="{{ e($cand->email) }}"
                                                data-label="{{ e($candLabel) }}">
                                            {{ $candLabel }} · {{ $cand->email }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1" for="{{ $modalId }}-email">Или email</label>
                                <input type="email"
                                       id="{{ $modalId }}-email"
                                       name="email"
                                       class="form-control form-control-sm"
                                       placeholder="user@example.com"
                                       autocomplete="off"
                                       data-team-member-email>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1" for="{{ $modalId }}-role">Роль</label>
                                <select id="{{ $modalId }}-role" name="role" class="form-select form-select-sm" data-team-member-role>
                                    @foreach($inviteRoles as $rk => $rl)
                                        <option value="{{ $rk }}" @if($rk === 'participant') selected @endif>{{ $rl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" data-team-member-add>
                                    Добавить
                                </button>
                            </div>
                        </div>
                        <p class="small text-danger mb-0 mt-2 d-none" data-team-member-error></p>
                        <div class="form-text mt-2">
                            Участник должен уже быть зарегистрирован в кабинете (приглашение по email ищет существующий аккаунт).
                        </div>
                        <div data-team-members-hidden></div>
                    </div>
                </div>
                <div class="modal-footer{{ $showManageLink ? ' justify-content-between' : '' }}">
                    @if($showManageLink)
                        <a href="{{ route('profile.index') }}#team" class="btn btn-link btn-sm px-0">Управление командами</a>
                    @endif
                    <div class="d-flex gap-2 ms-auto">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Создать</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
