import os
import re
import shutil
import zipfile
from collections import OrderedDict
from concurrent.futures import ThreadPoolExecutor

import gradio as gr
import yt_dlp

# Fungsi helper untuk mengelola cookie dari Secret atau file upload
def get_cookie_path(uploaded_file_path):
    """
    Memprioritaskan cookie dari Hugging Face Secret.
    Jika ada, tulis ke file sementara dan kembalikan path-nya.
    Jika tidak, gunakan path dari file yang diunggah.
    """
    cookie_secret_data = os.getenv("YOUTUBE_COOKIE_DATA")
    if cookie_secret_data:
        temp_cookie_filename = "cookies_from_secret.txt"
        with open(temp_cookie_filename, "w", encoding="utf-8") as f:
            f.write(cookie_secret_data)
        return temp_cookie_filename
    return uploaded_file_path

def check_resolutions(urls, cookies_file=None):
    if not urls:
        return "⚠️ Masukkan URL video terlebih dahulu."
    url_list = [u.strip() for u in re.split(r'[\n, ]+', urls) if u.strip()]
    if not url_list:
        return "⚠️ URL tidak valid."
    url = url_list[0]
    try:
        ydl_opts = {'quiet': True, 'no_warnings': True, 'cachedir': False}
        if cookies_file:
            ydl_opts['cookiefile'] = cookies_file
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            info = ydl.extract_info(url, download=False)
            formats = info.get('formats', [])
            
            # PERBAIKAN: Menyimpan resolusi sebagai pasangan (lebar, tinggi)
            resolutions = set()
            for f in formats:
                # Memastikan format memiliki resolusi dan merupakan video
                if f.get('height') and f.get('width') and f.get('vcodec') != 'none':
                    resolutions.add((f['width'], f['height']))
            
            if not resolutions:
                return "ℹ️ Tidak ada format video terpisah yang ditemukan."
            
            # Mengurutkan berdasarkan tinggi (descending), lalu lebar (descending)
            sorted_resolutions = sorted(list(resolutions), key=lambda x: (x[1], x[0]), reverse=True)
            
            # Menampilkan output yang lebih jelas
            output_str = "✅ **Resolusi yang Tersedia:**\n\n"
            for w, h in sorted_resolutions:
                # Menambahkan penanda untuk video vertikal
                orientation = " (Vertikal)" if h > w else ""
                output_str += f"- {w}x{h}p{orientation}\n"
                
            return output_str
            
    except Exception as e:
        return f"⛔ Gagal memeriksa resolusi: {str(e)}"

def download_videos(urls, format_type, video_quality, audio_quality, parallel_dl, zip_files, cookies_file=None):
    output_folder = 'FYT_DOWN'
    os.makedirs(output_folder, exist_ok=True)
    
    # Membersihkan folder output (kode asli dipertahankan)
    for f in os.listdir(output_folder):
        file_path = os.path.join(output_folder, f)
        try:
            if os.path.isfile(file_path): os.unlink(file_path)
            elif os.path.isdir(file_path): shutil.rmtree(file_path)
        except Exception as e:
            print(f"Gagal menghapus {file_path}: {e}")

    format_config = {
        'MP4': {'format': f'bestvideo[height<={video_quality}][vcodec^=avc1][ext=mp4]+bestaudio[ext=m4a]/bestvideo[vcodec^=avc1]+bestaudio/best', 'merge_output_format': 'mp4'},
        'MP3': {'format': 'bestaudio/best', 'postprocessors': [{'key': 'FFmpegExtractAudio', 'preferredcodec': 'mp3', 'preferredquality': audio_quality}]},
        'WAV': {'format': 'bestaudio/best', 'postprocessors': [{'key': 'FFmpegExtractAudio', 'preferredcodec': 'wav'}]}
    }

    def download_single(url):
        try:
            opts = {
                'outtmpl': os.path.join(output_folder, '%(title)s.%(ext)s'),
                'quiet': True, 'no_check_certificate': True, 'force_ipv4': True, 
                'cachedir': False, 'retries': 5,
                **format_config.get(format_type, {}),
                'http_headers': {
                    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
                    'Referer': 'https://www.youtube.com/',
                },
                # PERBAIKAN: Menambahkan extractor_args untuk menggunakan API klien Android
                'extractor_args': {
                    'youtube': {
                        'player_client': 'android',
                        'skip': 'authcheck'
                    }
                },
            }
            if cookies_file: opts['cookiefile'] = cookies_file
            
            with yt_dlp.YoutubeDL(opts) as ydl:
                # OPTIMASI: Panggil extract_info terlebih dahulu untuk mendapatkan judul
                info = ydl.extract_info(url, download=False)
                title = info.get('title', 'Unknown')
                # Lanjutkan dengan download
                ydl.download([url])

            return True, url, title
        except Exception as e:
            return False, url, str(e)

    try:
        url_list = [url.strip() for url in re.split(r'[\n, ]+', urls) if url.strip()]
        if not url_list:
            return "⚠️ Tidak ada URL yang valid!", None
            
        unique_urls = list(OrderedDict.fromkeys(url_list))
        
        if parallel_dl and len(unique_urls) > 1:
            with ThreadPoolExecutor(max_workers=3) as executor:
                results = list(executor.map(download_single, unique_urls))
        else:
            results = [download_single(url) for url in unique_urls]

        failed_urls = [url for success, url, _ in results if not success]
        success_count = len(results) - len(failed_urls)
        output_files = [os.path.join(output_folder, f) for f in os.listdir(output_folder) if os.path.isfile(os.path.join(output_folder, f))]
        
        final_output = None
        if len(output_files) > 1 and zip_files:
            zip_path = os.path.join(output_folder, 'downloads.zip')
            if os.path.exists(zip_path): os.remove(zip_path)
            with zipfile.ZipFile(zip_path, 'w') as zipf:
                for file in output_files: zipf.write(file, arcname=os.path.basename(file))
            final_output = zip_path
            for file in output_files: os.remove(file)
        elif output_files:
            final_output = output_files

        result_msg = f"✅ {success_count} dari {len(unique_urls)} URL berhasil diunduh!"
        if failed_urls:
            result_msg += f"\n❌ Gagal mengunduh: {len(failed_urls)} URL"

        return result_msg, final_output
    except Exception as e:
        return f"⛔ Terjadi error tak terduga: {str(e)}", None

