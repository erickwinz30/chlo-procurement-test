<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\Request as ProcurementRequest;
use App\Models\RequestItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestController extends Controller
{
    public function verificationQueue(Request $request)
    {
        $purchasing = $request->user();

        $query = ProcurementRequest::query()
            ->with([
                'user:id,name,email,department_id',
                'department:id,name,code',
                'items',
            ])
            ->where('status', 'submitted')
            ->whereHas('approvals', function ($approvalQuery) use ($purchasing) {
                $approvalQuery->where('approver_id', $purchasing->id)
                    ->where('level', 1)
                    ->where('status', 'pending');
            })
            ->latest();

        if ($request->filled('priority')) {
            $query->where('priority', $request->query('priority'));
        }

        $requests = $query->paginate((int) $request->query('per_page', 10));

        return response()->json($requests);
    }

    public function verificationQueueShow(Request $request, int $id)
    {
        $purchasing = $request->user();

        $procurementRequest = ProcurementRequest::query()
            ->with([
                'user:id,name,email,department_id',
                'department:id,name,code',
                'items',
                'approvals.approver:id,name,email,role',
            ])
            ->where('id', $id)
            ->where('status', 'submitted')
            ->whereHas('approvals', function ($approvalQuery) use ($purchasing) {
                $approvalQuery->where('approver_id', $purchasing->id)
                    ->where('level', 1)
                    ->where('status', 'pending');
            })
            ->first();

        if (!$procurementRequest) {
            return response()->json([
                'message' => 'Request not found.',
            ], 404);
        }

        return response()->json([
            'data' => $procurementRequest,
        ]);
    }

    public function approvalQueue(Request $request)
    {
        $manager = $request->user();

        $query = ProcurementRequest::query()
            ->with([
                'user:id,name,email,department_id',
                'department:id,name,code',
                'items',
            ])
            ->where('status', 'verified')
            ->whereHas('approvals', function ($approvalQuery) use ($manager) {
                $approvalQuery->where('approver_id', $manager->id)
                    ->where('level', 2)
                    ->where('status', 'pending');
            })
            ->latest();

        if ($request->filled('priority')) {
            $query->where('priority', $request->query('priority'));
        }

        $requests = $query->paginate((int) $request->query('per_page', 10));

        return response()->json($requests);
    }

    public function approvalQueueShow(Request $request, int $id)
    {
        $manager = $request->user();

        $procurementRequest = ProcurementRequest::query()
            ->with([
                'user:id,name,email,department_id',
                'department:id,name,code',
                'items',
                'approvals.approver:id,name,email,role',
            ])
            ->where('id', $id)
            ->where('status', 'verified')
            ->whereHas('approvals', function ($approvalQuery) use ($manager) {
                $approvalQuery->where('approver_id', $manager->id)
                    ->where('level', 2)
                    ->where('status', 'pending');
            })
            ->first();

        if (!$procurementRequest) {
            return response()->json([
                'message' => 'Request not found.',
            ], 404);
        }

        return response()->json([
            'data' => $procurementRequest,
        ]);
    }

    public function procurementQueue(Request $request)
    {
        $query = ProcurementRequest::query()
            ->with([
                'user:id,name,email,department_id',
                'department:id,name,code',
                'items',
                'approvals.approver:id,name,email,role',
            ])
            ->where('status', 'approved')
            ->latest();

        if ($request->filled('priority')) {
            $query->where('priority', $request->query('priority'));
        }

        $requests = $query->paginate((int) $request->query('per_page', 10));

        return response()->json($requests);
    }

    public function procurementQueueShow(int $id)
    {
        $procurementRequest = ProcurementRequest::query()
            ->with([
                'user:id,name,email,department_id',
                'department:id,name,code',
                'items',
                'approvals.approver:id,name,email,role',
                'statusHistory.user:id,name,email,role',
            ])
            ->where('id', $id)
            ->where('status', 'approved')
            ->first();

        if (!$procurementRequest) {
            return response()->json([
                'message' => 'Request not found.',
            ], 404);
        }

        return response()->json([
            'data' => $procurementRequest,
        ]);
    }

    public function issue(Request $request, int $id)
    {
        $warehouse = $request->user();

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.request_item_id' => ['required', 'integer', 'exists:request_items,id'],
            'items.*.stock_id' => ['nullable', 'integer', 'exists:stocks,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $procurementRequest = ProcurementRequest::with('items')->find($id);

        if (!$procurementRequest) {
            return response()->json([
                'message' => 'Request not found.',
            ], 404);
        }

        if (!in_array($procurementRequest->status, ['approved', 'in_procurement'], true)) {
            return response()->json([
                'message' => 'Stock can only be issued for approved or in-procurement requests.',
            ], 422);
        }

        $requestItemsById = $procurementRequest->items->keyBy('id');
        $issuePlans = [];

        foreach ($validated['items'] as $itemInput) {
            $requestItem = $requestItemsById->get($itemInput['request_item_id']);

            if (!$requestItem) {
                return response()->json([
                    'message' => 'One or more items do not belong to this request.',
                ], 422);
            }

            $issueQty = (int) ($itemInput['quantity'] ?? $requestItem->quantity);

            if ($issueQty > $requestItem->quantity) {
                return response()->json([
                    'message' => 'Issued quantity cannot exceed requested quantity.',
                ], 422);
            }

            $stock = $this->resolveStockForRequestItem(
                $requestItem->item_name,
                $requestItem->specification,
                isset($itemInput['stock_id']) ? (int) $itemInput['stock_id'] : null
            );

            if (!$stock) {
                return response()->json([
                    'message' => 'Stock record not found for one or more items. Provide stock_id in payload for exact mapping.',
                ], 422);
            }

            $issuePlans[] = [
                'request_item' => $requestItem,
                'issue_qty' => $issueQty,
                'stock_id' => $stock->id,
            ];
        }

        DB::transaction(function () use ($validated, $warehouse, $procurementRequest, $issuePlans) {
            $fromStatus = $procurementRequest->status;

            foreach ($issuePlans as $plan) {
                $requestItem = $plan['request_item'];
                $issueQty = $plan['issue_qty'];
                /** @var Stock|null $stock */
                $stock = Stock::query()->lockForUpdate()->find($plan['stock_id']);

                if (!$stock || $stock->quantity < $issueQty) {
                    throw ValidationException::withMessages([
                        'items' => ['Insufficient stock for one or more items.'],
                    ]);
                }

                $quantityBefore = (int) $stock->quantity;
                $quantityAfter = $quantityBefore - $issueQty;

                $stock->update([
                    'quantity' => $quantityAfter,
                    'last_updated_at' => now(),
                ]);

                StockMovement::create([
                    'stock_id' => $stock->id,
                    'request_id' => $procurementRequest->id,
                    'procurement_order_id' => null,
                    'moved_by' => $warehouse->id,
                    'movement_type' => 'out',
                    'quantity' => $issueQty,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityAfter,
                    'unit_price' => $stock->last_purchase_price,
                    'notes' => $validated['notes'] ?? ('Stock issued for request ' . $procurementRequest->request_number),
                    'moved_at' => now(),
                ]);
            }

            $procurementRequest->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            StatusHistory::create([
                'request_id' => $procurementRequest->id,
                'user_id' => $warehouse->id,
                'from_status' => $fromStatus,
                'to_status' => 'completed',
                'notes' => $validated['notes'] ?? 'Stock issued by warehouse to requester.',
            ]);
        });

        return response()->json([
            'message' => 'Stock issued successfully and request marked as completed.',
            'data' => $procurementRequest->fresh(['items', 'statusHistory.user:id,name,email,role']),
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = ProcurementRequest::query()
            ->with(['items', 'approvals.approver:id,name,email,role'])
            ->where('user_id', $user->id)
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $requests = $query->paginate((int) $request->query('per_page', 10));

        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'needed_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.specification' => ['nullable', 'string'],
            'items.*.category' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.estimated_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $createdRequest = DB::transaction(function () use ($validated, $user) {
            $procurementRequest = ProcurementRequest::create([
                'user_id' => $user->id,
                'department_id' => $user->department_id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'priority' => $validated['priority'],
                'status' => 'draft',
                'needed_date' => $validated['needed_date'] ?? null,
                'request_number' => $this->generateRequestNumber(),
            ]);

            foreach ($validated['items'] as $item) {
                RequestItem::create([
                    'request_id' => $procurementRequest->id,
                    'item_name' => $item['item_name'],
                    'specification' => $item['specification'] ?? null,
                    'category' => $item['category'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'estimated_price' => $item['estimated_price'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $procurementRequest->load('items');
        });

        return response()->json([
            'message' => 'Request draft created successfully.',
            'data' => $createdRequest,
        ], 201);
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $procurementRequest = ProcurementRequest::with([
            'items',
            'approvals.approver:id,name,email,role',
            'statusHistory.user:id,name,email,role',
        ])
            ->where('user_id', $user->id)
            ->find($id);

        if (!$procurementRequest) {
            return response()->json([
                'message' => 'Request not found.',
            ], 404);
        }

        return response()->json([
            'data' => $procurementRequest,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();

        $procurementRequest = ProcurementRequest::where('user_id', $user->id)->find($id);

        if (!$procurementRequest) {
            return response()->json([
                'message' => 'Request not found.',
            ], 404);
        }

        if ($procurementRequest->status !== 'draft') {
            return response()->json([
                'message' => 'Request can only be updated when status is draft.',
            ], 422);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'needed_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.specification' => ['nullable', 'string'],
            'items.*.category' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.estimated_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $updatedRequest = DB::transaction(function () use ($procurementRequest, $validated) {
            $procurementRequest->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'priority' => $validated['priority'],
                'needed_date' => $validated['needed_date'] ?? null,
            ]);

            $procurementRequest->items()->delete();

            foreach ($validated['items'] as $item) {
                RequestItem::create([
                    'request_id' => $procurementRequest->id,
                    'item_name' => $item['item_name'],
                    'specification' => $item['specification'] ?? null,
                    'category' => $item['category'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'estimated_price' => $item['estimated_price'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $procurementRequest->load('items');
        });

        return response()->json([
            'message' => 'Request updated successfully.',
            'data' => $updatedRequest,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $user = $request->user();

        $procurementRequest = ProcurementRequest::where('user_id', $user->id)->find($id);

        if (!$procurementRequest) {
            return response()->json([
                'message' => 'Request not found.',
            ], 404);
        }

        if ($procurementRequest->status !== 'draft') {
            return response()->json([
                'message' => 'Request can only be cancelled when status is draft.',
            ], 422);
        }

        DB::transaction(function () use ($procurementRequest, $user) {
            $fromStatus = $procurementRequest->status;

            $procurementRequest->update([
                'status' => 'cancelled',
            ]);

            StatusHistory::create([
                'request_id' => $procurementRequest->id,
                'user_id' => $user->id,
                'from_status' => $fromStatus,
                'to_status' => 'cancelled',
                'notes' => 'Cancelled by employee.',
            ]);

            $procurementRequest->delete();
        });

        return response()->json(null, 204);
    }

    public function submit(Request $request, int $id)
    {
        $user = $request->user();

        $procurementRequest = ProcurementRequest::with('items')
            ->where('user_id', $user->id)
            ->find($id);

        if (!$procurementRequest) {
            return response()->json([
                'message' => 'Request not found.',
            ], 404);
        }

        if ($procurementRequest->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft request can be submitted.',
            ], 422);
        }

        if ($procurementRequest->items->isEmpty()) {
            return response()->json([
                'message' => 'Request must contain at least one item before submit.',
            ], 422);
        }

        $purchasingUsers = User::query()
            ->where('role', 'purchasing')
            ->whereNull('deleted_at')
            ->get();

        if ($purchasingUsers->isEmpty()) {
            return response()->json([
                'message' => 'No purchasing staff found for approval routing.',
            ], 422);
        }

        DB::transaction(function () use ($procurementRequest, $purchasingUsers, $user) {
            $fromStatus = $procurementRequest->status;

            $procurementRequest->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            foreach ($purchasingUsers as $purchasingUser) {
                Approval::updateOrCreate(
                    [
                        'request_id' => $procurementRequest->id,
                        'approver_id' => $purchasingUser->id,
                        'level' => 1,
                    ],
                    [
                        'status' => 'pending',
                        'notes' => null,
                        'approved_at' => null,
                    ]
                );
            }

            StatusHistory::create([
                'request_id' => $procurementRequest->id,
                'user_id' => $user->id,
                'from_status' => $fromStatus,
                'to_status' => 'submitted',
                'notes' => 'Submitted by employee and waiting purchasing approval.',
            ]);
        });

        return response()->json([
            'message' => 'Request submitted successfully and routed to purchasing for approval.',
            'data' => ProcurementRequest::with(['items', 'approvals.approver:id,name,email,role'])->find($procurementRequest->id),
        ]);
    }

    public function verify(Request $request, int $id)
    {
        $purchasing = $request->user();

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $procurementRequest = ProcurementRequest::find($id);

        if (!$procurementRequest) {
            return response()->json([
                'message' => 'Request not found.',
            ], 404);
        }

        if ($procurementRequest->status !== 'submitted') {
            return response()->json([
                'message' => 'Only submitted request can be verified by purchasing.',
            ], 422);
        }

        $pendingApproval = Approval::query()
            ->where('request_id', $procurementRequest->id)
            ->where('approver_id', $purchasing->id)
            ->where('level', 1)
            ->where('status', 'pending')
            ->first();

        if (!$pendingApproval) {
            return response()->json([
                'message' => 'No pending purchasing approval found for this user.',
            ], 422);
        }

        $managers = User::query()
            ->where('role', 'manager')
            ->whereNull('deleted_at')
            ->get();

        if ($managers->isEmpty()) {
            return response()->json([
                'message' => 'No manager found for next approval stage.',
            ], 422);
        }

        DB::transaction(function () use ($procurementRequest, $purchasing, $validated, $pendingApproval, $managers) {
            $fromStatus = $procurementRequest->status;

            $pendingApproval->update([
                'status' => 'approved',
                'notes' => $validated['notes'] ?? null,
                'approved_at' => now(),
            ]);

            $procurementRequest->update([
                'status' => 'verified',
            ]);

            foreach ($managers as $manager) {
                Approval::updateOrCreate(
                    [
                        'request_id' => $procurementRequest->id,
                        'approver_id' => $manager->id,
                        'level' => 2,
                    ],
                    [
                        'status' => 'pending',
                        'notes' => null,
                        'approved_at' => null,
                    ]
                );
            }

            StatusHistory::create([
                'request_id' => $procurementRequest->id,
                'user_id' => $purchasing->id,
                'from_status' => $fromStatus,
                'to_status' => 'verified',
                'notes' => $validated['notes'] ?? 'Verified by purchasing and forwarded to manager approval.',
            ]);
        });

        return response()->json([
            'message' => 'Request verified by purchasing and forwarded to manager approval.',
            'data' => ProcurementRequest::with(['approvals.approver:id,name,email,role'])->find($procurementRequest->id),
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $manager = $request->user();

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $procurementRequest = ProcurementRequest::find($id);

        if (!$procurementRequest) {
            return response()->json([
                'message' => 'Request not found.',
            ], 404);
        }

        if ($procurementRequest->status === 'approved') {
            return response()->json([
                'message' => 'Request is already approved.',
            ], 422);
        }

        if ($procurementRequest->status === 'rejected') {
            return response()->json([
                'message' => 'Request is already rejected.',
            ], 422);
        }

        if ($procurementRequest->status !== 'verified') {
            return response()->json([
                'message' => 'Request must be verified by purchasing before manager decision.',
            ], 422);
        }

        DB::transaction(function () use ($procurementRequest, $manager, $validated) {
            $fromStatus = $procurementRequest->status;

            $procurementRequest->update([
                'status' => 'approved',
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            Approval::updateOrCreate(
                [
                    'request_id' => $procurementRequest->id,
                    'approver_id' => $manager->id,
                    'level' => 2,
                ],
                [
                    'status' => 'approved',
                    'notes' => $validated['notes'] ?? null,
                    'approved_at' => now(),
                ]
            );

            StatusHistory::create([
                'request_id' => $procurementRequest->id,
                'user_id' => $manager->id,
                'from_status' => $fromStatus,
                'to_status' => 'approved',
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return response()->json([
            'message' => 'Request approved successfully.',
            'data' => ProcurementRequest::with(['approvals.approver:id,name,email,role'])->find($procurementRequest->id),
        ]);
    }

    public function reject(Request $request, int $id)
    {
        $manager = $request->user();

        $validated = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $procurementRequest = ProcurementRequest::find($id);

        if (!$procurementRequest) {
            return response()->json([
                'message' => 'Request not found.',
            ], 404);
        }

        if ($procurementRequest->status === 'rejected') {
            return response()->json([
                'message' => 'Request is already rejected.',
            ], 422);
        }

        if ($procurementRequest->status === 'approved') {
            return response()->json([
                'message' => 'Request is already approved.',
            ], 422);
        }

        if ($procurementRequest->status !== 'verified') {
            return response()->json([
                'message' => 'Request must be verified by purchasing before manager decision.',
            ], 422);
        }

        DB::transaction(function () use ($procurementRequest, $manager, $validated) {
            $fromStatus = $procurementRequest->status;

            $procurementRequest->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['reason'],
                'approved_at' => null,
            ]);

            Approval::updateOrCreate(
                [
                    'request_id' => $procurementRequest->id,
                    'approver_id' => $manager->id,
                    'level' => 2,
                ],
                [
                    'status' => 'rejected',
                    'notes' => $validated['reason'],
                    'approved_at' => now(),
                ]
            );

            StatusHistory::create([
                'request_id' => $procurementRequest->id,
                'user_id' => $manager->id,
                'from_status' => $fromStatus,
                'to_status' => 'rejected',
                'notes' => $validated['reason'],
            ]);
        });

        return response()->json([
            'message' => 'Request rejected successfully.',
            'data' => ProcurementRequest::with(['approvals.approver:id,name,email,role'])->find($procurementRequest->id),
        ]);
    }

    public function approvals(int $id)
    {
        $procurementRequest = ProcurementRequest::with(['approvals.approver:id,name,email,role'])->find($id);

        if (!$procurementRequest) {
            return response()->json([
                'message' => 'Request not found.',
            ], 404);
        }

        return response()->json([
            'data' => $procurementRequest->approvals,
        ]);
    }

    public function approvedApprovals(Request $request)
    {
        $approvedApprovals = Approval::query()
            ->with([
                'request:id,request_number,title,status,user_id',
                'request.user:id,name,email',
                'approver:id,name,email,role',
            ])
            ->where('status', 'approved')
            ->latest()
            ->paginate((int) $request->query('per_page', 10));

        return response()->json($approvedApprovals);
    }

    public function history(int $id)
    {
        $procurementRequest = ProcurementRequest::with(['statusHistory.user:id,name,email,role'])->find($id);

        if (!$procurementRequest) {
            return response()->json([
                'message' => 'Request not found.',
            ], 404);
        }

        return response()->json([
            'data' => $procurementRequest->statusHistory,
        ]);
    }

    public function decisionHistory(Request $request)
    {
        $histories = StatusHistory::query()
            ->with([
                'request:id,request_number,title,status,user_id',
                'request.user:id,name,email',
                'user:id,name,email,role',
            ])
            ->whereIn('to_status', ['approved', 'rejected'])
            ->latest()
            ->paginate((int) $request->query('per_page', 10));

        return response()->json($histories);
    }

    private function generateRequestNumber(): string
    {
        $prefix = 'REQ-' . now()->format('Ym') . '-';
        $count = ProcurementRequest::where('request_number', 'like', $prefix . '%')->count() + 1;

        return $prefix . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function resolveStockForRequestItem(string $itemName, ?string $specification, ?int $stockId = null): ?Stock
    {
        if ($stockId) {
            $stockById = Stock::find($stockId);

            return $stockById instanceof Stock ? $stockById : null;
        }

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
