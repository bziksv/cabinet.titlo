<div class="card shadow-sm mb-3 cabinet-users-legend">
    <div class="card-body py-3">
        <h3 class="h6 mb-2">
            <i class="bi bi-info-circle me-1 text-primary" aria-hidden="true"></i>{{ __('Users table legend') }}
        </h3>
        <div class="row g-3 small">
            <div class="col-12 col-lg-6">
                <p class="fw-semibold mb-1">{{ __('Email column badges') }}</p>
                <ul class="mb-0 ps-3 text-secondary">
                    <li class="mb-2">
                        <span class="badge text-bg-success me-1">{{ __('VERIFIED') }}</span>
                        {{ __('User confirmed email address (field email_verified_at is set). Account passed standard Laravel email verification — link from letter or code on verification page.') }}
                    </li>
                    <li>
                        <span class="badge text-bg-primary me-1">{{ __('The letter has been read') }}</span>
                        {{ __('User entered the verification code from the registration email (read_letter flag). Shown only after code-based confirmation, not email open tracking.') }}
                    </li>
                </ul>
            </div>
            <div class="col-12 col-lg-6">
                <p class="fw-semibold mb-1">{{ __('Unverified accounts cleanup') }}</p>
                <p class="text-secondary mb-2">
                    {{ __('Cron deletes accounts without email verification older than :days days (daily 02:15 only). Test on server: php artisan users:prune-unverified --dry-run. Disable: DELETE_UNVERIFIED_USERS=false.', ['days' => (int) env('DELETE_UNVERIFIED_USERS_DAYS', 30)]) }}
                </p>
                <p class="text-secondary mb-0">
                    <i class="bi bi-funnel me-1"></i>{{ __('Filter «Email verification» applies the same verified / unverified criterion. KPI «Verified» counts users with email_verified_at.') }}
                </p>
            </div>
        </div>
    </div>
</div>
