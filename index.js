const express = require('express');
const { getX2TwitterDownloadLinks } = require('./getX2TwitterData');
const logger = require('./logger');
const NodeCache = require('node-cache');

const app = express();
const PORT = process.env.PORT || 3000;
const API_AUTHOR = "Feri";

// Inisialisasi Cache
// TTL (Time To Live) default 1 jam (3600 detik), checkperiod 5 menit (300 detik)
// Sesuaikan TTL sesuai kebutuhan Anda. Jika link uguu.se/snapcdn jarang berubah, TTL bisa lebih lama.
const appCache = new NodeCache({ stdTTL: 3600, checkperiod: 300 });

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

const formatResponse = (res, httpStatusCode, success, message, data = null, source = "api") => {
    return res.status(httpStatusCode).json({
        success: success,
        status_code: httpStatusCode,
        message: message,
        author: API_AUTHOR,
        source: source, // 'api' atau 'cache'
        data: data
    });
};

app.get('/api/tweetinfo', async (req, res) => {
    const tweetUrl = req.query.url;

    if (!tweetUrl) {
        return formatResponse(res, 400, false, 'Parameter "url" dibutuhkan.');
    }
    if (!tweetUrl.match(/^(https?:\/\/)?(www\.)?(twitter\.com|x\.com)\/\w+\/status\/\d+/i)) {
        return formatResponse(res, 400, false, 'Format URL Twitter tidak valid.');
    }

    const cacheKey = `tweetinfo:${tweetUrl}`; // Buat kunci cache yang unik

    // 1. Coba ambil dari cache terlebih dahulu
    const cachedData = appCache.get(cacheKey);
    if (cachedData) {
        logger.info({ url: tweetUrl, cacheKey, phase: 'cache_hit' }, "[API] Data ditemukan di cache.");
        // Asumsi cachedData disimpan dalam format yang sama dengan respons sukses kita
        return formatResponse(res, cachedData.status_code || 200, cachedData.success, cachedData.message, cachedData.data, "cache");
    }

    logger.info({ url: tweetUrl, ip: req.ip, phase: 'api_request_received_cache_miss' }, `[API] Cache miss. Menerima permintaan.`);

    try {
        const result = await getX2TwitterDownloadLinks(tweetUrl);

        if (result._internal_success) {
            const responsePayload = {
                success: true,
                status_code: 200,
                message: result.message || "Sukses mengambil data tweet.",
                data: result.data
            };
            // Simpan hasil sukses ke cache
            appCache.set(cacheKey, responsePayload); // TTL default akan digunakan
            logger.info({ url: tweetUrl, cacheKey, phase: 'cache_set' }, "[API] Data disimpan ke cache.");
            formatResponse(res, 200, true, responsePayload.message, responsePayload.data);
        } else {
            let httpStatusCode = 500;
            const errorMessage = result.message || "Terjadi kesalahan internal.";
            if (result._internal_statusCode) httpStatusCode = result._internal_statusCode === 200 ? 404 : result._internal_statusCode;
            else if (errorMessage.toLowerCase().includes("not found") || errorMessage.toLowerCase().includes("private or blocked")) httpStatusCode = 404;
            else if (errorMessage.toLowerCase().includes("token") || errorMessage.toLowerCase().includes("koneksi ke ep1")) httpStatusCode = 502;
            else if (errorMessage.toLowerCase().includes("koneksi ke ep2")) httpStatusCode = 503;

            logger.error({ url: tweetUrl, error: errorMessage, statusCode: httpStatusCode, phase: 'api_process_error' }, `[API] Error memproses`);
            // Jangan cache hasil error, atau cache dengan TTL sangat pendek jika perlu
            formatResponse(res, httpStatusCode, false, errorMessage, result.data);
        }
    } catch (error) {
        logger.fatal({ url: tweetUrl, error: error.message, stack: error.stack, phase: 'api_unhandled_error' }, '[API] Kesalahan Internal Server fatal');
        formatResponse(res, 500, false, 'Kesalahan fatal pada server API.');
    }
});

app.get('/', (req, res) => {
    res.status(200).json({
        success: true, status_code: 200, message: 'API Downloader Twitter Sederhana!', author: API_AUTHOR,
        data: { endpoints: [{ path: "/api/tweetinfo?url=<twitter_url>", description: "Info unduhan dari URL tweet." }] }
    });
});

// Endpoint untuk membersihkan cache (opsional, mungkin perlu proteksi)
app.get('/api/clearcache', (req, res) => {
    // Tambahkan mekanisme autentikasi di sini jika ini endpoint publik
    const stats = appCache.getStats();
    appCache.flushAll();
    logger.warn({ phase: 'cache_cleared', oldStats: stats }, "Semua cache telah dibersihkan.");
    res.json({ success: true, message: "Cache dibersihkan.", old_keys_count: stats.keys });
});


app.listen(PORT, () => {
    logger.info(`Server API berjalan di http://localhost:${PORT}`);
    logger.info(`Dibuat oleh: ${API_AUTHOR}`);
});
