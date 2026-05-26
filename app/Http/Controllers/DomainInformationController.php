<?php

namespace App\Http\Controllers;

use App\DomainInformation;
use App\DomainInformationPublicShare;
use App\Services\DomainInformationPdfService;
use App\Support\DomainInformationListSummary;
use App\Support\DomainInformationProjectStats;
use App\Support\DomainInformationPublicShareTtl;
use App\TariffSetting;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DomainInformationController extends Controller
{
    public $counter;

    public function __construct()
    {
        $this->middleware(['permission:Domain information']);
    }

    public function index()
    {
        $user = Auth::user();
        $projects = DomainInformation::where('user_id', '=', $user->id)->orderBy('domain')->get();
        $countProjects = $projects->count();

        if ($countProjects === 0) {
            return $this->createView();
        }

        $onFreeTariff = $user->onFreeTariff();
        $domainInformationEmailAvailable = $user->canReceiveDomainInformationEmail();

        $listSummary = DomainInformationListSummary::fromProjects($projects);

        return view('domain-information.index', compact(
            'projects',
            'countProjects',
            'listSummary',
            'onFreeTariff',
            'domainInformationEmailAvailable'
        ));
    }

    public function createView(): View
    {
        $user = Auth::user();

        return view('domain-information.create', [
            'onFreeTariff' => $user->onFreeTariff(),
            'domainInformationEmailAvailable' => $user->canReceiveDomainInformationEmail(),
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $user = User::find(Auth::id());

        if (TariffSetting::checkDomainInformationLimits($user)) {
            flash()->overlay(__('Your limits are exhausted the number of monitored domains is exhausted'), ' ')->error();

            return redirect()->route('domain.information');
        }

        if (isset($request->domains)) {
            DomainInformationController::multipleCreation($request->domains, $user);
        } else {
            $domain = DomainInformation::getDomain($request->domain);

            if (DomainInformation::isValidDomain($domain)) {
                $monitoring = new DomainInformation($request->all());
                $monitoring->domain = $domain;
                $monitoring->user_id = $user->id;
                $monitoring->check_dns_email = 0;
                $monitoring->check_registration_date_email = 0;
                $monitoring->save();
                flash()->overlay(__('Domain added successfully'), ' ')->success();
            } else {
                flash()->overlay(__('There is no such domain'), ' ')->error();

                return Redirect::back();
            }
        }

        return Redirect::route('domain.information');
    }

    /**
     * @param $domains
     * @param $user
     * @return void
     */
    public static function multipleCreation($domains, $user)
    {
        $newRecord = [];
        $domains = explode("\r\n", $domains);
        $domains = array_diff($domains, array(''));

        foreach ($domains as $item) {
            $obj = explode(':', $item);
            $domain = $obj[0];
            $counter = count($obj);
            $checkRegistrationDate = explode('/', $obj[$counter - 1]);

            if (DomainInformation::isValidDomain($domain)) {
                $newRecord[] = [
                    'user_id' => $user->id,
                    'domain' => $domain,
                    'check_dns' => (boolean) $obj[1] ?? false,
                    'check_registration_date' => (boolean) $checkRegistrationDate[0] ?? false,
                    'check_dns_email' => 0,
                    'check_registration_date_email' => 0,
                ];
            }
        }

        if (count($newRecord) >= 1) {
            DomainInformation::insert($newRecord);
        }
    }

    /**
     * @param $id
     * @return RedirectResponse
     */
    public function remove($id): RedirectResponse
    {
        DomainInformation::query()
            ->where('user_id', Auth::id())
            ->where('id', $id)
            ->delete();
        flash()->overlay(__('Domain successfully deleted'), ' ')->success();

        return Redirect::route('domain.information');
    }

    /**
     * @param $id
     * @return RedirectResponse
     */
    public function checkDomain($id): RedirectResponse
    {
        $project = DomainInformation::query()
            ->where('user_id', Auth::id())
            ->findOrFail($id);
        DomainInformation::checkDomain($project, 'manual');

        return Redirect::back();
    }

    public function projectStats(Request $request): JsonResponse
    {
        $project = $this->findOwnedProject((int) $request->input('projectId'));
        $page = max(1, (int) $request->input('page', 1));

        $payload = DomainInformationProjectStats::build($project, $page);
        $payload['share'] = $this->shareStateForProject($project);

        return response()->json($payload);
    }

    public function createPublicShare(Request $request): JsonResponse
    {
        $project = $this->findOwnedProject((int) $request->input('projectId'));

        if (!DomainInformationPublicShare::tableAvailable()) {
            return response()->json([
                'success' => false,
                'message' => __('Domain information public share unavailable'),
                'code' => 503,
            ], 503);
        }

        $ttlDays = DomainInformationPublicShareTtl::normalize($request->input('ttl_days', 30));
        $report = DomainInformationProjectStats::buildForExport($project);
        $meta = $this->buildReportMeta($project);
        $share = DomainInformationPublicShare::issueForProject(
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

        if (!DomainInformationPublicShare::tableAvailable()) {
            return response()->json([
                'success' => false,
                'message' => __('Public sharing is temporarily unavailable.'),
                'code' => 503,
            ], 503);
        }

        DomainInformationPublicShare::revokeForProject((int) Auth::id(), $project->id);

        return response()->json([
            'success' => true,
            'message' => __('Public link revoked'),
        ]);
    }

    public function exportPdf(Request $request)
    {
        $project = $this->findOwnedProject((int) $request->input('projectId'));
        $report = DomainInformationProjectStats::buildForExport($project);
        $meta = $this->buildReportMeta($project);
        $slug = Str::slug($project->domain ?: 'domain');
        $fileName = 'domain-information-' . ($slug !== '' ? $slug : 'domain') . '-' . date('Y-m-d-His') . '.pdf';

        return app(DomainInformationPdfService::class)->downloadResponse($report, $meta, $fileName);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function edit(Request $request): JsonResponse
    {
        $emailFields = ['check_dns_email', 'check_registration_date_email'];
        if (in_array($request->name, $emailFields, true)) {
            $user = Auth::user();
            if (!$user->canReceiveDomainInformationEmail()) {
                return response()->json([], 403);
            }
        }

        if ($request->name === 'domain' && strlen($request->option) > 0) {
            $domain = DomainInformation::getDomain($request->option);
            if (DomainInformation::isValidDomain($domain)) {
                DomainInformation::query()
                    ->where('id', $request->id)
                    ->where('user_id', Auth::id())
                    ->update(['domain' => $domain]);

                return response()->json([
                    'message' => $domain,
                ]);
            }
        } elseif (strlen($request->option) > 0 || in_array($request->name, $emailFields, true)) {
            $value = $request->option;
            if (in_array($request->name, $emailFields, true)) {
                $value = (int) $request->option;
            }

            DomainInformation::query()
                ->where('id', $request->id)
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
        $ids = array_filter(array_map('intval', explode(',', (string) $request->ids)));
        if ($ids === []) {
            return response()->json([], 400);
        }

        $deleted = DomainInformation::query()
            ->where('user_id', Auth::id())
            ->whereIn('id', $ids)
            ->delete();

        if ($deleted > 0) {
            return response()->json([]);
        }

        return response()->json([], 400);
    }

    protected function findOwnedProject(int $id): DomainInformation
    {
        return DomainInformation::query()
            ->where('user_id', Auth::id())
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildReportMeta(DomainInformation $project): array
    {
        return [
            'generated_at' => now()->format('d.m.Y H:i'),
            'source_label' => $project->domain,
            'version' => config('cabinet-domain-information.version'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function shareStateForProject(DomainInformation $project): array
    {
        if (!DomainInformationPublicShare::tableAvailable()) {
            return [
                'available' => false,
                'url' => null,
                'expires_at' => null,
                'expires_label' => null,
                'ttl_days' => 30,
                'ttl_options' => [],
            ];
        }

        $share = DomainInformationPublicShare::activeForProject($project->id, (int) Auth::id());

        return [
            'available' => true,
            'url' => $share ? $share->publicUrl() : null,
            'expires_at' => $share && $share->expires_at ? $share->expires_at->format('d.m.Y H:i') : null,
            'expires_label' => $share ? $share->expiresLabel() : null,
            'ttl_days' => $share ? $share->ttlDaysFromPayload() : 30,
            'ttl_options' => DomainInformationPublicShareTtl::labelsForUi(),
        ];
    }
}
