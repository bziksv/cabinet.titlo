<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * Юр. документы живут на titlo.ru — кабинетные URL только редиректят.
 */
class AccessController extends Controller
{
    public function getRuPersonalData(): RedirectResponse
    {
        return $this->awayMarketing('/legal/doc/personal-data-consent/');
    }

    public function getEnPersonalData(): RedirectResponse
    {
        return $this->awayMarketing('/legal/doc/personal-data-consent/');
    }

    public function getRuPrivacyPolicy(): RedirectResponse
    {
        return $this->awayMarketing('/legal/doc/privacy-policy/');
    }

    public function getEnPrivacyPolicy(): RedirectResponse
    {
        return $this->awayMarketing('/legal/doc/privacy-policy/');
    }

    private function awayMarketing(string $path): RedirectResponse
    {
        $base = app()->environment('local')
            ? 'http://localhost:3001'
            : 'https://titlo.ru';

        return redirect()->away(rtrim($base, '/') . $path);
    }
}
