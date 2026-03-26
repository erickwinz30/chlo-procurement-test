<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Approval;
use App\Models\ProcurementOrder;
use App\Models\Request as ProcurementRequest;
use App\Models\RequestItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorAndWarehouseProcurementTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_and_purchasing_can_view_stock_movements(): void
    {
        $warehouse = $this->createUser('warehouse');
        $purchasing = $this->createUser('purchasing');

        $stock = Stock::create([
            'item_name' => 'Access Point',
            'specification' => 'WiFi 6',
            'category' => 'Elektronik',
            'quantity' => 15,
            'unit' => 'unit',
            'min_stock' => 2,
            'last_updated_at' => now(),
        ]);

        $movement = StockMovement::create([
            'stock_id' => $stock->id,
            'movement_type' => 'in',
            'quantity' => 5,
            'quantity_before' => 10,
            'quantity_after' => 15,
            'moved_by' => $warehouse->id,
            'notes' => 'Initial receive',
            'moved_at' => now(),
        ]);

        Sanctum::actingAs($warehouse);
        $warehouseResponse = $this->getJson('/api/stocks/movements');
        $warehouseResponse->assertOk();
        $warehouseResponse->assertJsonPath('data.0.id', $movement->id);

        Sanctum::actingAs($purchasing);
        $purchasingResponse = $this->getJson("/api/stocks/movements/{$movement->id}");
        $purchasingResponse->assertOk();
        $purchasingResponse->assertJsonPath('data.id', $movement->id);
        $purchasingResponse->assertJsonPath('data.movement_type', 'in');
    }

    public function test_manager_can_view_stock_movement_summary_but_not_detail(): void
    {
        $warehouse = $this->createUser('warehouse');
        $manager = $this->createUser('manager');

        $stock = Stock::create([
            'item_name' => 'UPS',
            'specification' => '1200VA',
            'category' => 'Elektronik',
            'quantity' => 1,
            'unit' => 'unit',
            'min_stock' => 2,
            'last_updated_at' => now(),
        ]);

        StockMovement::create([
            'stock_id' => $stock->id,
            'movement_type' => 'in',
            'quantity' => 2,
            'quantity_before' => 0,
            'quantity_after' => 2,
            'moved_by' => $warehouse->id,
            'moved_at' => now(),
        ]);

        StockMovement::create([
            'stock_id' => $stock->id,
            'movement_type' => 'out',
            'quantity' => 1,
            'quantity_before' => 2,
            'quantity_after' => 1,
            'moved_by' => $warehouse->id,
            'moved_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $summary = $this->getJson('/api/stocks/movements/summary');
        $summary->assertOk();
        $summary->assertJsonPath('summary.movement_in.total_qty', 2);
        $summary->assertJsonPath('summary.movement_out.total_qty', 1);

        $detailForbidden = $this->getJson('/api/stocks/movements');
        $detailForbidden->assertStatus(403);
    }

    public function test_warehouse_can_list_stocks_with_filter(): void
    {
        $warehouse = $this->createUser('warehouse');

        Stock::create([
            'item_name' => 'Kabel LAN',
            'specification' => 'CAT6',
            'category' => 'Elektronik',
            'quantity' => 2,
            'unit' => 'pcs',
            'min_stock' => 5,
            'location' => 'Gudang B-1',
            'last_updated_at' => now(),
        ]);

        Stock::create([
            'item_name' => 'Mouse',
            'specification' => 'Wireless',
            'category' => 'Elektronik',
            'quantity' => 20,
            'unit' => 'pcs',
            'min_stock' => 5,
            'location' => 'Gudang B-2',
            'last_updated_at' => now(),
        ]);

        Sanctum::actingAs($warehouse);

        $response = $this->getJson('/api/stocks?stock_status=low');

        $response->assertOk();
        $response->assertJsonPath('data.0.item_name', 'Kabel LAN');
        $response->assertJsonPath('data.0.stock_status', 'low');
    }

    public function test_warehouse_can_view_stock_detail(): void
    {
        $warehouse = $this->createUser('warehouse');

        $stock = Stock::create([
            'item_name' => 'Switch 24 Port',
            'specification' => 'Gigabit',
            'category' => 'Elektronik',
            'quantity' => 0,
            'unit' => 'unit',
            'min_stock' => 1,
            'location' => 'Gudang B-3',
            'last_updated_at' => now(),
        ]);

        Sanctum::actingAs($warehouse);

        $response = $this->getJson("/api/stocks/{$stock->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $stock->id);
        $response->assertJsonPath('data.stock_status', 'out');
        $response->assertJsonPath('data.needs_restock', true);
    }

    public function test_warehouse_can_get_manager_approved_procurement_queue(): void
    {
        $warehouse = $this->createUser('warehouse');
        $manager = $this->createUser('manager');
        $employee = $this->createUser('employee');

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan Keyboard',
            'description' => 'Keyboard mekanik',
            'priority' => 'normal',
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'request_number' => 'REQ-202603-2100',
        ]);

        Approval::create([
            'request_id' => $request->id,
            'approver_id' => $manager->id,
            'status' => 'approved',
            'level' => 2,
            'approved_at' => now(),
        ]);

        Sanctum::actingAs($warehouse);

        $response = $this->getJson('/api/requests/procurement-queue');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $request->id);
        $response->assertJsonPath('data.0.status', 'approved');
    }

    public function test_warehouse_can_get_manager_approved_procurement_queue_detail(): void
    {
        $warehouse = $this->createUser('warehouse');
        $manager = $this->createUser('manager');
        $employee = $this->createUser('employee');

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan Mouse',
            'description' => 'Mouse wireless',
            'priority' => 'high',
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'request_number' => 'REQ-202603-2101',
        ]);

        Approval::create([
            'request_id' => $request->id,
            'approver_id' => $manager->id,
            'status' => 'approved',
            'level' => 2,
            'approved_at' => now(),
        ]);

        Sanctum::actingAs($warehouse);

        $response = $this->getJson("/api/requests/procurement-queue/{$request->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $request->id);
        $response->assertJsonPath('data.status', 'approved');
    }

    public function test_purchasing_can_create_vendor_and_warehouse_can_list_active_vendor(): void
    {
        $purchasing = $this->createUser('purchasing');
        $warehouse = $this->createUser('warehouse');

        Sanctum::actingAs($purchasing);

        $createResponse = $this->postJson('/api/vendors', [
            'name' => 'PT Sumber Jaya',
            'code' => 'VND-SJ-001',
            'address' => 'Jakarta',
            'phone' => '021123456',
            'email' => 'sales@sumberjaya.test',
            'status' => 'active',
        ]);

        $createResponse->assertCreated();
        $this->assertDatabaseHas('vendors', [
            'code' => 'VND-SJ-001',
            'status' => 'active',
        ]);

        Sanctum::actingAs($warehouse);

        $listResponse = $this->getJson('/api/vendors?status=active');
        $listResponse->assertOk();
        $listResponse->assertJsonPath('data.0.code', 'VND-SJ-001');
    }

    public function test_warehouse_can_create_procurement_when_stock_is_insufficient(): void
    {
        $warehouse = $this->createUser('warehouse');
        $employee = $this->createUser('employee');
        $vendor = Vendor::create([
            'name' => 'PT Vendor Aktif',
            'code' => 'VND-ACT-001',
            'status' => 'active',
        ]);

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan Laptop',
            'description' => 'Butuh laptop tambahan',
            'priority' => 'high',
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'request_number' => 'REQ-202603-2001',
        ]);

        $requestItem = RequestItem::create([
            'request_id' => $request->id,
            'item_name' => 'Laptop ThinkPad',
            'specification' => 'i7 16GB',
            'category' => 'Elektronik',
            'quantity' => 10,
            'unit' => 'pcs',
            'estimated_price' => 15000000,
        ]);

        Stock::create([
            'item_name' => 'Laptop ThinkPad',
            'specification' => 'i7 16GB',
            'category' => 'Elektronik',
            'quantity' => 3,
            'unit' => 'pcs',
            'min_stock' => 1,
        ]);

        Sanctum::actingAs($warehouse);

        $response = $this->postJson("/api/requests/{$request->id}/procure", [
            'vendor_id' => $vendor->id,
            'items' => [
                [
                    'request_item_id' => $requestItem->id,
                    'quantity' => 7,
                    'unit_price' => 14500000,
                ],
            ],
            'notes' => 'Order untuk menutup kekurangan stok',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('procurement_orders', [
            'request_id' => $request->id,
            'vendor_id' => $vendor->id,
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'status' => 'in_procurement',
        ]);
    }

    public function test_receiving_procurement_order_increases_stock_and_logs_movement(): void
    {
        $warehouse = $this->createUser('warehouse');
        $employee = $this->createUser('employee');

        $vendor = Vendor::create([
            'name' => 'PT Vendor Inbound',
            'code' => 'VND-IN-001',
            'status' => 'active',
        ]);

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan SSD',
            'description' => 'SSD untuk workstation',
            'priority' => 'high',
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'request_number' => 'REQ-202603-2200',
        ]);

        $requestItem = RequestItem::create([
            'request_id' => $request->id,
            'item_name' => 'SSD NVMe 1TB',
            'specification' => 'PCIe Gen4',
            'category' => 'Elektronik',
            'quantity' => 10,
            'unit' => 'pcs',
            'estimated_price' => 1200000,
        ]);

        Stock::create([
            'item_name' => 'SSD NVMe 1TB',
            'specification' => 'PCIe Gen4',
            'category' => 'Elektronik',
            'quantity' => 3,
            'unit' => 'pcs',
            'min_stock' => 1,
            'last_purchase_price' => 1150000,
        ]);

        Sanctum::actingAs($warehouse);

        $createPo = $this->postJson("/api/requests/{$request->id}/procure", [
            'vendor_id' => $vendor->id,
            'items' => [
                [
                    'request_item_id' => $requestItem->id,
                    'quantity' => 7,
                    'unit_price' => 1180000,
                ],
            ],
        ]);

        $createPo->assertCreated();
        $poId = $createPo->json('data.id');

        $receivePo = $this->putJson("/api/procurement-orders/{$poId}/status", [
            'status' => 'received',
        ]);

        $receivePo->assertOk();

        $this->assertDatabaseHas('stocks', [
            'item_name' => 'SSD NVMe 1TB',
            'specification' => 'PCIe Gen4',
            'quantity' => 10,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'procurement_order_id' => $poId,
            'request_id' => $request->id,
            'movement_type' => 'in',
            'quantity' => 7,
        ]);
    }

    public function test_warehouse_can_issue_stock_and_decrease_inventory(): void
    {
        $warehouse = $this->createUser('warehouse');
        $employee = $this->createUser('employee');

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan Mouse',
            'description' => 'Mouse untuk user baru',
            'priority' => 'normal',
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'request_number' => 'REQ-202603-2201',
        ]);

        $requestItem = RequestItem::create([
            'request_id' => $request->id,
            'item_name' => 'Mouse Wireless',
            'specification' => '2.4GHz',
            'category' => 'Elektronik',
            'quantity' => 4,
            'unit' => 'pcs',
            'estimated_price' => 200000,
        ]);

        $stock = Stock::create([
            'item_name' => 'Mouse Wireless',
            'specification' => '2.4GHz',
            'category' => 'Elektronik',
            'quantity' => 10,
            'unit' => 'pcs',
            'min_stock' => 2,
            'last_purchase_price' => 180000,
        ]);

        Sanctum::actingAs($warehouse);

        $issue = $this->postJson("/api/requests/{$request->id}/issue", [
            'notes' => 'Issued to requester',
            'items' => [
                [
                    'request_item_id' => $requestItem->id,
                    'quantity' => 4,
                ],
            ],
        ]);

        $issue->assertOk();
        $issue->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'quantity' => 6,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'stock_id' => $stock->id,
            'request_id' => $request->id,
            'movement_type' => 'out',
            'quantity' => 4,
        ]);
    }

    public function test_warehouse_can_issue_using_explicit_stock_id_mapping(): void
    {
        $warehouse = $this->createUser('warehouse');
        $employee = $this->createUser('employee');

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan AP',
            'description' => 'Butuh access point',
            'priority' => 'normal',
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'request_number' => 'REQ-202603-2202',
        ]);

        $requestItem = RequestItem::create([
            'request_id' => $request->id,
            'item_name' => 'Access Point',
            'specification' => 'Dual Band',
            'category' => 'Elektronik',
            'quantity' => 2,
            'unit' => 'unit',
            'estimated_price' => 1500000,
        ]);

        $stock = Stock::create([
            'item_name' => 'AP Ubiquiti U6-Lite',
            'specification' => 'Dual Band',
            'category' => 'Elektronik',
            'quantity' => 5,
            'unit' => 'unit',
            'min_stock' => 1,
            'last_purchase_price' => 1400000,
        ]);

        Sanctum::actingAs($warehouse);

        $issue = $this->postJson("/api/requests/{$request->id}/issue", [
            'items' => [
                [
                    'request_item_id' => $requestItem->id,
                    'stock_id' => $stock->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $issue->assertOk();

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'quantity' => 3,
        ]);
    }

    public function test_procurement_order_status_requires_valid_transition(): void
    {
        $warehouse = $this->createUser('warehouse');
        $employee = $this->createUser('employee');
        $vendor = Vendor::create([
            'name' => 'PT Vendor Dua',
            'code' => 'VND-ACT-002',
            'status' => 'active',
        ]);

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan Switch',
            'description' => 'Switch 24 port',
            'priority' => 'normal',
            'status' => 'in_procurement',
            'submitted_at' => now(),
            'approved_at' => now(),
            'request_number' => 'REQ-202603-2002',
        ]);

        $po = ProcurementOrder::create([
            'request_id' => $request->id,
            'vendor_id' => $vendor->id,
            'created_by' => $warehouse->id,
            'po_number' => 'PO-202603-2001',
            'status' => 'sent',
            'total_amount' => 5000000,
            'order_date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($warehouse);

        $response = $this->putJson("/api/procurement-orders/{$po->id}/status", [
            'status' => 'completed',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Invalid status transition.');
    }

    private function createUser(string $role): User
    {
        $department = Department::create([
            'name' => 'Dept ' . strtoupper($role),
            'code' => strtoupper(substr($role, 0, 3)) . rand(10, 99),
        ]);

        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => $role . rand(1000, 9999) . '@test.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'department_id' => $department->id,
        ]);
    }
}
