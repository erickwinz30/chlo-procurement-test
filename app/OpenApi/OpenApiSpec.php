<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
  version: '1.0.0',
  title: 'Procurement Backend API',
  description: 'API documentation for internal procurement workflow.'
)]
#[OA\Server(
  url: 'http://localhost:8000',
  description: 'Local server'
)]
#[OA\SecurityScheme(
  securityScheme: 'sanctum',
  type: 'http',
  scheme: 'bearer',
  bearerFormat: 'Token',
  description: 'Use Bearer token from /api/login'
)]
class OpenApiSpec
{
  #[OA\Post(
    path: '/api/register',
    tags: ['Auth'],
    summary: 'Register user and get bearer token',
    responses: [
      new OA\Response(response: 201, description: 'User registered'),
      new OA\Response(response: 422, description: 'Validation error'),
    ]
  )]
  public function register(): void
  {
  }

  #[OA\Post(
    path: '/api/login',
    tags: ['Auth'],
    summary: 'Login and get bearer token',
    requestBody: new OA\RequestBody(
      required: true,
      content: new OA\JsonContent(
        required: ['email', 'password'],
        properties: [
          new OA\Property(property: 'email', type: 'string', format: 'email', example: 'employee@test.com'),
          new OA\Property(property: 'password', type: 'string', example: 'password123'),
        ]
      )
    ),
    responses: [
      new OA\Response(response: 200, description: 'Login success'),
      new OA\Response(response: 422, description: 'Validation or credential error'),
    ]
  )]
  public function login(): void
  {
  }

  #[OA\Get(
    path: '/api/me',
    tags: ['Auth'],
    summary: 'Get current authenticated user',
    security: [['sanctum' => []]],
    responses: [
      new OA\Response(response: 200, description: 'Current user'),
      new OA\Response(response: 401, description: 'Unauthenticated'),
    ]
  )]
  public function me(): void
  {
  }

  #[OA\Post(
    path: '/api/logout',
    tags: ['Auth'],
    summary: 'Logout current user',
    security: [['sanctum' => []]],
    responses: [
      new OA\Response(response: 200, description: 'Logged out'),
      new OA\Response(response: 401, description: 'Unauthenticated'),
    ]
  )]
  public function logout(): void
  {
  }

  #[OA\Get(
    path: '/api/requests',
    tags: ['Requests - Employee'],
    summary: 'Get employee requests',
    security: [['sanctum' => []]],
    responses: [
      new OA\Response(response: 200, description: 'Request list'),
      new OA\Response(response: 403, description: 'Forbidden'),
    ]
  )]
  public function employeeRequestList(): void
  {
  }

  #[OA\Post(
    path: '/api/requests',
    tags: ['Requests - Employee'],
    summary: 'Create employee draft request',
    security: [['sanctum' => []]],
    responses: [
      new OA\Response(response: 201, description: 'Draft created'),
      new OA\Response(response: 422, description: 'Validation error'),
    ]
  )]
  public function employeeRequestCreate(): void
  {
  }

  #[OA\Get(
    path: '/api/requests/{id}',
    tags: ['Requests - Employee'],
    summary: 'Get employee request detail',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Request detail'),
      new OA\Response(response: 404, description: 'Not found'),
    ]
  )]
  public function employeeRequestShow(): void
  {
  }

  #[OA\Put(
    path: '/api/requests/{id}',
    tags: ['Requests - Employee'],
    summary: 'Update employee draft request',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Request updated'),
      new OA\Response(response: 422, description: 'Business rule or validation error'),
    ]
  )]
  public function employeeRequestUpdate(): void
  {
  }

  #[OA\Delete(
    path: '/api/requests/{id}',
    tags: ['Requests - Employee'],
    summary: 'Cancel employee draft request',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 204, description: 'Deleted'),
      new OA\Response(response: 422, description: 'Only draft can be cancelled'),
    ]
  )]
  public function employeeRequestDelete(): void
  {
  }

  #[OA\Post(
    path: '/api/requests/{id}/submit',
    tags: ['Requests - Employee'],
    summary: 'Submit employee draft request',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Submitted'),
      new OA\Response(response: 422, description: 'Business rule error'),
    ]
  )]
  public function employeeRequestSubmit(): void
  {
  }

  #[OA\Get(
    path: '/api/requests/verification-queue',
    tags: ['Requests - Purchasing'],
    summary: 'Get purchasing verification queue',
    security: [['sanctum' => []]],
    responses: [
      new OA\Response(response: 200, description: 'Queue data'),
      new OA\Response(response: 403, description: 'Forbidden'),
    ]
  )]
  public function purchasingQueue(): void
  {
  }

  #[OA\Get(
    path: '/api/requests/verification-queue/{id}',
    tags: ['Requests - Purchasing'],
    summary: 'Get purchasing verification queue detail',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Queue detail'),
      new OA\Response(response: 404, description: 'Not found'),
    ]
  )]
  public function purchasingQueueShow(): void
  {
  }

  #[OA\Post(
    path: '/api/requests/{id}/verify',
    tags: ['Requests - Purchasing'],
    summary: 'Verify submitted request by purchasing',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Request verified'),
      new OA\Response(response: 422, description: 'Business rule error'),
    ]
  )]
  public function purchasingVerify(): void
  {
  }

  #[OA\Get(
    path: '/api/requests/approval-queue',
    tags: ['Requests - Manager'],
    summary: 'Get manager approval queue',
    security: [['sanctum' => []]],
    responses: [
      new OA\Response(response: 200, description: 'Queue data'),
      new OA\Response(response: 403, description: 'Forbidden'),
    ]
  )]
  public function managerQueue(): void
  {
  }

  #[OA\Get(
    path: '/api/requests/approval-queue/{id}',
    tags: ['Requests - Manager'],
    summary: 'Get manager approval queue detail',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Queue detail'),
      new OA\Response(response: 404, description: 'Not found'),
    ]
  )]
  public function managerQueueShow(): void
  {
  }

  #[OA\Get(
    path: '/api/requests/approvals/approved',
    tags: ['Requests - Manager'],
    summary: 'Get approved approvals list',
    security: [['sanctum' => []]],
    responses: [
      new OA\Response(response: 200, description: 'Approved approvals'),
    ]
  )]
  public function managerApprovedApprovals(): void
  {
  }

  #[OA\Get(
    path: '/api/requests/history',
    tags: ['Requests - Manager'],
    summary: 'Get manager decision history',
    security: [['sanctum' => []]],
    responses: [
      new OA\Response(response: 200, description: 'Decision history'),
    ]
  )]
  public function managerDecisionHistory(): void
  {
  }

  #[OA\Post(
    path: '/api/requests/{id}/approve',
    tags: ['Requests - Manager'],
    summary: 'Approve request as manager',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Request approved'),
      new OA\Response(response: 422, description: 'Business rule error'),
    ]
  )]
  public function managerApprove(): void
  {
  }

  #[OA\Post(
    path: '/api/requests/{id}/reject',
    tags: ['Requests - Manager'],
    summary: 'Reject request as manager',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Request rejected'),
      new OA\Response(response: 422, description: 'Business rule error'),
    ]
  )]
  public function managerReject(): void
  {
  }

  #[OA\Get(
    path: '/api/requests/{id}/approvals',
    tags: ['Requests - Manager'],
    summary: 'Get approvals of a request',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Approvals list'),
    ]
  )]
  public function managerApprovalsByRequest(): void
  {
  }

  #[OA\Get(
    path: '/api/requests/{id}/history',
    tags: ['Requests - Manager'],
    summary: 'Get status history of a request',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Status history'),
    ]
  )]
  public function managerHistoryByRequest(): void
  {
  }

  #[OA\Get(
    path: '/api/requests/procurement-queue',
    tags: ['Requests - Warehouse'],
    summary: 'Get warehouse procurement queue',
    security: [['sanctum' => []]],
    responses: [
      new OA\Response(response: 200, description: 'Queue data'),
      new OA\Response(response: 403, description: 'Forbidden'),
    ]
  )]
  public function warehouseQueue(): void
  {
  }

  #[OA\Get(
    path: '/api/requests/procurement-queue/{id}',
    tags: ['Requests - Warehouse'],
    summary: 'Get warehouse procurement queue detail',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Queue detail'),
      new OA\Response(response: 404, description: 'Not found'),
    ]
  )]
  public function warehouseQueueShow(): void
  {
  }

  #[OA\Get(
    path: '/api/vendors',
    tags: ['Vendors'],
    summary: 'Get vendor list',
    security: [['sanctum' => []]],
    responses: [
      new OA\Response(response: 200, description: 'Vendor list'),
      new OA\Response(response: 403, description: 'Forbidden'),
    ]
  )]
  public function vendorList(): void
  {
  }

  #[OA\Post(
    path: '/api/vendors',
    tags: ['Vendors'],
    summary: 'Create vendor (purchasing)',
    security: [['sanctum' => []]],
    responses: [
      new OA\Response(response: 201, description: 'Vendor created'),
      new OA\Response(response: 422, description: 'Validation error'),
      new OA\Response(response: 403, description: 'Forbidden'),
    ]
  )]
  public function vendorCreate(): void
  {
  }

  #[OA\Post(
    path: '/api/requests/{id}/procure',
    tags: ['Procurement'],
    summary: 'Create procurement order from approved request',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 201, description: 'Procurement order created'),
      new OA\Response(response: 422, description: 'Business validation error'),
    ]
  )]
  public function procure(): void
  {
  }

  #[OA\Post(
    path: '/api/requests/{id}/issue',
    tags: ['Warehouse'],
    summary: 'Issue stock to requester and complete request',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Stock issued'),
      new OA\Response(response: 422, description: 'Insufficient stock or invalid payload'),
    ]
  )]
  public function issue(): void
  {
  }

  #[OA\Get(
    path: '/api/stocks',
    tags: ['Stocks'],
    summary: 'Get warehouse stock list',
    security: [['sanctum' => []]],
    responses: [
      new OA\Response(response: 200, description: 'Stock list'),
      new OA\Response(response: 403, description: 'Forbidden'),
    ]
  )]
  public function stocks(): void
  {
  }

  #[OA\Get(
    path: '/api/stocks/{id}',
    tags: ['Stocks'],
    summary: 'Get stock detail',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Stock detail'),
      new OA\Response(response: 404, description: 'Not found'),
    ]
  )]
  public function stockShow(): void
  {
  }

  #[OA\Get(
    path: '/api/stocks/movements',
    tags: ['Stock Movements'],
    summary: 'Get detailed stock movements (warehouse/purchasing)',
    security: [['sanctum' => []]],
    responses: [
      new OA\Response(response: 200, description: 'Movement list'),
      new OA\Response(response: 403, description: 'Forbidden'),
    ]
  )]
  public function stockMovements(): void
  {
  }

  #[OA\Get(
    path: '/api/stocks/movements/{id}',
    tags: ['Stock Movements'],
    summary: 'Get stock movement detail',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Movement detail'),
      new OA\Response(response: 404, description: 'Not found'),
    ]
  )]
  public function stockMovementShow(): void
  {
  }

  #[OA\Get(
    path: '/api/stocks/movements/summary',
    tags: ['Stock Movements'],
    summary: 'Get stock movement summary (manager)',
    security: [['sanctum' => []]],
    responses: [
      new OA\Response(response: 200, description: 'Movement summary'),
      new OA\Response(response: 403, description: 'Forbidden'),
    ]
  )]
  public function stockMovementSummary(): void
  {
  }

  #[OA\Get(
    path: '/api/procurement-orders',
    tags: ['Procurement Orders'],
    summary: 'Get procurement order list',
    security: [['sanctum' => []]],
    responses: [
      new OA\Response(response: 200, description: 'Order list'),
      new OA\Response(response: 403, description: 'Forbidden'),
    ]
  )]
  public function procurementOrderList(): void
  {
  }

  #[OA\Get(
    path: '/api/procurement-orders/{id}',
    tags: ['Procurement Orders'],
    summary: 'Get procurement order detail',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Order detail'),
      new OA\Response(response: 404, description: 'Not found'),
    ]
  )]
  public function procurementOrderShow(): void
  {
  }

  #[OA\Put(
    path: '/api/procurement-orders/{id}/status',
    tags: ['Procurement Orders'],
    summary: 'Update procurement order status',
    security: [['sanctum' => []]],
    parameters: [
      new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
      new OA\Response(response: 200, description: 'Status updated'),
      new OA\Response(response: 422, description: 'Invalid transition'),
    ]
  )]
  public function procurementOrderUpdateStatus(): void
  {
  }
}
