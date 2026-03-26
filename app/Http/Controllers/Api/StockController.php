<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
  public function movementIndex(Request $request)
  {
    $query = StockMovement::query()
      ->with([
        'stock:id,item_name,specification,category,unit,location',
        'request:id,request_number,title,status',
        'procurementOrder:id,po_number,status',
        'mover:id,name,email,role',
      ])
      ->latest('moved_at')
      ->latest();

    if ($request->filled('movement_type')) {
      $query->where('movement_type', $request->query('movement_type'));
    }

    if ($request->filled('stock_id')) {
      $query->where('stock_id', (int) $request->query('stock_id'));
    }

    if ($request->filled('request_id')) {
      $query->where('request_id', (int) $request->query('request_id'));
    }

    if ($request->filled('procurement_order_id')) {
      $query->where('procurement_order_id', (int) $request->query('procurement_order_id'));
    }

    if ($request->filled('date_from')) {
      $query->whereDate('moved_at', '>=', $request->query('date_from'));
    }

    if ($request->filled('date_to')) {
      $query->whereDate('moved_at', '<=', $request->query('date_to'));
    }

    $movements = $query->paginate((int) $request->query('per_page', 10));

    $movements->through(function (StockMovement $movement) {
      return $this->formatMovement($movement);
    });

    return response()->json($movements);
  }

  public function movementShow(int $id)
  {
    $movement = StockMovement::with([
      'stock:id,item_name,specification,category,unit,location',
      'request:id,request_number,title,status',
      'procurementOrder:id,po_number,status',
      'mover:id,name,email,role',
    ])->find($id);

    if (!$movement) {
      return response()->json([
        'message' => 'Stock movement not found.',
      ], 404);
    }

    return response()->json([
      'data' => $this->formatMovement($movement),
    ]);
  }

  public function movementSummary(Request $request)
  {
    $baseQuery = StockMovement::query();

    if ($request->filled('date_from')) {
      $baseQuery->whereDate('moved_at', '>=', $request->query('date_from'));
    }

    if ($request->filled('date_to')) {
      $baseQuery->whereDate('moved_at', '<=', $request->query('date_to'));
    }

    $summaryByType = (clone $baseQuery)
      ->select('movement_type', DB::raw('COUNT(*) as total_rows'), DB::raw('COALESCE(SUM(quantity), 0) as total_qty'))
      ->groupBy('movement_type')
      ->get();

    $resultByType = [
      'in' => ['total_rows' => 0, 'total_qty' => 0],
      'out' => ['total_rows' => 0, 'total_qty' => 0],
      'adjustment' => ['total_rows' => 0, 'total_qty' => 0],
    ];

    foreach ($summaryByType as $row) {
      $resultByType[$row->movement_type] = [
        'total_rows' => (int) $row->total_rows,
        'total_qty' => (int) $row->total_qty,
      ];
    }

    $lowStockCount = Stock::query()->whereColumn('quantity', '<=', 'min_stock')->where('quantity', '>', 0)->count();
    $outStockCount = Stock::query()->where('quantity', 0)->count();

    return response()->json([
      'summary' => [
        'movement_in' => $resultByType['in'],
        'movement_out' => $resultByType['out'],
        'movement_adjustment' => $resultByType['adjustment'],
        'net_qty' => $resultByType['in']['total_qty'] - $resultByType['out']['total_qty'],
        'low_stock_items' => $lowStockCount,
        'out_of_stock_items' => $outStockCount,
      ],
    ]);
  }

  public function index(Request $request)
  {
    $query = Stock::query()->latest('last_updated_at')->latest();

    if ($request->filled('search')) {
      $search = (string) $request->query('search');
      $query->where(function ($q) use ($search) {
        $q->where('item_name', 'like', "%{$search}%")
          ->orWhere('specification', 'like', "%{$search}%")
          ->orWhere('category', 'like', "%{$search}%")
          ->orWhere('location', 'like', "%{$search}%");
      });
    }

    if ($request->filled('category')) {
      $query->where('category', $request->query('category'));
    }

    if ($request->filled('location')) {
      $query->where('location', $request->query('location'));
    }

    if ($request->filled('stock_status')) {
      $stockStatus = $request->query('stock_status');

      if ($stockStatus === 'out') {
        $query->where('quantity', 0);
      } elseif ($stockStatus === 'low') {
        $query->whereColumn('quantity', '<=', 'min_stock')
          ->where('quantity', '>', 0);
      } elseif ($stockStatus === 'available') {
        $query->whereColumn('quantity', '>', 'min_stock');
      }
    }

    $stocks = $query->paginate((int) $request->query('per_page', 10));

    $stocks->through(function (Stock $stock) {
      return $this->formatStock($stock);
    });

    return response()->json($stocks);
  }

  public function show(int $id)
  {
    $stock = Stock::find($id);

    if (!$stock) {
      return response()->json([
        'message' => 'Stock not found.',
      ], 404);
    }

    return response()->json([
      'data' => $this->formatStock($stock),
    ]);
  }

  private function formatStock(Stock $stock): array
  {
    return [
      'id' => $stock->id,
      'item_name' => $stock->item_name,
      'specification' => $stock->specification,
      'category' => $stock->category,
      'quantity' => $stock->quantity,
      'unit' => $stock->unit,
      'min_stock' => $stock->min_stock,
      'max_stock' => $stock->max_stock,
      'last_purchase_price' => $stock->last_purchase_price,
      'location' => $stock->location,
      'last_updated_at' => $stock->last_updated_at,
      'stock_status' => $stock->isOutOfStock()
        ? 'out'
        : ($stock->isLowStock() ? 'low' : 'available'),
      'needs_restock' => $stock->isLowStock(),
    ];
  }

  private function formatMovement(StockMovement $movement): array
  {
    return [
      'id' => $movement->id,
      'movement_type' => $movement->movement_type,
      'quantity' => $movement->quantity,
      'quantity_before' => $movement->quantity_before,
      'quantity_after' => $movement->quantity_after,
      'unit_price' => $movement->unit_price,
      'notes' => $movement->notes,
      'moved_at' => $movement->moved_at,
      'stock' => $movement->stock,
      'request' => $movement->request,
      'procurement_order' => $movement->procurementOrder,
      'moved_by' => $movement->mover,
    ];
  }
}
