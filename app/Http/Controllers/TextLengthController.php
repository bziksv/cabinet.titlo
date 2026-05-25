<?php

namespace App\Http\Controllers;

use App\Services\TextLengthAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TextLengthController extends Controller
{
  public function __construct()
  {
    $this->middleware(['permission:Counting text length']);
  }

  public function index(): View
  {
    return view('pages.length');
  }

  public function countingTextLength(Request $request): JsonResponse
  {
    $text = (string) $request->input('text', '');
    $maxChars = (int) config('cabinet-text-length.max_chars', 38600);

    if (trim($text) === '') {
      return response()->json([
        'error' => 'validation',
        'message' => __('The text should not be empty'),
      ], 422);
    }

    if (mb_strlen($text) > $maxChars) {
      return response()->json([
        'error' => 'validation',
        'message' => sprintf(
          __('Maximum :count characters per check'),
          number_format($maxChars, 0, ',', ' ')
        ),
      ], 422);
    }

    $fields = [
      'title' => (string) $request->input('title', ''),
      'description' => (string) $request->input('description', ''),
      'h1' => (string) $request->input('h1', ''),
    ];

    $result = TextLengthAnalysisService::analyze($text, $fields);

    return response()->json([
      'data' => $result,
      // Legacy keys for старых интеграций
      'legacy' => [
        'length' => $result['summary']['chars_with_spaces'],
        'countSpaces' => $result['summary']['spaces'],
        'lengthWithOutSpaces' => $result['summary']['chars_no_spaces'],
        'countWords' => $result['summary']['words'],
      ],
    ]);
  }
}
