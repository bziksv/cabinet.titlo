<?php

namespace App\ViewComposers;

use App\Classes\Monitoring\PositionLimit;
use App\ClusterLimit;
use App\DomainInformation;
use App\DomainMonitoring;
use App\MetaTag;
use App\ProjectTracking;
use App\RelevanceHistory;
use App\SearchCompetitors;
use App\TextAnalyzer;
use App\LinkTracking;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LimitsComposer
{
    public function compose(View $view)
    {
        // Local + удалённая БД: десятки запросов лимитов на каждую страницу → зависание serve.
        if (app()->environment('local') || env('SKIP_HEAVY_WEB_MIDDLEWARE', false)) {
            $view->with('limitsStatistics', []);

            return;
        }

        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            $tariff = $user->tariff();

            $tariffLimits = [];
            if (isset($tariff)) {
                $tariffLimits = $tariff->getAsArray()['settings'];
            }

            $limitsStatistics = [];
            foreach ($tariffLimits as $tariffKey => $tariffValue) {
                $info = LimitsComposer::getUsedLimit($tariffKey, $user);
                $limitsStatistics[$tariffKey]['used'] = $info['count'];
                $limitsStatistics[$tariffKey]['position'] = $info['position'];
                $limitsStatistics[$tariffKey]['name'] = $tariffValue['name'];
                $limitsStatistics[$tariffKey]['value'] = $tariffValue['value'];
            }

            $limitsStatistics = collect($limitsStatistics)->sortBy('position')->toArray();

            $view->with(compact('limitsStatistics'));
        }
    }

    /**
     * @param string $code
     * @param $user
     * @return array
     */
    public static function getUsedLimit(string $code, $user): array
    {
        switch ($code) {
            case 'RelevanceAnalysis':
                $now = Carbon::now();
                $month = strlen($now->month) < 2 ? '0' . $now->month : $now->month;
                return [
                    'count' => (int)RelevanceHistory::where('user_id', '=', $user->id)
                        ->where('last_check', 'like', '%' . $now->year . '-' . $month . '%')
                        ->count(),
                    'position' => 1
                ];

            case 'TextAnalyzer':
                $now = Carbon::now();
                return [
                    'count' => (int)TextAnalyzer::where('user_id', '=', $user->id)
                        ->where('month', '=', $now->year . '-' . $now->month)
                        ->sum('counter'),
                    'position' => 2
                ];

            case 'CompetitorAnalysisPhrases':
                $now = Carbon::now();
                return [
                    'count' => (int)SearchCompetitors::where('user_id', '=', $user->id)
                        ->where('month', '=', $now->year . '-' . $now->month)
                        ->sum('counter'),
                    'position' => 3
                ];

            case 'Clusters':
                $now = Carbon::now();
                $month = strlen($now->month) < 2 ? '0' . $now->month : $now->month;

                return [
                    'count' => ClusterLimit::where('user_id', '=', Auth::id())
                            ->where('date', '=', "$now->year-$month")
                            ->first('count')->count ?? 0,
                    'position' => 5
                ];

            case 'domainMonitoringProject':
                return [
                    'count' => (int)DomainMonitoring::where('user_id', '=', $user->id)->count(),
                    'position' => 6
                ];

            case 'monitoring':
                return [
                    'count' => (new PositionLimit($user))->getCounter(),
                    'position' => 7,
                ];

            case 'DomainInformation':
                return [
                    'count' => (int)DomainInformation::where('user_id', '=', $user->id)->count(),
                    'position' => 8
                ];

            case 'MetaTagsProject':
                return [
                    'count' => MetaTag::where('user_id', '=', Auth::id())->count(),
                    'position' => 9,
                ];

            case 'MetaTagsPages':
                return [
                    'count' => (int) DB::table('meta_tags_histories')
                        ->whereIn('meta_tag_id', function ($query) use ($user) {
                            $query->select('id')
                                ->from('meta_tags')
                                ->where('user_id', $user->id);
                        })
                        ->count(),
                    'position' => 10,
                ];

            case 'PasswordGenerator':
                return [
                    'count' => __('Restrictions are not tracked'),
                    'position' => 10
                ];

            case 'TextLength':
                return [
                    'count' => __('Restrictions are not tracked'),
                    'position' => 11
                ];

            case 'ListComparison':
                return [
                    'count' => __('Restrictions are not tracked'),
                    'position' => 12
                ];

            case 'UniqueWords':
                return [
                    'count' => __('Restrictions are not tracked'),
                    'position' => 13
                ];

            case 'HtmlEditor':
                return [
                    'count' => __('Restrictions are not tracked'),
                    'position' => 14
                ];

            case 'RemoveDublicate':
                return [
                    'count' => __('Restrictions are not tracked'),
                    'position' => 15
                ];

            case 'UTM':
                return [
                    'count' => __('Restrictions are not tracked'),
                    'position' => 16
                ];

            case 'ROI':
                return [
                    'count' => __('Restrictions are not tracked'),
                    'position' => 17
                ];

            case 'BacklinkProject':
                return [
                    'count' => ProjectTracking::where('user_id', '=', $user->id)->count(),
                    'position' => 18
                ];

            case 'BacklinkLinks':
                return [
                    'count' => (int) LinkTracking::query()
                        ->whereIn('project_tracking_id', function ($query) use ($user) {
                            $query->select('id')
                                ->from('project_tracking')
                                ->where('user_id', $user->id);
                        })
                        ->count(),
                    'position' => 19,
                ];

            case 'HttpHeaders':
                return [
                    'count' => __('Restrictions are not tracked'),
                    'position' => 21
                ];

            case 'IndexCheck':
                return [
                    'count' => \App\Support\IndexCheckLimits::usedForUser($user),
                    'position' => 4,
                ];

            case 'GeneratorWords':
                return [
                    'count' => __('Restrictions are not tracked'),
                    'position' => 22
                ];

            default:
                return [
                    'count' => 100000,
                    'position' => 100
                ];
        }
    }

    /**
     * @param $code
     * @return int
     */
    public static function getPosition($code): int
    {
        switch ($code) {

            case 'RelevanceAnalysis':
                return 1;

            case 'TextAnalyzer':
                return 2;

            case 'CompetitorAnalysisPhrases':
                return 3;

            case 'Clusters':
                return 5;

            case 'domainMonitoringProject':
                return 6;

            case 'monitoring':
                return 7;

            case 'DomainInformation':
                return 8;

            case 'MetaTagsProject':
                return 9;

            case 'MetaTagsPages':
                return 10;

            case 'BacklinkProject':
                return 11;

            case 'BacklinkLinks':
                return 12;

            case 'ListComparison':
                return 14;

            case 'IndexCheck':
                return 4;

            case 'HttpHeaders':
                return 15;

            case 'TextLength':
                return 16;

            case 'RemoveDublicate':
                return 17;

            case 'UTM':
                return 18;

            case 'PasswordGenerator':
                return 19;

            case 'HtmlEditor':
                return 20;

            case 'ROI':
                return 21;

            case 'GeneratorWords':
                return 22;

            case 'UniqueWords':
                return 23;

            default:
                return 100;
        }
    }

}
