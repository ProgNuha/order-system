# Order Management System

[![CI](https://github.com/ProgNuha/order-system/actions/workflows/ci.yml/badge.svg)](https://github.com/ProgNuha/order-system/actions/workflows/ci.yml)

Dikerjakan untuk Technical Test — Senior Fullstack Developer, PT Laksmada Aza Simetri. Track B: Laravel + Vue.

## Tech Stack & Alasan

| Layer | Pilihan | Alasan |
|---|---|---|
| Backend | Laravel 13, PHP 8.3 | Sesuai Track B; kombinasi Eloquent, Form Request, dan Policy sudah mencukupi kebutuhan RBAC dan validasi tanpa boilerplate tambahan |
| Autentikasi | Laravel Sanctum (Bearer token) | Frontend dan backend merupakan dua service terpisah (bukan SPA satu origin), sehingga mode token dipilih dibandingkan mode cookie SPA pada Sanctum. Detail pertimbangan pada ARCHITECTURE.md ADR-3 |
| Basis data | PostgreSQL 16 | Memerlukan perilaku `SELECT ... FOR UPDATE` yang konsisten untuk locking, sebagaimana dianjurkan pada soal; pengujian dijalankan terhadap PostgreSQL asli, bukan SQLite |
| Cache/Queue/Idempotency store | Redis 7 | Satu komponen untuk cache, penyimpanan idempotency key, dan (rencana) antrean job |
| Frontend | Vue 3 + Vite + TypeScript | Lebih ringan dan lebih cepat untuk di-scaffold dibandingkan Nuxt, sesuai dengan cakupan pengujian ini |
| State server | TanStack Vue Query | Melakukan caching serta invalidasi otomatis setelah mutasi (create, pay, ship, complete, cancel order) |
| Form & validasi | vee-validate + zod | Validasi berbasis skema di sisi frontend, selaras dengan validasi di sisi server |
| Styling | Tailwind CSS v4 | — |
| Pengujian frontend | Vitest + Testing Library | Component test untuk form pembuatan order |

## Quick Start

```bash
docker compose up --build
```

Konfigurasi `.env`, pembuatan `APP_KEY`, migrasi basis data, dan seeding dijalankan secara otomatis oleh `backend/docker-entrypoint.sh` saat container `api` pertama kali berjalan — tidak diperlukan langkah manual tambahan.

- API: http://localhost:8000/api
- Dokumentasi OpenAPI (Scramble, dihasilkan otomatis): http://localhost:8000/docs/api
- Frontend: http://localhost:3000
- Mailpit (SMTP lokal): http://localhost:8025

## Akun Login (Hasil Seeder)

| Role | Email | Password |
|---|---|---|
| admin | admin@azatech.id | password |
| cashier | cashier@azatech.id | password |

## Menjalankan Pengujian

Backend (memerlukan PostgreSQL asli, bukan SQLite — lihat ADR-1 pada ARCHITECTURE.md):
```bash
docker compose exec api php artisan test
```
Dua di antara pengujian tersebut (`OrderConcurrencyTest` dan `OrderCancelConcurrencyTest`) mengirimkan permintaan HTTP sungguhan secara paralel ke server yang sedang berjalan (`php artisan serve --no-reload` dengan `PHP_CLI_SERVER_WORKERS` pada `docker-compose.yml`), untuk membuktikan tidak terjadi overselling maupun pengembalian stok ganda pada kondisi konkurensi yang sesungguhnya, bukan simulasi in-process.

Frontend:
```bash
cd frontend && npm test
```

## AI Usage

Claude (melalui Claude Code) digunakan secara ekstensif dalam proyek ini, mencakup:
- Perancangan dan implementasi awal: CRUD produk, pelanggan, dan order; order multi-item; state machine status order; Policy RBAC (admin/cashier); middleware idempotency (`EnsureIdempotentOrderRequest`); serta scaffolding frontend (Vue 3 + Vite + TypeScript + Tailwind CSS + TanStack Query).
- Diagnosis dan perbaikan race condition pada `OrderTransitionService` (`markAsPaid`, `markAsShipped`, `markAsCompleted`, `cancel`) dengan menambahkan `lockForUpdate()` pada baris order di dalam transaction, sehingga pemeriksaan status dilakukan terhadap data yang telah dikunci.
- Penulisan pengujian konkurensi (`OrderCancelConcurrencyTest`) yang mengirimkan sepuluh permintaan cancel secara paralel terhadap order yang sama.
- Diagnosis dan perbaikan pipeline CI (`.github/workflows/ci.yml`): mengatasi PHPStan yang kehabisan batas memori, memperbaiki pengujian konkurensi yang gagal di CI karena belum tersedia server HTTP sebelum `php artisan test` dijalankan (dengan menambahkan tahap start server beserta `PHP_CLI_SERVER_WORKERS`), serta menambahkan job `frontend` (type-check, test, build) yang sebelumnya belum ada.
- Pembaruan `backend/Dockerfile` menjadi multi-stage dengan pengguna non-root, pembuatan `docker-entrypoint.sh` untuk migrasi dan seeding basis data secara otomatis (idempotent, hanya dijalankan satu kali saat basis data kosong) termasuk inisialisasi `.env` dan `APP_KEY` secara otomatis, serta pemindahan konfigurasi koneksi Docker ke bagian `environment` pada `docker-compose.yml` agar `docker compose up` dapat berjalan dari clone baru tanpa konfigurasi manual.
- Penyusunan dokumen ini (`README.md` dan `ARCHITECTURE.md`, termasuk ERD dan tiga Architecture Decision Record).

Untuk bagian backend (keahlian utama kandidat), kode dari AI selalu ditinjau dan dipahami terlebih dahulu sebelum digunakan. Untuk bagian frontend, kandidat belum terbiasa menggunakan Vue, sehingga sebagian besar kode frontend lebih bergantung pada AI dan pemahaman mendalam terhadap detail implementasinya masih terbatas; hal ini akan dipelajari lebih lanjut sebelum sesi review teknis.

## Hal yang Belum Diselesaikan

Disusun berdasarkan prioritas:

- **Background job invoice dan notifikasi email (poin 4.7)** — saat ini baru ada `markAsPaid()`; job class dan mailable belum dibuat. Rencana implementasi: queued job `SendPaidOrderInvoiceJob`, bersifat idempotent melalui kolom `invoice_sent_at` pada tabel `orders`, dengan `$tries = 3` dan strategi backoff.
- **Notifikasi low-stock kepada admin (poin 4.7)** — belum diimplementasikan, menunggu penambahan kolom `low_stock_threshold`.
- **Kolom `low_stock_threshold` pada tabel `products`** — belum ditambahkan ke migration.
- **Endpoint `stock-adjustments`, `stock-movements` per produk, `orders/{id}/invoice`, `dashboard/summary`, `reports/sales`, dan `reports/top-products`** — belum diimplementasikan.
- **`GET /api/health`** — belum tersedia secara eksplisit; saat ini masih menggunakan endpoint bawaan Laravel `/up`, yang hanya memeriksa koneksi database dan belum memeriksa Redis secara eksplisit.
- **Konfigurasi CORS eksplisit** — belum dikonfigurasi, masih menggunakan pengaturan default Laravel. Perlu dibatasi ke origin frontend sebelum dianggap selesai (bukan `*`).
- **Rate limiting pada pembuatan order** — saat ini baru diterapkan pada login, belum pada `POST /orders`.
- **ESLint pada frontend** — belum dikonfigurasi.
- **E2E test dengan Playwright** — tidak dikerjakan (bersifat bonus).

Pendekatan lanjutan: kolom `low_stock_threshold` dan job invoice diprioritaskan lebih dahulu karena berkaitan langsung dengan requirement wajib pada poin 4.2, diikuti oleh endpoint laporan dan dashboard.