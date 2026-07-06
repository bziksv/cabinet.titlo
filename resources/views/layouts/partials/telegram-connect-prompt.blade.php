@if($showTelegramConnectPrompt ?? false)
    <div class="modal fade" id="cabinet-telegram-connect-modal" tabindex="-1"
         aria-labelledby="cabinet-telegram-connect-title" aria-hidden="true"
         data-snooze-url="{{ route('profile.telegram-connect-prompt.snooze') }}">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content cabinet-telegram-connect-modal">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="cabinet-telegram-connect-title">
                        <i class="bi bi-telegram text-info me-2" aria-hidden="true"></i>
                        {{ __('Connect Telegram bot') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body pt-2">
                    @if(($telegramConnectBonusEligible ?? false) && ($telegramConnectBonusAmount ?? 0) > 0)
                        <div class="cabinet-telegram-connect-modal__bonus mb-3" role="note">
                            <div class="cabinet-telegram-connect-modal__bonus-badge">
                                <i class="bi bi-gift-fill" aria-hidden="true"></i>
                                {{ __('Telegram connect bonus badge') }}
                            </div>
                            <p class="cabinet-telegram-connect-modal__bonus-title mb-1">
                                {{ __('Telegram connect bonus title', ['amount' => number_format($telegramConnectBonusAmount, 0, '.', ' ')]) }}
                            </p>
                            <p class="cabinet-telegram-connect-modal__bonus-text mb-0">
                                {{ __('Telegram connect bonus description') }}
                            </p>
                        </div>
                    @endif
                    <p class="mb-3">
                        {{ __('Connect our Telegram bot so you do not miss project updates: statuses, monitoring alerts, and other important events.') }}
                    </p>
                    <ul class="list-unstyled small text-secondary mb-0 cabinet-telegram-connect-modal__list">
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2" aria-hidden="true"></i>
                            {{ __('Project and task status notifications') }}
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2" aria-hidden="true"></i>
                            {{ __('Monitoring: domain issues, limits, DNS changes') }}
                        </li>
                        <li>
                            <i class="bi bi-check-circle-fill text-success me-2" aria-hidden="true"></i>
                            {{ __('Clustering and analysis completion alerts') }}
                        </li>
                    </ul>
                    <div class="alert alert-info border-info small mt-3 mb-0" role="note">
                        <i class="bi bi-clock-history me-1" aria-hidden="true"></i>
                        {{ __('Telegram connect polling notice') }}
                    </div>
                </div>
                <div class="modal-footer border-0 flex-wrap gap-2 justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" id="cabinet-telegram-connect-snooze">
                        {{ __('Remind me in 2 weeks') }}
                    </button>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ $telegramBotSubscribeUrl }}"
                           class="btn btn-primary"
                           target="_blank"
                           rel="noopener noreferrer">
                            <i class="bi bi-telegram me-1" aria-hidden="true"></i>
                            {{ __('Subscribe to notifications') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            function snoozeTelegramConnectPrompt(callback) {
                var modalEl = document.getElementById('cabinet-telegram-connect-modal');
                if (!modalEl) {
                    return;
                }
                var url = modalEl.getAttribute('data-snooze-url');
                var token = document.querySelector('meta[name="csrf-token"]');
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('snooze failed');
                    }
                    return response.json().catch(function () {
                        return {};
                    });
                }).then(function () {
                    if (typeof callback === 'function') {
                        callback();
                    }
                }).catch(function () {
                    if (snoozeBtn) {
                        snoozeBtn.disabled = false;
                    }
                });
            }

            var snoozeBtn;

            document.addEventListener('DOMContentLoaded', function () {
                var modalEl = document.getElementById('cabinet-telegram-connect-modal');
                if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                    return;
                }

                var modal = bootstrap.Modal.getOrCreateInstance(modalEl, {backdrop: true, keyboard: true});
                if (window.CabinetModalQueue) {
                    window.CabinetModalQueue.enqueue(modalEl, 10);
                } else {
                    modal.show();
                }

                snoozeBtn = document.getElementById('cabinet-telegram-connect-snooze');
                if (snoozeBtn) {
                    snoozeBtn.addEventListener('click', function () {
                        snoozeBtn.disabled = true;
                        snoozeTelegramConnectPrompt(function () {
                            modal.hide();
                        });
                    });
                }
            });
        })();
    </script>
@endif
