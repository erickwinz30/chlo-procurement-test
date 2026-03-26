# Context: Workflow & Database Operation Purchasing Request

Dokumen ini berisi spesifikasi alur kerja (workflow) untuk sistem Purchasing Request, mencakup transisi status, aktor yang terlibat, modifikasi tabel database, serta query SQL yang digunakan pada setiap tahapan.

## 1. DRAFT

- **Aktor:** Employee
- **Status Transisi:** `DRAFT` (Initial State)
- **Deskripsi:** Karyawan membuat permintaan baru. Data utama request dan detail barang (items) dibuat dan disimpan ke dalam database.

**Tabel yang termodifikasi:**

- **`requests` (INSERT):** Buat baris baru dengan status = 'draft', version = 1.
    ```sql
    INSERT INTO requests (id, requester_id, department_id, title, notes, priority, status, version)
    VALUES (gen_random_uuid(), $user_id, $dept_id, $title, $notes, 'normal', 'draft', 1);
    ```
- **`request_items` (INSERT):** Satu baris per barang yang diminta.
    ```sql
    INSERT INTO request_items (id, request_id, item_name, category, qty_requested, unit)
    VALUES (gen_random_uuid(), $req_id, $name, $cat, $qty, $unit);
    ```

> **Catatan:** `status_history` belum diisi pada tahap ini. History mulai dicatat saat pertama kali di-submit.

## 2. SUBMITTED

- **Aktor:** Employee
- **Status Transisi:** `DRAFT` ➔ `SUBMITTED`
- **Deskripsi:** Karyawan menekan tombol 'Submit'. Request resmi masuk ke antrian purchasing.

**Tabel yang termodifikasi:**

- **`requests` (UPDATE):** Status berubah ke 'submitted', catat waktu submit, naikkan version.
    ```sql
    UPDATE requests
    SET status = 'submitted',
        submitted_at = NOW(),
        version = version + 1
    WHERE id = $req_id AND version = $current_version;
    ```
- **`status_history` (INSERT):** Catat perpindahan dari 'draft' ke 'submitted'.
    ```sql
    INSERT INTO status_history (id, request_id, changed_by, from_status, to_status)
    VALUES (gen_random_uuid(), $req_id, $user_id, 'draft', 'submitted');
    ```
- **`approvals` (INSERT):** Buat tiket approval step 1 (Purchasing Staff) dengan status pending.
    ```sql
    INSERT INTO approvals (id, request_id, approver_id, step, status)
    VALUES (gen_random_uuid(), $req_id, $purchasing_staff_id, 1, 'pending');
    ```

> **Catatan Optimistic Locking:** Kolom `version` di `requests` dipakai untuk optimistic locking. Jika UPDATE affected rows = 0, artinya ada konflik — return 409 Conflict.

## 3. VERIFIED

- **Aktor:** Purchasing Staff
- **Status Transisi:** `SUBMITTED` ➔ `VERIFIED`
- **Deskripsi:** Purchasing Staff memverifikasi kelengkapan dan keabsahan permintaan.

**Tabel yang termodifikasi:**

- **`approvals` (UPDATE):** Set status = 'approved' pada step 1, catat waktu aksi.
    ```sql
    UPDATE approvals
    SET status = 'approved',
        remarks = $remarks,
        acted_at = NOW()
    WHERE request_id = $req_id AND step = 1 AND status = 'pending';
    ```
- **`requests` (UPDATE):** Status berubah ke 'verified', naikkan version.
    ```sql
    UPDATE requests
    SET status = 'verified', version = version + 1
    WHERE id = $req_id AND version = $current_version;
    ```
- **`status_history` (INSERT):** Catat perpindahan 'submitted' ➔ 'verified'.
    ```sql
    INSERT INTO status_history (id, request_id, changed_by, from_status, to_status)
    VALUES (gen_random_uuid(), $req_id, $staff_id, 'submitted', 'verified');
    ```
- **`approvals` (INSERT):** Buat tiket approval step 2 untuk Purchasing Manager.
    ```sql
    INSERT INTO approvals (id, request_id, approver_id, step, status)
    VALUES (gen_random_uuid(), $req_id, $manager_id, 2, 'pending');
    ```

> **Catatan Database:** Constraint `UNIQUE(request_id, step)` di tabel `approvals` memastikan tidak bisa ada dua approval di step yang sama untuk satu request.

## 4. APPROVED

- **Aktor:** Purchasing Manager
- **Status Transisi:** `VERIFIED` ➔ `APPROVED`
- **Deskripsi:** Manager menyetujui permintaan. Request siap diproses ke warehouse.

**Tabel yang termodifikasi:**

- **`approvals` (UPDATE):** Set status = 'approved' pada step 2.
    ```sql
    UPDATE approvals
    SET status = 'approved', remarks = $remarks, acted_at = NOW()
    WHERE request_id = $req_id AND step = 2 AND status = 'pending';
    ```
- **`requests` (UPDATE):** Status berubah ke 'approved'.
    ```sql
    UPDATE requests
    SET status = 'approved', version = version + 1
    WHERE id = $req_id AND version = $current_version;
    ```
