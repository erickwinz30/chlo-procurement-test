<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Request as ProcurementRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeRequestEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_create_draft_request_with_items(): void
    {
        $employee = $this->createUser('employee');

        Sanctum::actingAs($employee);

        $payload = [
            'title' => 'Pengadaan Laptop',
            'description' => 'Untuk tim engineering baru',
            'priority' => 'high',
            'needed_date' => now()->addDays(7)->toDateString(),
            'items' => [
                [
                    'item_name' => 'Laptop ThinkPad',
                    'quantity' => 2,
                    'unit' => 'pcs',
                ],
            ],
        ];

        $response = $this->postJson('/api/requests', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonCount(1, 'data.items');

        $this->assertDatabaseHas('requests', [
            'user_id' => $employee->id,
            'title' => 'Pengadaan Laptop',
            'status' => 'draft',
        ]);
    }

    public function test_submit_draft_routes_request_to_purchasing_approval(): void
    {
        $employee = $this->createUser('employee');
        $purchasing = $this->createUser('purchasing');

        Sanctum::actingAs($employee);

        $createResponse = $this->postJson('/api/requests', [
            'title' => 'Pengadaan Kursi Kantor',
            'description' => 'Butuh kursi tambahan',
            'priority' => 'normal',
            'items' => [
                [
                    'item_name' => 'Kursi Ergonomis',
                    'quantity' => 5,
                    'unit' => 'pcs',
                ],
            ],
        ]);

        $requestId = $createResponse->json('data.id');

        $submitResponse = $this->postJson("/api/requests/{$requestId}/submit");

        $submitResponse->assertOk();
        $submitResponse->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('approvals', [
            'request_id' => $requestId,
            'approver_id' => $purchasing->id,
            'level' => 1,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('status_histories', [
            'request_id' => $requestId,
            'user_id' => $employee->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
        ]);
    }

    public function test_employee_cannot_update_non_draft_request(): void
    {
        $employee = $this->createUser('employee');

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Request Existing',
            'description' => null,
            'priority' => 'normal',
            'status' => 'submitted',
            'request_number' => 'REQ-202603-0001',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->putJson("/api/requests/{$request->id}", [
            'title' => 'Updated Title',
            'description' => 'Updated desc',
            'priority' => 'high',
            'items' => [
                [
                    'item_name' => 'Keyboard',
                    'quantity' => 1,
                    'unit' => 'pcs',
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Request can only be updated when status is draft.');
    }

    public function test_non_employee_is_forbidden_to_access_employee_request_endpoints(): void
    {
        $purchasing = $this->createUser('purchasing');

        Sanctum::actingAs($purchasing);

        $response = $this->getJson('/api/requests');

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Forbidden.');
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
