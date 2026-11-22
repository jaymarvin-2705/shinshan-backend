<?php

namespace App\Http\Controllers\Api;

use App\DTOs\TechnicianData;
use App\Http\Controllers\Controller;
use App\Services\TechnicianService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TechnicianController extends Controller
{
    public function __construct(
        protected TechnicianService $technicianService
    ) {}

    public function index(Request $request)
    {
        $search = $request->query('search');

        $perPage = min(
            (int) $request->query('per_page', 10),
            100
        );

        $technicians = $this->technicianService->getAll(
            $search,
            $perPage
        );

        return response()->json([
            'success' => true,
            'message' => 'Technicians retrieved successfully.',
            'data' => $technicians,
        ]);
    }

    public function show(int $technicianId)
    {
        $technician = $this->technicianService->getById(
            $technicianId
        );

        return response()->json([
            'success' => true,
            'message' => 'Technician retrieved successfully.',
            'data' => $technician,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fullname' => [
                'required',
                'string',
                'max:100',
            ],

            'email' => [
                'required',
                'email',
                'max:100',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],

            'service_area' => [
                'nullable',
                'string',
                'max:100',
            ],

            'availability' => [
                'nullable',
                Rule::in([
                    'available',
                    'busy',
                    'offline',
                ]),
            ],
        ]);

        $technician = $this->technicianService->create(
            TechnicianData::fromArray($validated)
        );

        return response()->json([
            'success' => true,
            'message' => 'Technician created successfully.',
            'data' => $technician->load('user'),
        ], 201);
    }

    public function update(
        Request $request,
        int $technicianId
    ) {
        $validated = $request->validate([
            'fullname' => [
                'required',
                'string',
                'max:100',
            ],

            'email' => [
                'required',
                'email',
                'max:100',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],

            'service_area' => [
                'nullable',
                'string',
                'max:100',
            ],

            'availability' => [
                'nullable',
                Rule::in([
                    'available',
                    'busy',
                    'offline',
                ]),
            ],
        ]);

        $technician = $this->technicianService->update(
            $technicianId,
            TechnicianData::fromArray($validated)
        );

        return response()->json([
            'success' => true,
            'message' => 'Technician updated successfully.',
            'data' => $technician->load('user'),
        ]);
    }

    public function updateStatus(
        Request $request,
        int $technicianId
    ) {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],
        ]);

        $technician = $this->technicianService->updateStatus(
            $technicianId,
            $validated['status']
        );

        return response()->json([
            'success' => true,
            'message' => 'Technician status updated successfully.',
            'data' => $technician,
        ]);
    }

    public function updateAvailability(
        Request $request,
        int $technicianId
    ) {
        $validated = $request->validate([
            'availability' => [
                'required',
                Rule::in([
                    'available',
                    'busy',
                    'offline',
                ]),
            ],
        ]);

        $technician = $this->technicianService
            ->updateAvailability(
                $technicianId,
                $validated['availability']
            );

        return response()->json([
            'success' => true,
            'message' => 'Technician availability updated successfully.',
            'data' => $technician,
        ]);
    }
}