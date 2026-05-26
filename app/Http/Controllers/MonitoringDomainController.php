<?php

namespace App\Http\Controllers;

use App\Classes\Tariffs\Facades\Tariffs;
use App\DomainMonitoring;
use App\Services\SiteMonitoringPdfService;
use App\SiteMonitoringConfig;
use App\SiteMonitoringPublicShare;
use App\Support\SiteMonitoringAdminStats;
use App\Support\SiteMonitoringProjectStats;
use App\Support\SiteMonitoringPublicShareTtl;
use App\Support\SiteMonitoringTiming;
use Illuminate\Support\Str;
use App\TariffSetting;
use App\User;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class MonitoringDomainController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:Domain monitoring']);
    }

    /**
     * @return array|false|Application|Factory|View|mixed
     */
    public function index()
    {
        $projects = DomainMonitoring::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('id')
            ->get();
        $countProjects = $projects->count();
        $admin = User::isUserAdmin();
        $user = Auth::user();
        $onFreeTariff = $user->onFreeTariff();
        $siteMonitoringEmailAvailable = $user->canReceiveSiteMonitoringEmail();
        $timingOptions = SiteMonitoringTiming::selectOptionsForUser($user, true);

        if ($onFreeTariff) {
            SiteMonitoringTiming::enforceForUser($user);
            $projects = DomainMonitoring::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->get();
        }

        return view('site-monitoring.index', compact(
            'projects',
            'countProjects',
            'admin',
            'onFreeTariff',
            'siteMonitoringEmailAvailable',
            'timingOptions'
        ));
    }

    /**
     * @return array|false|Application|Factory|View|mixed
     */
    public function createView()
    {
        $defaultNotify = SiteMonitoringConfig::defaultSendNotification();
        $admin = User::isUserAdmin();
        $user = Auth::user();
        $onFreeTariff = $user->onFreeTariff();
        $siteMonitoringEmailAvailable = $user->canReceiveSiteMonitoringEmail();
        $timingOptions = SiteMonitoringTiming::selectOptionsForUser($user, false);
        $defaultTiming = SiteMonitoringTiming::defaultForUser($user);

        return view('site-monitoring.create', compact(
            'defaultNotify',
            'admin',
            'onFreeTariff',
            'siteMonitoringEmailAvailable',
            'timingOptions',
            'defaultTiming'
        ));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        if (TariffSetting::checkDomainMonitoringLimits()) {
            flash()->overlay(__('Your limits are exhausted this month'), ' ')->error();

            return redirect()->route('site.monitoring');
        }

        $user = Auth::user();
        $data = $request->all();
        $data['timing'] = SiteMonitoringTiming::resolveForUser($request->input('timing'), $user);

        $monitoring = new DomainMonitoring($data);
        $monitoring->user_id = $user->id;
        $monitoring->send_notification = (int) $request->input('send_notification', SiteMonitoringConfig::defaultSendNotification() ? 1 : 0);
        $monitoring->save();

        $user = Auth::user();
        if ($user && !$user->receivesSiteMonitoringExternalAlerts((bool) $monitoring->send_notification)) {
            flash()->overlay(__('Site monitoring cabinet only flash'), ' ')->info();
        } else {
            flash()->overlay(__('Monitoring was successfully created'), ' ')->success();
        }

        return Redirect::route('site.monitoring');
    }

    /**
     * @param $id
     * @return RedirectResponse
     */
    public function remove($id): RedirectResponse
    {
        DomainMonitoring::destroy($id);
        flash()->overlay(__('Monitoring was successfully deleted'), ' ')->success();

        return Redirect::route('site.monitoring');
    }

    public function checkLink(Request $request): JsonResponse
    {
        $project = $this->findOwnedProject((int) $request->input('projectId'));
        DomainMonitoring::httpCheck($project, 'manual');

        return response()->json($this->projectStatusPayload($project));
    }

    public function resetStats(Request $request): JsonResponse
    {
        $project = $this->findOwnedProject((int) $request->input('projectId'));
        $project->resetStatistics();

        return response()->json(array_merge(
            $this->projectStatusPayload($project),
            ['message' => __('Site monitoring stats reset success')]
        ));
    }

    public function resetAllStats(): JsonResponse
    {
        $userId = (int) Auth::id();
        $count = DomainMonitoring::query()->where('user_id', $userId)->count();

        if ($count < 1) {
            return response()->json([
                'message' => __('Site monitoring reset all stats empty'),
            ], 400);
        }

        DomainMonitoring::resetStatisticsForUser($userId);

        $rows = DomainMonitoring::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->map(function (DomainMonitoring $project) {
                return array_merge(
                    ['id' => $project->id],
                    $this->projectStatusPayload($project)
                );
            })
            ->values();

        return response()->json([
            'message' => __('Site monitoring reset all stats success', ['count' => $count]),
            'projects' => $rows,
        ]);
    }

    public function projectStats(Request $request): JsonResponse
    {
        $project = $this->findOwnedProject((int) $request->input('projectId'));
        $page = max(1, (int) $request->input('page', 1));

        $payload = SiteMonitoringProjectStats::build($project, $page);
        $payload['share'] = $this->shareStateForProject($project);

        return response()->json($payload);
    }

    public function createPublicShare(Request $request): JsonResponse
    {
        $project = $this->findOwnedProject((int) $request->input('projectId'));

        if (!SiteMonitoringPublicShare::tableAvailable()) {
            return response()->json([
                'success' => false,
                'message' => __('Public sharing is temporarily unavailable. Run database migration site_monitoring_public_shares.'),
                'code' => 503,
            ], 503);
        }

        $ttlDays = SiteMonitoringPublicShareTtl::normalize($request->input('ttl_days', 30));
        $report = SiteMonitoringProjectStats::buildForExport($project);
        $meta = $this->buildReportMeta($project);
        $share = SiteMonitoringPublicShare::issueForProject(
            (int) Auth::id(),
            $project->id,
            $report,
            $meta,
            $ttlDays
        );

        if ($share === null) {
            return response()->json([
                'success' => false,
                'message' => __('Public link could not be created.'),
                'code' => 500,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('Public link created'),
            'url' => $share->publicUrl(),
            'ttl_days' => $ttlDays,
            'expires_at' => $share->expires_at ? $share->expires_at->format('d.m.Y H:i') : null,
            'expires_label' => $share->expiresLabel(),
        ]);
    }

    public function revokePublicShare(Request $request): JsonResponse
    {
        $project = $this->findOwnedProject((int) $request->input('projectId'));

        if (!SiteMonitoringPublicShare::tableAvailable()) {
            return response()->json([
                'success' => false,
                'message' => __('Public sharing is temporarily unavailable.'),
                'code' => 503,
            ], 503);
        }

        SiteMonitoringPublicShare::revokeForProject((int) Auth::id(), $project->id);

        return response()->json([
            'success' => true,
            'message' => __('Public link revoked'),
        ]);
    }

    public function exportPdf(Request $request)
    {
        $project = $this->findOwnedProject((int) $request->input('projectId'));
        $report = SiteMonitoringProjectStats::buildForExport($project);
        $meta = $this->buildReportMeta($project);
        $slug = Str::slug($project->project_name ?: 'project');
        $fileName = 'site-monitoring-' . ($slug !== '' ? $slug : 'project') . '-' . date('Y-m-d-His') . '.pdf';

        return app(SiteMonitoringPdfService::class)->downloadResponse($report, $meta, $fileName);
    }

    protected function findOwnedProject(int $id): DomainMonitoring
    {
        return DomainMonitoring::query()
            ->where('user_id', Auth::id())
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildReportMeta(DomainMonitoring $project): array
    {
        return [
            'generated_at' => now()->format('d.m.Y H:i'),
            'source_label' => $project->project_name . ' · ' . $project->link,
            'version' => config('cabinet-site-monitoring.version'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function shareStateForProject(DomainMonitoring $project): array
    {
        if (!SiteMonitoringPublicShare::tableAvailable()) {
            return [
                'available' => false,
                'url' => null,
                'expires_at' => null,
                'expires_label' => null,
                'ttl_days' => 30,
                'ttl_options' => [],
            ];
        }

        $share = SiteMonitoringPublicShare::activeForProject($project->id, (int) Auth::id());

        return [
            'available' => true,
            'url' => $share ? $share->publicUrl() : null,
            'expires_at' => $share && $share->expires_at ? $share->expires_at->format('d.m.Y H:i') : null,
            'expires_label' => $share ? $share->expiresLabel() : null,
            'ttl_days' => $share ? $share->ttlDaysFromPayload() : 30,
            'ttl_options' => SiteMonitoringPublicShareTtl::labelsForUi(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function projectStatusPayload(DomainMonitoring $project): array
    {
        $pending = $project->isPendingResetStatus();

        return [
            'status' => __($project->status),
            'code' => $project->code !== null ? (int) $project->code : null,
            'uptime' => $project->uptime_percent !== null ? round((float) $project->uptime_percent, 2) : null,
            'broken' => (bool) $project->broken,
            'pending' => $pending,
        ];
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function edit(Request $request): JsonResponse
    {
        if (strlen($request->option) > 0 || $request->name === 'phrase') {
            $value = $request->option;
            if ($request->name === 'timing') {
                $project = DomainMonitoring::query()
                    ->where('user_id', Auth::id())
                    ->findOrFail($request->id);
                $value = SiteMonitoringTiming::resolveForUser($value, Auth::user());
            }

            DomainMonitoring::where('id', $request->id)
                ->where('user_id', Auth::id())
                ->update([
                    $request->name => $value,
                ]);

            return response()->json([]);
        }
        return response()->json([], 400);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function removeDomains(Request $request): JsonResponse
    {
        if (DomainMonitoring::destroy(explode(',', $request->ids))) {
            return response()->json([]);
        }
        return response()->json([], 400);
    }

    public function config()
    {
        if (!User::isUserAdmin()) {
            abort(403);
        }

        $config = SiteMonitoringConfig::instance();
        $registry = SiteMonitoringAdminStats::snapshot();

        return view('site-monitoring.config', [
            'admin' => true,
            'config' => $config,
            'stats' => $registry['summary'],
            'registry' => $registry,
        ]);
    }

    public function editConfig(Request $request): RedirectResponse
    {
        if (!User::isUserAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'repeat_broken_notification_minutes' => 'required|integer|min:60|max:10080',
            'default_send_notification' => 'nullable|boolean',
            'email_notifications_enabled' => 'nullable|boolean',
            'telegram_notifications_enabled' => 'nullable|boolean',
        ]);

        $config = SiteMonitoringConfig::instance();
        $config->update([
            'repeat_broken_notification_minutes' => (int) $validated['repeat_broken_notification_minutes'],
            'default_send_notification' => $request->has('default_send_notification'),
            'email_notifications_enabled' => $request->has('email_notifications_enabled'),
            'telegram_notifications_enabled' => $request->has('telegram_notifications_enabled'),
        ]);

        flash()->overlay(__('Settings updated'), ' ')->success();

        return Redirect::route('site.monitoring.config');
    }
}
