<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class IssueCategoryController extends Controller
{
    public function index()
    {
        $categories = DB::table('issue_categories')
            ->orderBy('category_id')
            ->get(['category_id', 'category_name', 'description']);

        return response()->json([
            'success' => true,
            'message' => 'Issue categories retrieved successfully.',
            'data' => $categories,
        ]);
    }
}