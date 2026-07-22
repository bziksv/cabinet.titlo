<?php

namespace App\Http\Controllers;

use App\Services\UserNotificationPreferenceService;
use App\Support\SignupEmailPolicy;
use App\TariffSetting;
use App\TariffSettingUserValue;
use App\TelegramBot;
use App\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfilesController extends Controller
{
    /**
     * @var
     */
    protected $user;

    /**
     * ProfilesController constructor.
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();

            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $lang = collect(Storage::disk('lang')->files())->mapWithKeys(function ($val) {
            $str = Str::before($val, '.');
            return [$str => __($str)];
        });

        /** @var User $user */
        $user = $this->user;
        $tariff = $user->tariff();
        $name = ($tariff) ? $tariff->name() : null;
        $tariffValidUntil = $user->tariffValidUntilLabel();

        $tariffProperties = [];
        $tariffSettings = $user->tariffSettings()
            ->with(['field.property'])
            ->get();
        foreach ($tariffSettings as $tariffSetting) {
            $property = $tariffSetting->field->property;
            $tariffProperties[$property['id']]['setting'] = $property;
            $tariffProperties[$property['id']]['ids'][] = $tariffSetting['id'];
            $tariffProperties[$property['id']]['fields'][] = $tariffSetting;
        }

        return view('profile.index', [
            'user' => $user,
            'lang' => $lang,
            'name' => $name,
            'tariffValidUntil' => $tariffValidUntil,
            'tariffProperties' => $tariffProperties,
            'telegramConnected' => $user->isTelegramConnected(),
            'notificationGroups' => app(UserNotificationPreferenceService::class)->profileGroupsForUser($user),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Profile $profile
     * @return \Illuminate\Http\Response
     */
    public function show(Profile $profile)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Profile $profile
     * @return \Illuminate\Http\Response
     */
    public function edit(Profile $profile)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'last_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'min:3', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
        ]);

        SignupEmailPolicy::appendValidation($validator, $request->input('email'));

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $user = $this->user;
        $disk = Storage::disk('public');

        $user->name = $request->input('name');
        $user->last_name = $request->input('last_name');
        $user->lang = $request->input('lang');

        if ($user->email !== $request->input('email')) {
            $user->email = $request->input('email');
            $user->email_verified_at = null;
        }

        if ($request->boolean('remove_avatar') && $user->image) {
            if ($disk->exists($user->image)) {
                $disk->delete($user->image);
            }
            $user->image = null;
        } elseif ($request->hasFile('image')) {
            if ($user->image && $disk->exists($user->image)) {
                $disk->delete($user->image);
            }

            $user->image = $request->file('image')->store('avatar', 'public');
        }

        $user->save();

        flash()->overlay(__('User update successfully'), __('Update user'))->success();

        if ($user->email_verified_at) {
            return redirect()->route('profile.index');
        }

        return redirect()->route('verification.resend');
    }

    public function password(Request $request)
    {
        $this->validate($request, [
            'password' => 'required|confirmed|min:8',
        ]);

        $user = $this->user;

        $this->resetPassword($user, $request->input('password'));

        flash()->overlay(__('User password successfully changed'), __('Update password'))->success();

        $user->sendProfilePasswordResetNotification($request, $user);

        return redirect()->route('profile.index');
    }

    /**
     * Reset the given user's password.
     *
     * @param \Illuminate\Contracts\Auth\CanResetPassword $user
     * @param string $password
     * @return void
     */
    protected function resetPassword($user, $password)
    {
        $user->password = Hash::make($password);

        $user->setRememberToken(Str::random(60));

        $user->save();

        event(new PasswordReset($user));

        $this->guard()->login($user);
    }

    /**
     * Get the guard to be used during password reset.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Profile $profile
     * @return \Illuminate\Http\Response
     */
    public function destroy(Profile $profile)
    {
        //
    }

    public function testTelegramNotify()
    {
        try {
            TelegramBot::sendTestNotify();
        } catch (\ErrorException $exception) {
            return redirect()->back()->with('status', __('Subscribe to notifications in Telegram first.'));
        }

        return redirect()->back()->with('status', __('Test notification sent.'));
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateNotifications(Request $request)
    {
        $preferences = (array) $request->input('notifications', []);

        app(UserNotificationPreferenceService::class)->syncFromRequest($this->user, $preferences);

        return redirect()
            ->to(route('profile.index') . '#notifications')
            ->with('notification_status', __('Profile notify saved'));
    }

    /**
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function snoozeTelegramConnectPrompt(Request $request)
    {
        $until = Carbon::now()->addWeeks(2);

        $this->user->telegram_prompt_snoozed_until = $until;
        $this->user->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'snoozed_until' => $until->toIso8601String(),
            ]);
        }

        return redirect()->back();
    }

    /**
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function snoozeMonitoringSchedulePaidPrompt(Request $request)
    {
        $weeks = max(1, (int) config('cabinet-monitoring.schedule_prompt_snooze_weeks', 2));
        $until = Carbon::now()->addWeeks($weeks);

        $this->user->monitoring_schedule_prompt_snoozed_until = $until;
        $this->user->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'snoozed_until' => $until->toIso8601String(),
            ]);
        }

        return redirect()->back();
    }
}
