// logger.js
const pino = require('pino');
const pretty = require('pino-pretty'); // Impor pino-pretty langsung

let logger;

if (process.env.NODE_ENV !== 'production') {
    // Konfigurasi untuk pengembangan (menggunakan pino-pretty sebagai stream)
    const stream = pretty({
        colorize: true,
        translateTime: 'SYS:yyyy-mm-dd HH:MM:ss',
        ignore: 'pid,hostname,component' // 'component' bisa Anda tambahkan ke log jika mau, tapi seringnya tidak perlu di pretty print
    });
    logger = pino({
        level: process.env.LOG_LEVEL || 'debug', // Default ke 'debug' di pengembangan
    }, stream);
} else {
    // Konfigurasi untuk produksi (JSON murni)
    logger = pino({
        level: process.env.LOG_LEVEL || 'info', // Default ke 'info' di produksi
        // Opsi produksi lain bisa ditambahkan di sini jika perlu
        // misalnya, format timestamp kustom untuk JSON:
        // timestamp: () => `,"time":"${new Date().toISOString()}"`
    });
}

module.exports = logger;
