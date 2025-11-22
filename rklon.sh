#!/bin/bash

# =================================================================================
# Skrip Instalasi Rclone Otomatis (Versi Stabil)
# Menggabungkan semua pelajaran dari proses troubleshooting.
# =================================================================================

# --- Bagian 1: Pengaturan Awal (Input dari Pengguna & Info Sistem) ---

echo "--- Script Otomatisasi Rclone & Streaming (Versi Stabil) ---"
read -p "Masukkan nama subdomain (contoh: xdn.videy.my.id): " SUBDOMAIN
read -p "Masukkan nama remote rclone yang Anda inginkan (contoh: gdrive): " RCLONE_REMOTE_NAME
read -p "Masukkan lokasi direktori di Google Drive (kosongkan jika root): " DRIVE_ROOT_FOLDER
read -p "Masukkan batas ukuran cache (contoh: 5G, 10G): " CACHE_SIZE

echo "Mendeteksi UID dan GID untuk pengguna 'www'..."
WWW_UID=$(id -u www)
WWW_GID=$(id -g www)
echo "Ditemukan: UID=${WWW_UID}, GID=${WWW_GID}"


# --- Bagian 2: Instalasi Rclone ---

echo "Mengunduh dan menginstal Rclone versi terbaru..."
curl https://rclone.org/install.sh | sudo bash
sudo apt update -y > /dev/null 2>&1


# --- Bagian 3: Konfigurasi Rclone (Manual) ---

echo "--- Perhatian: Konfigurasi Rclone (Manual) ---"
echo "Anda akan masuk ke konfigurasi rclone. Ikuti panduan ini:"
echo "1. 'n' (New remote), Nama remote: ${RCLONE_REMOTE_NAME}"
echo "2. Pilih 'drive' (Google Drive), biarkan client_id & secret kosong."
echo "3. '1' (Full access), biarkan root_folder_id & service_account_file kosong."
echo "4. 'n' (No, do not use auto config)."
echo "5. Salin URL, autentikasi di browser, lalu tempel kode verifikasi."
echo "6. Konfigurasi sebagai Team Drive? (biasanya 'n')."
echo "7. 'y' (Yes, this is OK), lalu 'q' (Quit)."
echo "--------------------------------------------------------"
read -p "Tekan Enter untuk memulai 'rclone config'..."

rclone config

read -p "Konfigurasi rclone selesai? Tekan Enter untuk melanjutkan..."


# --- Bagian 4: Konfigurasi Sistem & Izin (KUNCI UTAMA) ---

echo "Menyiapkan direktori dan izin sistem..."

# BARU: Mengonfigurasi fuse.conf secara otomatis
# Mengecek apakah 'user_allow_other' sudah ada, jika belum, tambahkan.
if ! grep -q "^user_allow_other" /etc/fuse.conf; then
    echo "user_allow_other" | sudo tee -a /etc/fuse.conf
    echo "fuse.conf telah diperbarui."
fi

# Membuat semua direktori yang dibutuhkan
sudo mkdir -p /mnt/gdrive /opt/rclone_cache /etc/rclone

# Memindahkan file config dan mengatur izin yang benar untuk update token
echo "Memindahkan file konfigurasi dan mengatur izin..."
sudo mv /root/.config/rclone/rclone.conf /etc/rclone/rclone.conf
sudo chown root:www /etc/rclone/rclone.conf
sudo chmod 664 /etc/rclone/rclone.conf

# Mengatur kepemilikan direktori mount dan cache
sudo chown www:www /mnt/gdrive
sudo chown www:www /opt/rclone_cache
# Mengatur kepemilikan web root
sudo chown -R www:www /www/wwwroot/$SUBDOMAIN


# --- Bagian 5: Buat Layanan Systemd yang Telah Diperbaiki ---

echo "Membuat layanan systemd yang stabil..."
sudo bash -c "cat > /etc/systemd/system/rclone.service <<EOL
[Unit]
Description=Rclone Mount Service for ${RCLONE_REMOTE_NAME}
After=network-online.target

[Service]
Type=notify
ExecStart=/usr/bin/rclone mount ${RCLONE_REMOTE_NAME}:${DRIVE_ROOT_FOLDER} /mnt/gdrive \\
    --config /etc/rclone/rclone.conf \\
    --vfs-cache-mode full \\
    --cache-dir /opt/rclone_cache \\
    --vfs-cache-max-size ${CACHE_SIZE} \\
    --vfs-cache-max-age 72h \\
    --allow-other \\
    --uid ${WWW_UID} \\
    --gid ${WWW_GID} \\
    --rc \\
    --rc-addr :5572
ExecStop=/bin/fusermount -u /mnt/gdrive
Restart=always
RestartSec=10
User=www
Group=www

[Install]
WantedBy=multi-user.target
EOL"

echo "Reload daemon dan memulai layanan rclone..."
sudo systemctl daemon-reload
sudo systemctl enable rclone.service
sudo systemctl restart rclone.service

echo ""
echo "--- Instalasi Selesai! ---"
echo "Proses seharusnya sudah berjalan dengan baik. Cek status dengan:"
echo "sudo systemctl status rclone.service"
echo "Dan cek isi mount (mungkin perlu beberapa detik untuk muncul):"
echo "ls -l /mnt/gdrive"
