<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Request as ProcurementRequest;
use App\Models\Approval;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManagerApprovalEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_get_approval_queue(): void
    {
        $manager = $this->createUser('manager');
        $employee = $this->createUser('employee');

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan Docking Station',
            'description' => 'Perlu docking station',
            'priority' => 'normal',
            'status' => 'verified',
            'submitted_at' => now(),
            'request_number' => 'REQ-202603-1200',
        ]);

        Approval::create([
            'request_id' => $request->id,
            'approver_id' => $manager->id,
            'status' => 'pending',
            'level' => 2,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/requests/approval-queue');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $request->id);
        $response->assertJsonPath('data.0.status', 'verified');
    }

    public function test_manager_can_get_approval_queue_detail(): void
    {
        $manager = $this->createUser('manager');
        $employee = $this->createUser('employee');

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan Proyektor',
            'description' => 'Proyektor ruang meeting',
            'priority' => 'high',
            'status' => 'verified',
            'submitted_at' => now(),
            'request_number' => 'REQ-202603-1201',
        ]);

        Approval::create([
            'request_id' => $request->id,
            'approver_id' => $manager->id,
            'status' => 'pending',
            'level' => 2,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/requests/approval-queue/{$request->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $request->id);
        $response->assertJsonPath('data.status', 'verified');
    }

    public function test_purchasing_can_get_verification_queue(): void
    {
        $purchasing = $this->createUser('purchasing');
        $employee = $this->createUser('employee');

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan Monitor',
            'description' => 'Monitor untuk tim',
            'priority' => 'normal',
            'status' => 'submitted',
            'submitted_at' => now(),
            'request_number' => 'REQ-202603-1100',
        ]);

        Approval::create([
            'request_id' => $request->id,
            'approver_id' => $purchasing->id,
            'status' => 'pending',
            'level' => 1,
        ]);

        Sanctum::actingAs($purchasing);

        $response = $this->getJson('/api/requests/verification-queue');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $request->id);
        $response->assertJsonPath('data.0.status', 'submitted');
    }

    public function test_purchasing_can_get_verification_queue_detail(): void
    {
        $purchasing = $this->createUser('purchasing');
        $employee = $this->createUser('employee');

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan Scanner',
            'description' => 'Scanner baru',
            'priority' => 'normal',
            'status' => 'submitted',
            'submitted_at' => now(),
            'request_number' => 'REQ-202603-1101',
        ]);

        Approval::create([
            'request_id' => $request->id,
            'approver_id' => $purchasing->id,
            'status' => 'pending',
            'level' => 1,
        ]);

        Sanctum::actingAs($purchasing);

        $response = $this->getJson("/api/requests/verification-queue/{$request->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $request->id);
        $response->assertJsonPath('data.status', 'submitted');
    }

    public function test_purchasing_must_verify_before_manager_can_approve(): void
    {
        $purchasing = $this->createUser('purchasing');
        $manager = $this->createUser('manager');
        $employee = $this->createUser('employee');

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan Printer',
            'description' => 'Printer kantor',
            'priority' => 'normal',
            'status' => 'submitted',
            'submitted_at' => now(),
            'request_number' => 'REQ-202603-1000',
        ]);

        Approval::create([
            'request_id' => $request->id,
            'approver_id' => $purchasing->id,
            'status' => 'pending',
            'level' => 1,
        ]);

        Sanctum::actingAs($manager);

        $failResponse = $this->postJson("/api/requests/{$request->id}/approve", [
            'notes' => 'Approve langsung',
        ]);

        $failResponse->assertStatus(422);
        $failResponse->assertJsonPath('message', 'Request must be verified by purchasing before manager decision.');

        Sanctum::actingAs($purchasing);

        $verifyResponse = $this->postJson("/api/requests/{$request->id}/verify", [
            'notes' => 'Data request valid',
        ]);

        $verifyResponse->assertOk();
        $verifyResponse->assertJsonPath('data.status', 'verified');

        Sanctum::actingAs($manager);

        $approveResponse = $this->postJson("/api/requests/{$request->id}/approve", [
            'notes' => 'Approved by manager',
        ]);

        $approveResponse->assertOk();
        $approveResponse->assertJsonPath('data.status', 'approved');
    }

    public function test_manager_can_approve_request(): void
    {
        $manager = $this->createUser('manager');
        $employee = $this->createUser('employee');

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan Printer',
            'description' => 'Printer kantor',
            'priority' => 'normal',
            'status' => 'verified',
            'submitted_at' => now(),
            'request_number' => 'REQ-202603-1001',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/requests/{$request->id}/approve", [
            'notes' => 'Approved by manager',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('approvals', [
            'request_id' => $request->id,
            'approver_id' => $manager->id,
            'level' => 2,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('status_histories', [
            'request_id' => $request->id,
            'user_id' => $manager->id,
            'from_status' => 'verified',
            'to_status' => 'approved',
        ]);
    }

    public function test_manager_reject_requires_reason(): void
    {
        $manager = $this->createUser('manager');
        $employee = $this->createUser('employee');

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan UPS',
            'description' => 'Perlu UPS',
            'priority' => 'high',
            'status' => 'verified',
            'submitted_at' => now(),
            'request_number' => 'REQ-202603-1002',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/requests/{$request->id}/reject", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);
    }

    public function test_non_manager_cannot_approve_request(): void
    {
        $employee = $this->createUser('employee');

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/requests/1/approve', [
            'notes' => 'Try approve',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Forbidden.');
    }

    public function test_manager_can_get_all_approved_approvals(): void
    {
        $manager = $this->createUser('manager');
        $employee = $this->createUser('employee');

        $request = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan Scanner',
            'description' => 'Scanner dokumen',
            'priority' => 'normal',
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'request_number' => 'REQ-202603-1003',
        ]);

        Approval::create([
            'request_id' => $request->id,
            'approver_id' => $manager->id,
            'status' => 'approved',
            'level' => 2,
            'notes' => 'OK',
            'approved_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/requests/approvals/approved');

        $response->assertOk();
        $response->assertJsonPath('data.0.status', 'approved');
    }

    public function test_manager_can_get_all_approved_and_rejected_decision_history(): void
    {
        $manager = $this->createUser('manager');
        $employee = $this->createUser('employee');

        $approvedRequest = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan AP',
            'description' => 'Access point',
            'priority' => 'normal',
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'request_number' => 'REQ-202603-1004',
        ]);

        $rejectedRequest = ProcurementRequest::create([
            'user_id' => $employee->id,
            'department_id' => $employee->department_id,
            'title' => 'Pengadaan Proyektor',
            'description' => 'Ruang meeting',
            'priority' => 'high',
            'status' => 'rejected',
            'submitted_at' => now(),
            'request_number' => 'REQ-202603-1005',
            'rejection_reason' => 'Budget tidak cukup',
        ]);

        StatusHistory::create([
            'request_id' => $approvedRequest->id,
            'user_id' => $manager->id,
            'from_status' => 'submitted',
            'to_status' => 'approved',
            'notes' => 'Approved',
        ]);

        StatusHistory::create([
            'request_id' => $rejectedRequest->id,
            'user_id' => $manager->id,
            'from_status' => 'submitted',
            'to_status' => 'rejected',
            'notes' => 'Rejected',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/requests/history');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
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
