const axios = require('axios');
const cheerio = require('cheerio');
const { URLSearchParams } = require('url');
const FormData = require('form-data');
const logger = require('./logger'); // Impor logger pino Anda

// --- KONFIGURASI DASAR (SAMA) ---
const EP1_URL = "https://x2twitter.com/api/userverify";
const EP1_HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
    'Accept': '*/*', 'Accept-Language': 'id,en-US;q=0.9,en;q=0.8',
    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
    'Origin': 'https://x2twitter.com', 'Referer': 'https://x2twitter.com/id',
    'X-Requested-With': 'XMLHttpRequest', 'Sec-CH-UA': '"Google Chrome";v="135", "Not-A.Brand";v="8", "Chromium";v="135"',
    'Sec-CH-UA-Mobile': '?0', 'Sec-CH-UA-Platform': '"Windows"', 'Sec-Fetch-Dest': 'empty',
    'Sec-Fetch-Mode': 'cors', 'Sec-Fetch-Site': 'same-origin'
};
const EP2_URL = "https://x2twitter.com/api/ajaxSearch";
const defaultTimeout = 25000;
const UGUU_UPLOAD_URL = "https://uguu.se/upload";
const MAX_UPLOAD_SIZE_MB = 128;
const MAX_UPLOAD_SIZE_BYTES = MAX_UPLOAD_SIZE_MB * 1024 * 1024;

function getExtensionFromMimeType(mimeType) {
    if (!mimeType) return 'tmp';
    const mimeMap = { /* ... mimeMap sama ... */
        'video/mp4': 'mp4', 'video/webm': 'webm', 'video/quicktime': 'mov',
        'image/jpeg': 'jpg', 'image/png': 'png', 'image/gif': 'gif',
        'audio/mpeg': 'mp3', 'audio/wav': 'wav',
    };
    return mimeMap[mimeType.toLowerCase()] || 'tmp';
}

async function uploadToUguuIfPossible(snapcdnUrl, originalLinkText) {
    let dynamicFilename = "downloaded_media.tmp";
    const logContext = { snapcdnUrl: snapcdnUrl.substring(0,70) + "...", originalLinkText, phase: 'uguu_upload' };

    try {
        logger.debug(logContext, "Memulai proses unggah ke Uguu.se");

        const fileResponse = await axios.get(snapcdnUrl, {
            responseType: 'arraybuffer',
            timeout: defaultTimeout
        });
        logger.debug({ ...logContext, status: fileResponse.status, phase_detail: 'snapcdn_download_complete' }, "Selesai mengunduh dari SnapCDN");

        const fileBuffer = Buffer.from(fileResponse.data, 'binary');
        const fileSize = fileBuffer.length;

        const contentType = fileResponse.headers['content-type'];
        const extension = getExtensionFromMimeType(contentType);
        const sanitizedText = originalLinkText.replace(/[^a-zA-Z0-9\s]/g, "").replace(/\s+/g, "_");
        dynamicFilename = `${sanitizedText}_${Date.now()}.${extension}`;
        logContext.dynamicFilename = dynamicFilename; // Update context untuk logging

        logger.info({ ...logContext, fileSizeMB: (fileSize / (1024*1024)).toFixed(2), contentType, extension }, "File diunduh dan info didapatkan");

        if (fileSize > MAX_UPLOAD_SIZE_BYTES) {
            logger.warn({ ...logContext, fileSizeMB: (fileSize / (1024*1024)).toFixed(2), maxSizeMB: MAX_UPLOAD_SIZE_MB }, "File terlalu besar, menggunakan URL SnapCDN asli.");
            return snapcdnUrl;
        }

        const formData = new FormData();
        formData.append('files[]', fileBuffer, { filename: dynamicFilename });

        logger.info({ ...logContext, fileSizeMB: (fileSize / (1024*1024)).toFixed(2), uploadUrl: UGUU_UPLOAD_URL }, `Mengunggah ${dynamicFilename} ke Uguu.se...`);
        const uguuResponse = await axios.post(UGUU_UPLOAD_URL, formData, {
            headers: { ...formData.getHeaders() },
            timeout: defaultTimeout
        });

        if (uguuResponse.data && uguuResponse.data.success && uguuResponse.data.files && uguuResponse.data.files.length > 0) {
            const uguuFileUrl = uguuResponse.data.files[0].url;
            const uguuFilename = uguuResponse.data.files[0].filename;
            logger.info({ ...logContext, uguuUrl: uguuFileUrl, uguuFilename }, "Berhasil diunggah ke Uguu.se");
            return uguuFileUrl;
        } else {
            logger.error({ ...logContext, responseData: uguuResponse.data, phase_detail: 'uguu_upload_failed_unexpected_response' }, "Gagal mengunggah ke Uguu.se atau format respons tidak dikenali.");
            return snapcdnUrl;
        }
    } catch (error) {
        logContext.errorMessage = error.message;
        if (error.response && error.response.status === 413) {
            logger.warn({ ...logContext, status: 413 }, `Gagal mengunggah: 413 Request Entity Too Large untuk ${dynamicFilename}. Menggunakan URL SnapCDN asli.`);
        } else if (error.code === 'ECONNABORTED' || (error.message && error.message.toLowerCase().includes('timeout'))) {
            logger.warn({ ...logContext, code: error.code }, `Proses Uguu timeout. Menggunakan URL SnapCDN asli.`);
        } else {
            logger.error({ ...logContext, stack: error.stack }, `Error tak terduga saat proses Uguu.`);
        }
        return snapcdnUrl;
    }
}

