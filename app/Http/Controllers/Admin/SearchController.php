<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Search\GlobalSearch;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request, GlobalSearch $search): JsonResponse
    {
        $term = trim((string) $request->get('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $term = mb_substr($term, 0, 100);

        return response()->json([
            'results' => $search->search($term, $request->user()),
        ]);
    }
}
