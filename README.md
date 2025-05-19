Tentu saja! Membuat dokumentasi API, meskipun singkat, sangat penting bahkan untuk proyek pribadi agar mudah diingat cara penggunaannya di masa depan atau jika Anda ingin membaginya dengan orang lain.

Berikut adalah contoh dokumentasi API singkat untuk layanan yang telah kita bangun:

---

## Dokumentasi API: Twitter Media Downloader (Unofficial)

**Author:** XTLY
**Versi API:** 1.0.0
**URL Basis (Contoh dari Replit):** `https://<ID_REPLIT_ANDA>.replit.dev/`
*(Ganti `<ID_REPLIT_ANDA>` dengan URL publik Replit Anda)*

### Deskripsi Umum

API ini menyediakan fungsionalitas untuk mendapatkan link unduhan media (video, GIF, gambar) dari URL tweet tertentu. API ini berinteraksi dengan layanan eksternal (x2twitter.com) untuk mengambil informasi dan kemudian secara opsional mengunggah file ke host sementara (uguu.se) untuk menyediakan link yang lebih stabil dan bersih.

### Konfigurasi Logging (Server-Side)

Logging di sisi server menggunakan `pino`. Level logging dan format output dapat dikontrol melalui variabel environment berikut (biasanya diatur di Replit Secrets):

1.  **`NODE_ENV`**:
    *   **Nilai:** `production` atau `development` (atau biarkan kosong untuk default ke mode pengembangan).
    *   **Efek:**
        *   Jika `production`: Log akan dihasilkan dalam format JSON murni, yang efisien untuk dikumpulkan dan dianalisis oleh sistem logging.
        *   Jika `development` (atau kosong): Log akan diformat menggunakan `pino-pretty` untuk keterbacaan yang lebih baik di konsol selama pengembangan.
    *   **Contoh:** `NODE_ENV=production`

2.  **`LOG_LEVEL`**:
    *   **Nilai:** `fatal`, `error`, `warn`, `info`, `debug`, `trace`.
    *   **Efek:** Menentukan tingkat detail minimum log yang akan dicatat. Log dengan level yang sama atau lebih tinggi dari yang disetel akan ditampilkan.
        *   `info`: (Default jika `NODE_ENV=production`) Cocok untuk produksi normal, menampilkan operasi umum dan error.
        *   `debug`: (Default jika `NODE_ENV!=production`) Sangat detail, berguna untuk troubleshooting mendalam.
        *   `warn`: Hanya menampilkan peringatan dan error.
    *   **Contoh:** `LOG_LEVEL=info`

### Endpoint API

#### 1. Root Endpoint

*   **Metode:** `GET`
*   **URL:** `/`
*   **Deskripsi:** Menyediakan pesan selamat datang dan informasi dasar tentang API.
*   **Parameter Query:** Tidak ada.
*   **Respons Sukses (200 OK):**
    ```json
    {
        "success": true,
        "status_code": 200,
        "message": "Selamat datang di API Downloader Twitter Sederhana!",
        "author": "XTLY",
        "data": {
            "endpoints": [
                {
                    "path": "/api/tweetinfo?url=<twitter_url>",
                    "description": "Mendapatkan informasi unduhan dari URL tweet."
                }
            ]
        }
    }
    ```

#### 2. Get Tweet Info & Download Links

*   **Metode:** `GET`
*   **URL:** `/api/tweetinfo`
*   **Deskripsi:** Mengambil informasi media (judul, durasi jika ada) dan link unduhan dari URL tweet yang diberikan. Link unduhan akan diarahkan melalui `uguu.se` jika memungkinkan (file < 128MB), jika tidak akan menggunakan link asli dari `snapcdn.app`.
*   **Parameter Query:**
    *   `url` (wajib): String, URL lengkap dari tweet yang ingin diproses.
        *   Contoh: `https://x.com/SpaceX/status/1787538497902276780`

*   **Contoh Permintaan:**
    `GET https://<ID_REPLIT_ANDA>.replit.dev/api/tweetinfo?url=https://x.com/SpaceX/status/1787538497902276780`

*   **Respons Sukses (200 OK):**
    ```json
    {
        "success": true,
        "status_code": 200,
        "message": "Sukses mengambil data tweet.", // atau pesan sukses lainnya
        "author": "XTLY",
        "source": "api", // atau "cache" jika data diambil dari cache
        "data": {
            "title": "Judul Tweet (jika video/GIF dan ditemukan)", // bisa null
            "duration": "Durasi Video/GIF (jika ada dan ditemukan)", // bisa null
            "links": [
                {
                    "text": "Deskripsi link (mis. Unduh MP4 (1280p))",
                    "url": "https://URL_UGUU_SE_ATAU_SNAPCDN/...", // URL unduhan
                    "source": "uguu.se" // atau "snapcdn"
                },
                // ... objek link lainnya
            ]
        }
    }
    ```
    *   Jika `data.links` kosong, berarti tidak ada media yang bisa diunduh atau tidak ditemukan.

*   **Respons Gagal:**
    *   **400 Bad Request (Parameter Tidak Valid):**
        ```json
        {
            "success": false,
            "status_code": 400,
            "message": "Parameter \"url\" dibutuhkan.", // atau "Format URL Twitter tidak valid."
            "author": "XTLY",
            "source": "api",
            "data": null
        }
        ```
    *   **404 Not Found (Tweet Tidak Ditemukan/Private):**
        ```json
        {
            "success": false,
            "status_code": 404,
            "message": "Video not found. Maybe the video is private or blocked.", // atau pesan error serupa dari layanan eksternal
            "author": "XTLY",
            "source": "api",
            "data": null // atau bisa berisi data parsial jika ada, seperti { title: null, duration: null, links: [] }
        }
        ```
    *   **500 Internal Server Error / 502 Bad Gateway / 503 Service Unavailable (Masalah Server):**
        Akan ada pesan error yang relevan di field `message`.
        ```json
        {
            "success": false,
            "status_code": 50X,
            "message": "Pesan error spesifik (mis. Gagal mendapatkan token: ... atau Error koneksi ke EP1: ...)",
            "author": "XTLY",
            "source": "api",
            "data": null
        }
        ```

#### 3. Clear Cache (Opsional & Perlu Proteksi)

*   **Metode:** `GET`
*   **URL:** `/api/clearcache`
*   **Deskripsi:** Menghapus semua data yang tersimpan di cache server API. **PERHATIAN:** Endpoint ini sebaiknya diproteksi jika API dapat diakses oleh pihak lain.
*   **Parameter Query:** Tidak ada.
*   **Respons Sukses (200 OK):**
    ```json
    {
        "success": true,
        "message": "Cache dibersihkan.",
        "old_keys_count": 0 // Jumlah kunci yang ada sebelum dibersihkan
    }
    ```

### Catatan Tambahan

*   **Caching:** API ini mengimplementasikan caching in-memory untuk respons yang berhasil. Permintaan berikutnya untuk URL tweet yang sama dalam periode TTL cache (default 1 jam) akan dilayani dari cache, ditandai dengan `source: "cache"` pada respons.
*   **Ketergantungan:** Kinerja dan ketersediaan API ini bergantung pada layanan eksternal x2twitter.com dan uguu.se.
*   **Batas Ukuran Uguu.se:** File di atas 128MB (batas yang dikonfigurasi) tidak akan diunggah ke uguu.se dan akan menggunakan link asli dari snapcdn.app.

---
