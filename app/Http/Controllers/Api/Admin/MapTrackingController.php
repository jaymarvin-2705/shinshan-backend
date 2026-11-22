<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RepairRequest;
use App\Models\TechnicianProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MapTrackingController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => [
                'nullable',
                Rule::in([
                    'pending',
                    'assigned',
                    'on_the_way',
                    'in_progress',
                    'completed',
                    'cancelled',
                ]),
            ],
        ]);

        $requests = RepairRequest::query()
            ->with([
                'client.user',
                'category',
                'assignment.technician.user',
            ])
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->latest('request_id')
            ->get();

        $technicians = TechnicianProfile::query()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->orderBy('technician_id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Map locations retrieved successfully.',
            'data' => [
                'requests' => $requests,
                'technicians' => $technicians,
                'last_updated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