def download_wrapper(urls, format_type, video_radio, audio_radio, parallel_dl, zip_files, cookies_file):
    if not urls or not urls.strip():
        return "⚠️ Masukkan URL video terlebih dahulu.", None, None, None

    final_cookie_path = get_cookie_path(cookies_file)
    
    video_numeric = map_quality(video_radio)
    audio_numeric = audio_radio if format_type == "MP3" else "192"
    
    result_msg, final_output = download_videos(
        urls, format_type, video_numeric, audio_numeric, parallel_dl, zip_files, final_cookie_path
    )
    
    file_update = gr.update(value=None, visible=False)
    video_update = gr.update(value=None, visible=False)
    audio_update = gr.update(value=None, visible=False)

    if final_output:
        if isinstance(final_output, list) and len(final_output) == 1:
            filepath = final_output[0]
            ext = os.path.splitext(filepath)[1].lower()
            if ext in ['.mp4', '.webm', '.ogg', '.mov']:
                video_update = gr.update(value=filepath, visible=True)
            elif ext in ['.mp3', '.wav', '.flac']:
                audio_update = gr.update(value=filepath, visible=True)
            else:
                file_update = gr.update(value=filepath, visible=True)
        else:
            file_update = gr.update(value=final_output, visible=True)

    return result_msg, file_update, video_update, audio_update

def check_res_wrapper(urls, cookies_file):
    final_cookie_path = get_cookie_path(cookies_file)
    return check_resolutions(urls, final_cookie_path)

# --- Antarmuka Pengguna (Gradio) ---
with gr.Blocks(title="YT Downloader Pro", theme=gr.themes.Soft()) as app:
    gr.Markdown("# 🎥 YouTube Downloader Pro\nUnduh video atau audio dengan cepat dan mudah.")
    with gr.Tabs():
        with gr.TabItem("▶️ Unduh"):
            with gr.Row():
                with gr.Column(scale=2):
                    url_input = gr.Textbox(label="Masukkan satu atau lebih URL (pisahkan dengan koma/spasi/baris baru)", lines=5)
                    with gr.Row():
                        check_res_btn = gr.Button("🔍 Cek Resolusi Video")
                    resolution_output = gr.Markdown(label="Info Resolusi")
                with gr.Column(scale=1):
                    format_radio = gr.Radio(["MP4", "MP3", "WAV"], label="Format Output", value="MP4")
                    video_quality = gr.Radio(["High", "Medium", "Low"], label="Kualitas Video", value="Medium", visible=True)
                    audio_quality = gr.Radio(["320", "256", "192", "128"], label="Kualitas Audio (untuk MP3)", value="192", visible=False)
                    with gr.Row():
                        parallel_toggle = gr.Checkbox(label="Paralel", value=True, scale=1)
                        zip_toggle = gr.Checkbox(label="Jadikan ZIP", value=True, scale=1)
        with gr.TabItem("🔧 Pengaturan Lanjutan"):
            cookies_file_input = gr.File(label="Unggah cookies.txt (opsional, jika tidak menggunakan Secret)", file_types=[".txt"], type="filepath")

    submit_btn = gr.Button("🚀 Mulai Download", variant="primary")
    
    with gr.Accordion("Hasil", open=False):
        result_output = gr.Textbox(label="Status Download", interactive=False)
        file_output = gr.File(label="File Hasil Download", interactive=False, visible=True)
        video_player = gr.Video(label="Hasil Video", visible=False, interactive=False)
        audio_player = gr.Audio(label="Hasil Audio", visible=False, interactive=False)

    def map_quality(quality_opt):
        quality_map = {"High": "4320", "Medium": "1080", "Low": "720"}
        return quality_map.get(quality_opt, "1080")

    def toggle_quality_options(format_choice):
        is_mp4 = format_choice == "MP4"
        is_mp3 = format_choice == "MP3"
        return (gr.update(visible=is_mp4), gr.update(visible=is_mp3))

    app.load(fn=lambda: toggle_quality_options("MP4"), inputs=None, outputs=[video_quality, audio_quality])
    format_radio.change(fn=toggle_quality_options, inputs=format_radio, outputs=[video_quality, audio_quality])
    
    submit_btn.click(
        fn=download_wrapper, 
        inputs=[url_input, format_radio, video_quality, audio_quality, parallel_toggle, zip_toggle, cookies_file_input], 
        outputs=[result_output, file_output, video_player, audio_player]
    )
    
    check_res_btn.click(
        fn=check_res_wrapper,
        inputs=[url_input, cookies_file_input],
        outputs=resolution_output)

if __name__ == "__main__":
    app.launch(debug=True)
