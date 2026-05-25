<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProjectDescriptionRequest;
use App\Http\Requests\EditProjectDescriptionRequest;
use App\Http\Requests\CreateProjectRequest;
use App\Http\Requests\EditProjectRequest;
use App\HtmlEditorPublicShare;
use App\HtmlEditorPreset;
use App\Project;
use App\ProjectDescription;
use App\Services\HtmlEditorPresetService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use JavaScript;

class TextEditorController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:Html editor']);
    }

    /**
     * @return array|false|Application|Factory|RedirectResponse|View|mixed
     */
    public function index()
    {
        $projects = Project::where('user_id', Auth::id())
            ->with('descriptions')
            ->orderByDesc('id')
            ->get();
        $projectCount = $projects->count();
        $textCount = $projects->sum(static function (Project $project) {
            return $project->descriptions->count();
        });
        if ($projectCount === 0) {
            return self::createView(false);
        }

        return view('html-editor.projects', compact('projects', 'projectCount', 'textCount'));
    }

    /**
     * @param boolean $showButton
     * @return array|false|Application|Factory|RedirectResponse|View|mixed
     */
    public function createView(bool $showButton = true)
    {
        $user = Auth::user();
        if (self::isCountProjectsMoreTwenty($user->id)) {
            flash()->overlay(__('You have created the maximum number of projects(20), you need to delete something'), ' ')
                ->error();
            return Redirect::route('HTML.editor');
        }

        $lang = Auth::user()->lang;

        return view('html-editor.create-project', array_merge(compact('showButton', 'lang'), $this->editorFormExtras()));
    }

    /**
     * @param int $id
     * @return array|false|Application|Factory|View|mixed
     */
    public function editProjectView(int $id)
    {
        $project = Project::query()
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return view('html-editor.edit-project', compact('project'));
    }

    /**
     * @param EditProjectRequest $request
     * @return RedirectResponse
     */
    protected function editProject(EditProjectRequest $request): RedirectResponse
    {
        $project = Project::query()
            ->where('user_id', Auth::id())
            ->findOrFail($request->project_id);
        $project->update([
            'project_name' => $request->project_name,
            'short_description' => $request->short_description
        ]);
        flash()->overlay(__('Project was successfully changed'), ' ')
            ->success();

        return Redirect::route('HTML.editor');
    }

    /**
     * @param CreateProjectRequest $request
     * @return array|false|Application|Factory|RedirectResponse|View|mixed
     */
    public function storeProject(CreateProjectRequest $request)
    {
        if (self::isDescriptionEmpty($request->description)) {
            flash()->overlay(__('The text cannot be empty'), ' ')->error();

            return Redirect::back();
        }
        $showButton = true;
        ProjectDescription::storeDescriptionProject($request->description, Project::createNewProject($request));

        flash()->overlay(__('Project was successfully created'), $request->project_name)->success();

        return view('html-editor.create-project', array_merge(compact('showButton', 'request'), $this->editorFormExtras()));
    }


    /**
     * @param string $id
     * @return RedirectResponse
     */
    public function destroyProject(string $id): RedirectResponse
    {
        Project::destroy($id);

        flash()->overlay(__('Project was successfully deleted'), ' ')
            ->success();

        return Redirect::back();
    }

    /**
     * @param string $id
     * @return array|Application|Factory|View|mixed
     */
    public function editDescriptionView(string $id)
    {
        $lang = Auth::user()->lang;
        $project = ProjectDescription::query()
            ->join('projects', 'projects.id', '=', 'project_description.project_id')
            ->where('projects.user_id', Auth::id())
            ->where('project_description.id', $id)
            ->select('project_description.*', 'projects.project_name')
            ->firstOrFail();

        $publicShare = HtmlEditorPublicShare::activeForDescription((int) $project->id, (int) Auth::id());

        return view('html-editor.edit-description', array_merge(compact('project', 'lang', 'publicShare'), $this->editorFormExtras()));
    }

    /**
     * @param EditProjectDescriptionRequest $request
     * @return array|Application|Factory|RedirectResponse|View|mixed
     */
    public function editDescription(EditProjectDescriptionRequest $request)
    {
        if (self::isDescriptionEmpty($request->description)) {
            flash()->overlay(__('The text cannot be empty'), ' ')
                ->error();

            return $this->editDescriptionView($request->description_id);
        }

        $description = ProjectDescription::query()
            ->join('projects', 'projects.id', '=', 'project_description.project_id')
            ->where('projects.user_id', Auth::id())
            ->where('project_description.id', $request->description_id)
            ->select('project_description.*')
            ->firstOrFail();
        $description->description = $request->description;
        $description->save();
        flash()->overlay(__('Text was successfully change'), ' ')
            ->success();

        return Redirect::route('HTML.editor');
    }

    /**
     * @param string $id
     * @return RedirectResponse
     */
    public function destroyDescription(string $id): RedirectResponse
    {
        ProjectDescription::destroy($id);
        flash()->overlay(__('Text was successfully deleted'), ' ')
            ->success();

        return Redirect::back();
    }

    /**
     * @return array|Application|Factory|View|mixed
     */
    public function createDescriptionView()
    {
        $user = Auth::user();
        $lang = $user->lang;

        if (self::isCountDescriptionProjectsMoreThirty($user->id)) {
            flash()->overlay(__('You have reached the maximum number of texts per project, you need to delete something'), ' ')
                ->error();

            return Redirect::route('HTML.editor');
        }

        $projects = Project::query()
            ->where('user_id', $user->id)
            ->orderBy('project_name')
            ->get(['id', 'project_name']);

        $preselectedProjectId = (int) request()->query('project_id', 0);

        return view('html-editor.create-description', array_merge(compact('lang', 'projects', 'preselectedProjectId'), $this->editorFormExtras()));
    }

    /**
     * @param CreateProjectDescriptionRequest $request
     * @return RedirectResponse
     */
    public function createDescription(CreateProjectDescriptionRequest $request): RedirectResponse
    {
        if (self::isDescriptionEmpty($request->description)) {
            flash()->overlay(__('The text cannot be empty'), ' ')->error();

            return Redirect::back();
        }

        $project = Project::query()
            ->where('user_id', Auth::id())
            ->findOrFail($request->project_id);
        self::saveDescription($request->description, $project->id);
        flash()->overlay(__('Text was saved successfully'), ' ')
            ->success();

        return Redirect::back();
    }

    /**
     * @param $userId
     * @return bool
     */
    public static function isCountProjectsMoreTwenty($userId): bool
    {
        if (Project::where('user_id', $userId)->count() >= 20) {
            return true;
        }
        return false;
    }

    /**
     * @param $user_id
     * @return bool
     */
    public static function isCountDescriptionProjectsMoreThirty($user_id): bool
    {
        $descriptions = Project::where('user_id', $user_id)->withCount('descriptions')->get();
        foreach ($descriptions as $description) {
            if ($description->descriptions_count >= 30) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $description
     * @param $id
     */
    public static function saveDescription($description, $id)
    {
        $projectDescription = new ProjectDescription([
            'description' => $description,
            'project_id' => $id,
        ]);

        $projectDescription->save();
    }

    /**
     * @param $description
     * @return bool
     */
    public static function isDescriptionEmpty($description): bool
    {
        if (strip_tags($description) === "") {
            return true;
        }
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function editorFormExtras(): array
    {
        return [
            'presetsPayload' => HtmlEditorPresetService::payloadForUser((int) Auth::id()),
        ];
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storePreset(Request $request)
    {
        if (!Schema::hasTable('html_editor_presets')) {
            return response()->json(['message' => __('Presets storage is not ready yet. Run database migration.')], 503);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'html' => 'required|string|max:500000',
        ]);

        if (strip_tags($validated['html']) === '') {
            return response()->json(['message' => __('The text cannot be empty')], 422);
        }

        $userId = (int) Auth::id();
        $count = HtmlEditorPreset::query()->where('user_id', $userId)->count();
        if ($count >= HtmlEditorPresetService::maxUserPresets()) {
            return response()->json(['message' => __('You have reached the maximum number of presets')], 422);
        }

        $preset = HtmlEditorPreset::query()->create([
            'user_id' => $userId,
            'name' => $validated['name'],
            'html' => $validated['html'],
        ]);

        return response()->json([
            'preset' => [
                'id' => 'user:' . $preset->id,
                'name' => $preset->name,
                'html' => $preset->html,
                'builtin' => false,
            ],
        ]);
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyPreset(int $id)
    {
        if (!Schema::hasTable('html_editor_presets')) {
            return response()->json(['message' => __('Presets storage is not ready yet. Run database migration.')], 503);
        }

        HtmlEditorPreset::query()
            ->where('user_id', Auth::id())
            ->where('id', $id)
            ->delete();

        return response()->json(['ok' => true]);
    }

  /**
     * @param Request $request
     * @return JsonResponse
     */
    public function createPublicShare(Request $request): JsonResponse
    {
        if (!HtmlEditorPublicShare::tableAvailable()) {
            return response()->json([
                'success' => false,
                'message' => __('Public sharing is temporarily unavailable. Run database migration html_editor_public_shares.'),
                'code' => 503,
            ]);
        }

        $validated = $request->validate([
            'description_id' => 'required|integer',
            'html' => 'nullable|string|max:500000',
        ]);

        $description = ProjectDescription::query()
            ->join('projects', 'projects.id', '=', 'project_description.project_id')
            ->where('projects.user_id', Auth::id())
            ->where('project_description.id', $validated['description_id'])
            ->select('project_description.*', 'projects.project_name')
            ->firstOrFail();

        $html = $validated['html'] ?? $description->description;
        if (self::isDescriptionEmpty($html)) {
            return response()->json([
                'success' => false,
                'message' => __('The text cannot be empty'),
                'code' => 422,
            ]);
        }

        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($html)));
        $meta = [
            'project_name' => $description->project_name,
            'text_excerpt' => $plain !== '' ? Str::limit($plain, 120) : __('Empty text'),
            'version' => config('cabinet-html-editor.version'),
        ];

        $share = HtmlEditorPublicShare::issueForDescription(
            (int) Auth::id(),
            (int) $description->id,
            $html,
            $meta
        );

        if ($share === null) {
            return response()->json([
                'success' => false,
                'message' => __('Public link could not be created.'),
                'code' => 500,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('Public link created'),
            'code' => 201,
            'url' => $share->publicUrl(),
            'expires_at' => $share->expires_at->format('d.m.Y H:i'),
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function revokePublicShare(Request $request): JsonResponse
    {
        if (!HtmlEditorPublicShare::tableAvailable()) {
            return response()->json([
                'success' => false,
                'message' => __('Public sharing is temporarily unavailable.'),
                'code' => 503,
            ]);
        }

        $validated = $request->validate([
            'description_id' => 'required|integer',
        ]);

        ProjectDescription::query()
            ->join('projects', 'projects.id', '=', 'project_description.project_id')
            ->where('projects.user_id', Auth::id())
            ->where('project_description.id', $validated['description_id'])
            ->select('project_description.id')
            ->firstOrFail();

        HtmlEditorPublicShare::revokeForDescription((int) Auth::id(), (int) $validated['description_id']);

        return response()->json([
            'success' => true,
            'message' => __('Public link revoked'),
            'code' => 201,
        ]);
    }
}
