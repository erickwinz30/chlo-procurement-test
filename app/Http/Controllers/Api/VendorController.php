<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $query = Vendor::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $vendors = $query->paginate((int) $request->query('per_page', 10));

        return response()->json($vendors);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'unique:vendors,code'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,blacklisted'],
            'notes' => ['nullable', 'string'],
        ]);

        $vendor = Vendor::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'contact_person' => $validated['contact_person'] ?? null,
            'tax_id' => $validated['tax_id'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Vendor created successfully.',
            'data' => $vendor,
        ], 201);
    }
}
