<?php

namespace App\Http\Controllers;

use App\Support\TariffTierOrder;
use App\TariffSetting;
use App\TariffSettingValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TariffSettingValuesController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    public function create(): View
    {
        $select = TariffTierOrder::labelsMap();
        $setting = TariffSetting::findOrFail(request('id'));

        return view('tariff-setting-values.create', compact('select', 'setting'));
    }

    public function edit(TariffSettingValue $tariffSettingValue): View
    {
        $settingValue = $tariffSettingValue;
        $setting = $settingValue->property ?? TariffSetting::findOrFail($settingValue->tariff_setting_id);
        $select = TariffTierOrder::labelsMap();

        return view('tariff-setting-values.edit', compact('select', 'setting', 'settingValue'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedValue($request);
        $settingId = (int) $data['tariff_setting_id'];

        if ($this->duplicateExists($settingId, $data['tariff'])) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'tariff' => __('A value for this tariff already exists. Use Edit on the limits page.'),
                ]);
        }

        $data['sort'] = $data['sort'] ?? TariffTierOrder::sortKey((string) $data['tariff']) + 1;
        TariffSettingValue::create($data);

        return $this->redirectToSetting($settingId);
    }

    public function update(Request $request, TariffSettingValue $tariffSettingValue): RedirectResponse
    {
        $data = $this->validatedValue($request, $tariffSettingValue->id);

        if ($this->duplicateExists(
            (int) $tariffSettingValue->tariff_setting_id,
            $data['tariff'],
            $tariffSettingValue->id
        )) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'tariff' => __('A value for this tariff already exists.'),
                ]);
        }

        $tariffSettingValue->update($data);

        return $this->redirectToSetting((int) $tariffSettingValue->tariff_setting_id);
    }

    /**
     * @param TariffSettingValue $settingValue
     * @return RedirectResponse
     * @throws \Exception
     */
    public function destroy(TariffSettingValue $settingValue): RedirectResponse
    {
        $settingId = (int) $settingValue->tariff_setting_id;
        $settingValue->delete();

        return $this->redirectToSetting($settingId);
    }

    /** @return array<string, mixed> */
    protected function validatedValue(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'tariff_setting_id' => 'required|integer|exists:tariff_settings,id',
            'tariff' => 'required|string|max:64',
            'value' => 'required|numeric|min:0',
            'sort' => 'nullable|integer|min:0',
        ]);

        $data['value'] = (int) $data['value'];
        if (array_key_exists('sort', $data) && $data['sort'] !== null) {
            $data['sort'] = (int) $data['sort'];
        }

        return $data;
    }

    protected function duplicateExists(int $settingId, string $tariff, ?int $ignoreId = null): bool
    {
        $query = TariffSettingValue::where('tariff_setting_id', $settingId)
            ->where('tariff', $tariff);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    protected function redirectToSetting(int $settingId): RedirectResponse
    {
        $setting = TariffSetting::find($settingId);
        $anchor = $setting ? '#' . $setting->code : '';

        return redirect()->to(url('tariff-settings') . $anchor);
    }
}
