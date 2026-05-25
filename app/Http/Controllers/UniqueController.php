<?php

namespace App\Http\Controllers;

use App\Services\UniqueWordsAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UniqueController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:Unique words']);
    }

    public function index(): View
    {
        return view('pages.unique');
    }

    public function dataTableView(Request $request): JsonResponse
    {
        $content = (string) $request->input('content', '');

        return response()->json(UniqueWordsAnalysisService::analyze($content));
    }
}
