<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProcurementOrder;
use App\Models\Request as ProcurementRequest;
use App\Models\StockMovement;
use App\Models\StatusHistory;
use App\Models\Stock;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementOrderController extends Controller
{
    public function store(Request $request, int $id)
    {
        $user = $request->user();

        $validated = $request->validate([
            'vendor_id' => ['required', 'exists:vendors,id'],
            'notes' => ['nullable', 'string'],
            'expected_delivery_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.request_item_id' => ['required', 'integer', 'exists:request_items,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $procurementRequest = ProcurementRequest::with('items')->find($id);

        if (!$procurementRequest) {
            return response()->json([
                'message' => 'Request not found.',
            ], 404);
        }

        if ($procurementRequest->status !== 'approved') {
            return response()->json([
                'message' => 'Procurement can only be created when request status is approved.',
            ], 422);
        }

        $vendor = Vendor::find($validated['vendor_id']);

        if (!$vendor || !$vendor->isActive()) {
            return response()->json([
                'message' => 'Selected vendor must be active.',
            ], 422);
        }

        $requestItemsById = $procurementRequest->items->keyBy('id');
        $orderItems = [];
        $totalAmount = 0;

        foreach ($validated['items'] as $itemInput) {
            $requestItem = $requestItemsById->get($itemInput['request_item_id']);

            if (!$requestItem) {
                return response()->json([
                    'message' => 'One or more items do not belong to this request.',
                ], 422);
            }

            $stock = $this->resolveStock($requestItem->item_name, $requestItem->specification);

            $availableQty = $stock?->quantity ?? 0;
            $shortageQty = max($requestItem->quantity - $availableQty, 0);

            if ($shortageQty <= 0) {
                continue;
            }

            $orderQty = $itemInput['quantity'] ?? $shortageQty;
            if ($orderQty > $shortageQty) {
                return response()->json([
                    'message' => 'Order quantity cannot exceed calculated shortage.',
                ], 422);
            }

            $unitPrice = (float) ($itemInput['unit_price'] ?? $requestItem->estimated_price ?? $stock?->last_purchase_price ?? 0);
            $subtotal = $orderQty * $unitPrice;
            $totalAmount += $subtotal;

            $orderItems[] = [
                'request_item_id' => $requestItem->id,
                'item_name' => $requestItem->item_name,
                'specification' => $requestItem->specification,
                'unit' => $requestItem->unit,
                'required_quantity' => $requestItem->quantity,
                'stock_available' => $availableQty,
                'quantity_to_order' => $orderQty,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ];
        }

        if (empty($orderItems)) {
            return response()->json([
                'message' => 'All requested items are available in stock. Procurement is not required.',
            ], 422);
        }

        $po = DB::transaction(function () use ($validated, $user, $procurementRequest, $vendor, $orderItems, $totalAmount) {
            $po = ProcurementOrder::create([
                'request_id' => $procurementRequest->id,
                'vendor_id' => $vendor->id,
                'created_by' => $user->id,
                'po_number' => $this->generatePoNumber(),
                'status' => 'sent',
                'total_amount' => $totalAmount,
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'notes' => json_encode([
                    'notes' => $validated['notes'] ?? null,
                    'items' => $orderItems,
                ]),
            ]);

            $fromStatus = $procurementRequest->status;
            $procurementRequest->update([
                'status' => 'in_procurement',
            ]);

            StatusHistory::create([
                'request_id' => $procurementRequest->id,
                'user_id' => $user->id,
                'from_status' => $fromStatus,
                'to_status' => 'in_procurement',
                'notes' => 'Procurement order created for vendor ' . $vendor->name,
            ]);

            return $po;
        });

        return response()->json([
            'message' => 'Procurement order created successfully.',
            'data' => $po->load(['request:id,request_number,title,status', 'vendor:id,name,code,status', 'createdBy:id,name,email,role']),
            'items' => $orderItems,
        ], 201);
    }

    public function index(Request $request)
    {
        $query = ProcurementOrder::query()
            ->with([
                'request:id,request_number,title,status',
                'vendor:id,name,code,status',
                'createdBy:id,name,email,role',
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $orders = $query->paginate((int) $request->query('per_page', 10));

        return response()->json($orders);
    }

    public function show(int $id)
    {
        $po = ProcurementOrder::with([
            'request:id,request_number,title,status,user_id',
            'request.user:id,name,email',
            'vendor:id,name,code,status,email,phone',
            'createdBy:id,name,email,role',
        ])->find($id);

        if (!$po) {
            return response()->json([
                'message' => 'Procurement order not found.',
            ], 404);
        }

        return response()->json([
            'data' => $po,
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $actor = $request->user();

        $validated = $request->validate([
            'status' => ['required', 'in:draft,sent,confirmed,shipped,received,completed,cancelled'],
        ]);

        $po = ProcurementOrder::with('request')->find($id);

        if (!$po) {
            return response()->json([
                'message' => 'Procurement order not found.',
            ], 404);
        }

        $fromStatus = $po->status;
        $toStatus = $validated['status'];

        if ($fromStatus === $toStatus) {
            return response()->json([
                'message' => 'Procurement order already has the requested status.',
            ], 422);
        }

        if (!$this->isValidTransition($fromStatus, $toStatus)) {
            return response()->json([
                'message' => 'Invalid status transition.',
            ], 422);
        }

        DB::transaction(function () use ($po, $toStatus, $actor) {
            $payload = ['status' => $toStatus];

            if ($toStatus === 'received') {
                $payload['actual_delivery_date'] = now()->toDateString();
                $this->applyStockInMovements($po, $actor?->id);
            }

            $po->update($payload);

            if ($toStatus === 'completed' && $po->request) {
                $requestFromStatus = $po->request->status;

                $po->request->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                StatusHistory::create([
                    'request_id' => $po->request->id,
                    'user_id' => $po->created_by,
                    'from_status' => $requestFromStatus,
                    'to_status' => 'completed',
                    'notes' => 'Procurement order ' . $po->po_number . ' completed.',
                ]);
            }
        });

        return response()->json([
            'message' => 'Procurement order status updated successfully.',
            'data' => $po->fresh(['request:id,request_number,title,status', 'vendor:id,name,code,status', 'createdBy:id,name,email,role']),
        ]);
    }

    private function isValidTransition(string $from, string $to): bool
    {
        $map = [
            'draft' => ['sent', 'cancelled'],
            'sent' => ['confirmed', 'received', 'cancelled'],
            'confirmed' => ['shipped', 'received', 'cancelled'],
            'shipped' => ['received', 'cancelled'],
            'received' => ['completed'],
            'completed' => [],
            'cancelled' => [],
        ];

        return in_array($to, $map[$from] ?? [], true);
    }

    private function generatePoNumber(): string
    {
        $prefix = 'PO-' . now()->format('Ym') . '-';
        $count = ProcurementOrder::where('po_number', 'like', $prefix . '%')->count() + 1;

        return $prefix . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function applyStockInMovements(ProcurementOrder $po, ?int $movedBy): void
    {
        $items = $this->extractPoItems($po->notes);

        foreach ($items as $item) {
            $quantityIn = (int) ($item['quantity_to_order'] ?? 0);

            if ($quantityIn <= 0) {
                continue;
            }

            $stock = $this->resolveStock($item['item_name'] ?? '', $item['specification'] ?? null);

            if (!$stock) {
                $stock = Stock::create([
                    'item_name' => $item['item_name'] ?? 'Unknown Item',
                    'specification' => $item['specification'] ?? null,
                    'category' => null,
                    'quantity' => 0,
                    'unit' => $item['unit'] ?? 'pcs',
                    'min_stock' => 0,
                    'last_purchase_price' => $item['unit_price'] ?? null,
                    'last_updated_at' => now(),
                ]);
            }

            $quantityBefore = (int) $stock->quantity;
            $quantityAfter = $quantityBefore + $quantityIn;

            $stock->update([
                'quantity' => $quantityAfter,
                'unit' => $item['unit'] ?? $stock->unit,
                'last_purchase_price' => $item['unit_price'] ?? $stock->last_purchase_price,
                'last_updated_at' => now(),
            ]);

            StockMovement::create([
                'stock_id' => $stock->id,
                'request_id' => $po->request_id,
                'procurement_order_id' => $po->id,
                'moved_by' => $movedBy,
                'movement_type' => 'in',
                'quantity' => $quantityIn,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_price' => $item['unit_price'] ?? null,
                'notes' => 'Stock received from procurement order ' . $po->po_number,
                'moved_at' => now(),
            ]);
        }
    }

    private function extractPoItems(?string $notes): array
    {
        if (!$notes) {
            return [];
        }

        $decoded = json_decode($notes, true);
        if (!is_array($decoded)) {
            return [];
        }

        return isset($decoded['items']) && is_array($decoded['items'])
            ? $decoded['items']
            : [];
    }

    private function resolveStock(string $itemName, ?string $specification): ?Stock
    {
        $normalizedName = mb_strtolower(trim($itemName));
        $normalizedSpec = $specification !== null ? mb_strtolower(trim($specification)) : null;

        $baseQuery = Stock::query()->whereRaw('LOWER(TRIM(item_name)) = ?', [$normalizedName]);

        if ($normalizedSpec !== null && $normalizedSpec !== '') {
            $stock = (clone $baseQuery)
                ->whereRaw("LOWER(TRIM(COALESCE(specification, ''))) = ?", [$normalizedSpec])
                ->first();

            if ($stock instanceof Stock) {
                return $stock;
            }
        }

        $fallbackStock = $baseQuery->first();

        return $fallbackStock instanceof Stock ? $fallbackStock : null;
    }
}