- **`status_history` (INSERT):** Catat perpindahan 'verified' ➔ 'approved'.
    ```sql
    INSERT INTO status_history (id, request_id, changed_by, from_status, to_status)
    VALUES (gen_random_uuid(), $req_id, $manager_id, 'verified', 'approved');
    ```

> **Alur Percabangan:** Dari state ini, alur bercabang. Jika stok tersedia ➔ langsung ke COMPLETED. Jika tidak ➔ masuk IN_PROCUREMENT.

## 5. REJECTED (Alternatif dari VERIFIED)

- **Aktor:** Purchasing Manager
- **Status Transisi:** `VERIFIED` ➔ `REJECTED`
- **Deskripsi:** Manager menolak permintaan. Requester dapat merevisi dan submit ulang.

**Tabel yang termodifikasi:**

- **`approvals` (UPDATE):** Set status = 'rejected' pada step 2, wajib isi remarks.
    ```sql
    UPDATE approvals
    SET status = 'rejected', remarks = $reason, acted_at = NOW()
    WHERE request_id = $req_id AND step = 2 AND status = 'pending';
    ```
- **`requests` (UPDATE):** Status berubah ke 'rejected'.
    ```sql
    UPDATE requests
    SET status = 'rejected', version = version + 1
    WHERE id = $req_id AND version = $current_version;
    ```
- **`status_history` (INSERT):** Catat perpindahan 'verified' ➔ 'rejected'.
    ```sql
    INSERT INTO status_history (id, request_id, changed_by, from_status, to_status, remarks)
    VALUES (gen_random_uuid(), $req_id, $manager_id, 'verified', 'rejected', $reason);
    ```

> **Catatan Revisi:** Requester bisa merevisi request (kembali ke DRAFT) dengan membuat versi baru. Request lama tetap tersimpan dengan status 'rejected'.

## 6. IN PROCUREMENT

- **Aktor:** Purchasing Staff
- **Status Transisi:** `APPROVED` ➔ `IN_PROCUREMENT`
- **Deskripsi:** Stok tidak tersedia. Purchasing membuat Purchase Order (PO) ke vendor.

**Tabel yang termodifikasi:**

- **`stock` (SELECT FOR UPDATE):** Lock baris stok untuk cek ketersediaan (mencegah race condition).
    ```sql
    BEGIN;
    SELECT qty_available FROM stock
    WHERE id = $stock_id FOR UPDATE;
    -- validasi qty_available < qty_requested
    COMMIT;
    ```
- **`procurement_orders` (INSERT):** Buat PO per item ke vendor yang dipilih.
    ```sql
    INSERT INTO procurement_orders (id, request_item_id, vendor_id, created_by, qty_ordered, unit_price, status)
    VALUES (gen_random_uuid(), $item_id, $vendor_id, $staff_id, $qty, $price, 'pending');
    ```
- **`requests` (UPDATE):** Status berubah ke 'in_procurement'.
    ```sql
    UPDATE requests
    SET status = 'in_procurement', version = version + 1
    WHERE id = $req_id AND version = $current_version;
    ```
- **`status_history` (INSERT):** Catat perpindahan 'approved' ➔ 'in_procurement'.
    ```sql
    INSERT INTO status_history (id, request_id, changed_by, from_status, to_status)
    VALUES (gen_random_uuid(), $req_id, $staff_id, 'approved', 'in_procurement');
    ```

> **Catatan Concurrency:** `SELECT FOR UPDATE` memastikan tidak ada dua proses yang mengklaim stok yang sama secara bersamaan (stock race condition prevention).

## 7. COMPLETED

- **Aktor:** Warehouse / System
- **Status Transisi:** `IN_PROCUREMENT` / `APPROVED` ➔ `COMPLETED`
- **Deskripsi:** Barang sudah diterima atau diambil dari stok. Request selesai.

**Tabel yang termodifikasi:**

- **`stock` (UPDATE):** Kurangi qty_available atau qty_reserved sesuai sumber pemenuhan.
    ```sql
    UPDATE stock
    SET qty_available = qty_available - $qty,
        qty_reserved = qty_reserved - $qty
    WHERE id = $stock_id;
    ```
- **`procurement_orders` (UPDATE):** Set status = 'received', catat tanggal terima (jika via PO).
    ```sql
    UPDATE procurement_orders
    SET status = 'received', received_at = NOW()
    WHERE id = $po_id;
    ```
- **`requests` (UPDATE):** Status berubah ke 'completed', catat completed_at.
    ```sql
    UPDATE requests
    SET status = 'completed',
        completed_at = NOW(),
        version = version + 1
    WHERE id = $req_id AND version = $current_version;
    ```
- **`status_history` (INSERT):** Catat perpindahan akhir ke 'completed'.
    ```sql
    INSERT INTO status_history (id, request_id, changed_by, from_status, to_status)
    VALUES (gen_random_uuid(), $req_id, $wh_user_id, 'in_procurement', 'completed');
    ```

> **Catatan Reporting:** `completed_at` - `submitted_at` = lead time aktual. Kolom ini dipakai untuk query reporting average lead time.
