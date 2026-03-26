# Procurement Backend API

Backend API untuk alur internal procurement:
employee request -> purchasing verify -> manager approve/reject -> warehouse execute.

## Tech Stack

- Laravel 11
- PHP 8.2+
- PostgreSQL
- Laravel Sanctum (Bearer token)

## Quick Start

1. Install dependency.

```bash
composer install
```

2. Siapkan environment.

```bash
cp .env.example .env
php artisan key:generate
```

3. Atur database di `.env`, lalu migrate dan seed.

```bash
php artisan migrate --seed
```

4. Jalankan server.

```bash
php artisan serve
```

## Auth

- Login: `POST /api/login`
- Gunakan header `Authorization: Bearer <token>` untuk endpoint protected.
- Tidak menggunakan flow cookie CSRF SPA.

## Role Utama

- `employee`: buat, edit, submit request.
- `purchasing`: verifikasi request, lihat movement detail stok.
- `manager`: approve/reject, lihat ringkasan movement stok.
- `warehouse`: lihat stok, issue stok, proses procurement.

## Endpoint Inti

### Request Workflow

- Employee:
- `GET /api/requests`
- `POST /api/requests`
- `GET /api/requests/{id}`
- `PUT /api/requests/{id}`
- `DELETE /api/requests/{id}`
- `POST /api/requests/{id}/submit`

- Purchasing:
- `GET /api/requests/verification-queue`
- `GET /api/requests/verification-queue/{id}`
- `POST /api/requests/{id}/verify`

- Manager:
- `GET /api/requests/approval-queue`
- `GET /api/requests/approval-queue/{id}`
- `POST /api/requests/{id}/approve`
- `POST /api/requests/{id}/reject`

- Warehouse:
- `GET /api/requests/procurement-queue`
- `GET /api/requests/procurement-queue/{id}`
- `POST /api/requests/{id}/procure`
- `POST /api/requests/{id}/issue`

### Stock

- Warehouse stock:
- `GET /api/stocks`
- `GET /api/stocks/{id}`

- Stock movements:
- `GET /api/stocks/movements` (warehouse, purchasing)
- `GET /api/stocks/movements/{id}` (warehouse, purchasing)
- `GET /api/stocks/movements/summary` (manager)

## Stock Mutation Rules

- PO `received` -> stok bertambah (`stock_movements` type `in`).
- Warehouse `issue` -> stok berkurang (`stock_movements` type `out`) dan request menjadi `completed`.

## Testing

Jalankan semua test:

```bash
php artisan test
```

Jalankan test warehouse/procurement saja:

```bash
php artisan test tests/Feature/VendorAndWarehouseProcurementTest.php
```

## API Docs (Swagger)

- Swagger UI: `/api/documentation`
- Raw OpenAPI JSON: `/docs`

Regenerate docs setelah ubah annotation:

```bash
php artisan l5-swagger:generate
```
