<?php

namespace App\Http\Controllers;

use App\Support\AiProviderSettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiProvidersAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    public function index(): View
    {
        return view('admin.ai-providers.index', [
            'settings' => AiProviderSettingsRegistry::forAdmin(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $providers = array_keys((array) config('cabinet-ai-providers.providers', []));
        $modules = array_keys((array) config('cabinet-ai-providers.modules', []));

        $data = $request->validate([
            'default_provider' => 'required|string|in:' . implode(',', $providers),
            'module_providers' => 'nullable|array',
            'module_providers.*' => 'nullable|string|in:' . implode(',', $providers),
        ]);

        $map = [];
        foreach ($modules as $code) {
            $map[$code] = (string) ($data['module_providers'][$code] ?? $data['default_provider']);
        }

        AiProviderSettingsRegistry::save([
            'default_provider' => $data['default_provider'],
            'module_providers' => $map,
        ]);

        flash()->overlay(__('AI providers admin saved'), __('Success'))->success();

        return redirect()->route('admin.ai-providers.index');
    }
}