async function getX2TwitterDownloadLinks(tweetUrl) {
    let cftoken = null;
    let title = null;
    let duration = null;
    const mainLogContext = { tweetUrl, component: 'getX2TwitterDownloadLinks' };

    const ep1RequestConfig = { headers: EP1_HEADERS, timeout: defaultTimeout };
    const ep2RequestConfig = { headers: EP1_HEADERS, timeout: defaultTimeout };

    try {
        logger.debug({ ...mainLogContext, phase: 'ep1_request_start' }, "Meminta token EP1");
        const ep1Payload = new URLSearchParams(); ep1Payload.append('url', tweetUrl);
        const responseEp1 = await axios.post(EP1_URL, ep1Payload.toString(), ep1RequestConfig);

        if (responseEp1.data && responseEp1.data.success) {
            cftoken = responseEp1.data.token;
            logger.debug({ ...mainLogContext, phase: 'ep1_request_success', tokenReceived: !!cftoken }, "Token EP1 diterima.");
        } else {
            const errMsg = responseEp1.data ? responseEp1.data.message : 'Tidak ada pesan error dari EP1';
            logger.warn({ ...mainLogContext, phase: 'ep1_request_failed_logic', errorMessage: errMsg, responseData: responseEp1.data }, "Gagal mendapatkan token EP1 (logic).");
            return { _internal_success: false, message: `Token: ${errMsg}`, data: null };
        }
    } catch (error) {
        logger.error({ ...mainLogContext, phase: 'ep1_request_failed_connection', errorMessage: error.message, errorCode: error.code, stack: error.stack }, "Error koneksi ke EP1.");
        return { _internal_success: false, message: `EP1 Conn: ${error.code || error.message}`, data: null };
    }

    if (!cftoken) {
        logger.error({ ...mainLogContext, phase: 'ep1_no_token_critical' }, "Kondisi kritis: Token tidak didapatkan dari EP1.");
        return { _internal_success: false, message: "No token (internal)", data: null };
    }

    try {
        logger.debug({ ...mainLogContext, phase: 'ep2_request_start' }, "Meminta data unduhan EP2");
        const ep2Payload = new URLSearchParams(); ep2Payload.append('q', tweetUrl); ep2Payload.append('lang', 'id'); ep2Payload.append('cftoken', cftoken);
        const responseEp2 = await axios.post(EP2_URL, ep2Payload.toString(), ep2RequestConfig);

        logger.debug({ ...mainLogContext, phase: 'ep2_request_complete', status: responseEp2.status }, "Permintaan EP2 selesai.");

        if (responseEp2.data && responseEp2.data.status === "ok") {
            const htmlData = responseEp2.data.data;
            const msgFromServer = responseEp2.data.msg;

            if (msgFromServer && (msgFromServer.toLowerCase().includes("invalid") || msgFromServer.toLowerCase().includes("expired") || msgFromServer.toLowerCase().includes("token") || responseEp2.data.statusCode === 404)) {
                logger.warn({ ...mainLogContext, phase: 'ep2_server_error_message', serverMessage: msgFromServer, statusCode: responseEp2.data.statusCode }, "Pesan error dari server EP2.");
                return { _internal_success: false, message: msgFromServer, data: null, _internal_statusCode: responseEp2.data.statusCode };
            }

            if (htmlData) {
                logger.debug({ ...mainLogContext, phase: 'ep2_parsing_html' }, "Memulai parsing HTML dari EP2.");
                const $ = cheerio.load(htmlData);
                // ... (logika ekstraksi title, duration, links sama seperti sebelumnya)
                const clearfixDiv = $('div.clearfix');
                if (clearfixDiv.length > 0) { const h3T = clearfixDiv.first().find('h3'); const pD = clearfixDiv.first().find('p'); if (h3T.length > 0) title = h3T.text().trim(); if (pD.length > 0) { const pDT = pD.text().trim(); if (pDT.match(/^\d{1,2}:\d{2}(:\d{2})?$/)) duration = pDT; else if (pD.next().is('p') && pD.next().text().trim().match(/^\d{1,2}:\d{2}(:\d{2})?$/)) duration = pD.next().text().trim();}}
                if (!title) { const vcT = $('.tw-video .content h3'); if (vcT.length > 0) title = vcT.first().text().trim(); }
                if (!duration) { $('.tw-video .content p').each((i, el) => { const pTx = $(el).text().trim(); if (pTx.match(/^\d{1,2}:\d{2}(:\d{2})?$/)) { duration = pTx; return false; } }); }

                let initialDownloadLinks = [];
                let linkElements = $('div.dl-action a.tw-button-dl[href*="dl.snapcdn.app"], div.dl-action a.button[href*="dl.snapcdn.app"]');
                if (linkElements.length === 0) linkElements = $('div.photo-list div.download-items__btn a.abutton[href*="dl.snapcdn.app"]');
                if (linkElements.length === 0) linkElements = $('a[href*="dl.snapcdn.app/get?token="]');
                linkElements.each((i, el) => { const lT = $(el); const h = lT.attr('href'); let t = lT.text().trim(); if (!t && lT.find('span > span').length > 0) t = lT.find('span > span').text().trim(); else if (!t) t = lT.attr('title') || "Tautan Unduhan"; if (h && h !== '#') initialDownloadLinks.push({ text: t, url: h, original_url: h }); });

                logger.info({ ...mainLogContext, phase: 'ep2_links_extracted', count: initialDownloadLinks.length }, `Ditemukan ${initialDownloadLinks.length} link awal.`);

                if (initialDownloadLinks.length > 0) {
                    const processedLinks = [];
                    for (const linkObj of initialDownloadLinks) {
                        if (linkObj.url && linkObj.url.includes("dl.snapcdn.app")) {
                            const newUrl = await uploadToUguuIfPossible(linkObj.url, linkObj.text);
                            processedLinks.push({
                                text: linkObj.text,
                                url: newUrl,
                                source: newUrl === linkObj.url ? "snapcdn" : "uguu.se"
                            });
                        } else {
                            logger.debug({ ...mainLogContext, phase: 'ep2_skipping_non_snapcdn_link', url: linkObj.url }, "Melewati link non-snapcdn.");
                            processedLinks.push(linkObj);
                        }
                    }
                    const resultData = { title, duration, links: processedLinks };
                    logger.info({ ...mainLogContext, phase: 'processing_complete_success', linkCount: processedLinks.length }, "Proses selesai dengan sukses.");
                    return { _internal_success: true, data: resultData, message: "Sukses mengambil data tweet." };
                } else {
                    logger.warn({ ...mainLogContext, phase: 'ep2_no_download_links_found', serverMessage: msgFromServer }, "Tidak ada link download ditemukan setelah parsing.");
                    return { _internal_success: false, message: msgFromServer || "Tidak ada link download ditemukan.", data: { title, duration, links: [] } };
                }
            } else {
                logger.warn({ ...mainLogContext, phase: 'ep2_html_data_empty', serverMessage: msgFromServer }, "Data HTML kosong dari EP2.");
                return { _internal_success: false, message: msgFromServer || "Data HTML kosong", data: null, _internal_statusCode: responseEp2.data.statusCode };
            }
        } else if (responseEp2.data) {
            const errorMsg = responseEp2.data.msg || responseEp2.data.message || JSON.stringify(responseEp2.data);
            logger.warn({ ...mainLogContext, phase: 'ep2_request_failed_server_logic', errorMessage: errorMsg, responseData: responseEp2.data }, "Gagal mendapatkan data dari EP2 (logic server).");
            return { _internal_success: false, message: `EP2 Err: ${errorMsg}`, data: null, _internal_statusCode: responseEp2.data.statusCode };
        } else {
            logger.error({ ...mainLogContext, phase: 'ep2_request_failed_no_data' }, "Tidak ada data dalam respons dari EP2.");
            return { _internal_success: false, message: "EP2 No data resp", data: null };
        }
    } catch (error) {
        logger.error({ ...mainLogContext, phase: 'ep2_request_failed_connection', errorMessage: error.message, errorCode: error.code, stack: error.stack }, "Error koneksi ke EP2.");
        return { _internal_success: false, message: `EP2 Conn: ${error.code || error.message}`, data: null };
    }
}

module.exports = { getX2TwitterDownloadLinks };
