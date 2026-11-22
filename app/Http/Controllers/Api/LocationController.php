<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\TrinidadLocationCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function barangays()
    {
        return response()->json([
            'success' => true,
            'data' => TrinidadLocationCatalog::barangays(),
        ]);
    }

    public function puroks(Request $request)
    {
        $validated = $request->validate([
            'barangay' => [
                'required',
                'string',
                Rule::in(TrinidadLocationCatalog::barangays()),
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => TrinidadLocationCatalog::puroks($validated['barangay']),
        ]);
    }
}
