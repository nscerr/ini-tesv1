<?php
// Mengaktifkan laporan error untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- PENGAMBILAN DATA ASLI ---
require_once 'db_connect.php';

$page = $_GET['page'] ?? 'dashboard';

// 1. Mengambil semua akun (akan dipaginasi nanti jika di halaman manage_accounts)
$sql_all_accounts = "SELECT id, threads_username, token_expires_at, updated_at FROM threads_accounts ORDER BY updated_at DESC";
$result_all_accounts = $conn->query($sql_all_accounts);
$all_accounts_data = [];
if ($result_all_accounts && $result_all_accounts->num_rows > 0) {
    while($row = $result_all_accounts->fetch_assoc()) {
        $all_accounts_data[] = $row;
    }
}
$total_accounts = count($all_accounts_data);

// 2. Mengambil statistik postingan
$sql_stats = "SELECT status, COUNT(id) as count FROM scheduled_posts GROUP BY status";
$result_stats = $conn->query($sql_stats);
$post_stats = ['published' => 0, 'failed' => 0, 'scheduled' => 0];
if ($result_stats && $result_stats->num_rows > 0) {
    while ($row = $result_stats->fetch_assoc()) {
        if (isset($post_stats[$row['status']])) {
            $post_stats[$row['status']] = $row['count'];
        }
    }
}

// 3. Mengambil pengaturan penjadwalan
$settings = [];
$sqlSettings = "SELECT setting_name, setting_value FROM scheduler_settings";
$resultSettings = $conn->query($sqlSettings);
if ($resultSettings && $resultSettings->num_rows > 0) {
    while($row = $resultSettings->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
}
$startTime = $settings['start_time'] ?? '09:00';
$endTime = $settings['end_time'] ?? '17:00';

// --- Logika Filter dan Paginasi ---
$posts_per_page = 5;
$accounts_per_page = 5;

if ($page === 'queue') {
    $filter_status = $_GET['status'] ?? 'all';
    $allowed_statuses = ['scheduled', 'published', 'failed'];
    $where_clause = '';
    $params = [];
    $types = '';

    if (in_array($filter_status, $allowed_statuses)) {
        $where_clause = "WHERE status = ?";
        $params[] = $filter_status;
        $types .= 's';
    }

    $sql_count = "SELECT COUNT(id) as total FROM scheduled_posts $where_clause";
    $stmt_count = $conn->prepare($sql_count);
    if (!empty($params)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $count_result = $stmt_count->get_result();
    $total_posts = $count_result->fetch_assoc()['total'] ?? 0;
    $total_pages = ceil($total_posts / $posts_per_page);
    if($total_pages < 1) $total_pages = 1;

    $current_page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
    if ($current_page < 1) $current_page = 1;
    if ($current_page > $total_pages) $current_page = $total_pages;
    $offset = ($current_page - 1) * $posts_per_page;

    $main_where_clause = '';
    if (in_array($filter_status, $allowed_statuses)) {
        $main_where_clause = "WHERE p.status = ?";
    }

    $sqlPosts = "
        SELECT p.id, p.post_content, p.status, p.scheduled_at, p.error_message,
               GROUP_CONCAT(ta.threads_username SEPARATOR ', ') as target_usernames
        FROM scheduled_posts p
        LEFT JOIN post_accounts pa ON p.id = pa.post_id
        LEFT JOIN threads_accounts ta ON pa.account_id = ta.id
        $main_where_clause
        GROUP BY p.id
        ORDER BY p.scheduled_at DESC
        LIMIT ? OFFSET ?
    ";
    $stmt_posts = $conn->prepare($sqlPosts);
    $main_params = $params;
    $main_params[] = $posts_per_page;
    $main_params[] = $offset;
    $main_types = $types . 'ii';
    
    if (!empty($params)) {
        $stmt_posts->bind_param($main_types, ...$main_params);
    } else {
        $stmt_posts->bind_param('ii', $posts_per_page, $offset);
    }
    
    $stmt_posts->execute();
    $resultPosts = $stmt_posts->get_result();
} elseif ($page === 'manage_accounts') {
    $total_pages_accounts = ceil($total_accounts / $accounts_per_page);
    if($total_pages_accounts < 1) $total_pages_accounts = 1;
    
    $current_page_accounts = isset($_GET['p']) ? (int)$_GET['p'] : 1;
    if ($current_page_accounts < 1) $current_page_accounts = 1;
    if ($current_page_accounts > $total_pages_accounts) $current_page_accounts = $total_pages_accounts;
    
    $offset_accounts = ($current_page_accounts - 1) * $accounts_per_page;
    
    $accounts_data_paginated = array_slice($all_accounts_data, $offset_accounts, $accounts_per_page);
}


function get_page_title($page) {
    switch ($page) {
        case 'create_post': return 'Buat Postingan Baru';
        case 'manage_accounts': return 'Kelola Akun';
        case 'queue': return 'Antrean Postingan';
        default: return 'Dashboard';
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo get_page_title($page); ?> - Threads Scheduler</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Tom Select for professional multi-select dropdown -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    <script>
        // Script untuk menerapkan dark mode secepat mungkin untuk menghindari FOUC (Flash of Unstyled Content)
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        // Konfigurasi Tailwind untuk dark mode
        tailwind.config = {
            darkMode: 'class',
        }
    </script>

    <style>
        /* Menggunakan font Inter sebagai default */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* slate-50 */
            transition: background-color 0.3s, color 0.3s;
        }
        .dark body {
            background-color: #0f172a; /* slate-900 */
        }

        /* Style tambahan untuk transisi sidebar */
        .sidebar-transition {
            transition: transform 0.3s ease-in-out;
        }

        /* Kelas untuk mengunci scroll pada body */
        .body-lock {
            overflow: hidden;
        }

        /* Kustomisasi Tom Select agar sesuai dengan tema Tailwind */
        .ts-control {
            border-radius: 0.375rem; /* rounded-md */
            border-color: #cbd5e1; /* slate-300 */
            padding: 0.5rem 0.75rem;
        }
        .dark .ts-control {
            background-color: #1e293b; /* slate-800 */
            border-color: #475569; /* slate-600 */
        }
        .ts-control:focus, .ts-control.focus {
            border-color: #3b82f6; /* blue-500 */
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
        }
        .ts-control .item {
            background-color: #3b82f6; /* blue-500 */
            color: white;
            border-radius: 0.25rem; /* rounded-sm */
            padding: 0.25rem 0.5rem;
        }
        .dark .ts-control .item {
            background-color: #2563eb; /* blue-600 */
        }
        .ts-dropdown {
            border-radius: 0.375rem; /* rounded-md */
            border-color: #cbd5e1; /* slate-300 */
        }
        .dark .ts-dropdown {
            background-color: #1e293b; /* slate-800 */
            border-color: #475569; /* slate-600 */
        }
        .dark .ts-dropdown .option {
            color: #d1d5db; /* gray-300 */
        }
        .dark .ts-dropdown .option:hover, .dark .ts-dropdown .active {
            background-color: #334155; /* slate-700 */
        }
        /* Style untuk menyembunyikan placeholder saat ada item */
        .ts-control input::placeholder {
            color: transparent;
        }
        .ts-control input.empty::placeholder {
            color: #a0aec0;
        }
        
        /* Animasi untuk Toast Notification */
        @keyframes toast-in {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes toast-out {
            from { transform: translateY(0); opacity: 1; }
            to { transform: translateY(-20px); opacity: 0; }
        }
        .toast-in { animation: toast-in 0.3s ease-out forwards; }
        .toast-out { animation: toast-out 0.3s ease-in forwards; }
        
        /* Bulk Action Bar Styles */
        .bulk-action-bar {
            transition: transform 0.3s ease-in-out;
        }        
    </style>
</head>
<body class="text-slate-800 dark:text-slate-300">

    <div class="relative min-h-screen md:flex">
        <!-- Mobile Header -->
        <div class="md:hidden flex justify-between items-center p-4 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 sticky top-0 z-30">
            <button id="hamburger" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white z-50">
                <i data-lucide="menu" class="w-6 h-6" id="hamburger-icon-open"></i>
                <i data-lucide="x" class="w-6 h-6 hidden" id="hamburger-icon-close"></i>
            </button>
            <a href="?page=dashboard" class="text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center space-x-2">
                <i data-lucide="send" class="w-6 h-6 text-blue-600"></i>
                <span>Threads App</span>
            </a>
            <button id="theme-toggle-mobile" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">
                <i data-lucide="sun" class="w-6 h-6 hidden theme-icon" data-theme-icon="light"></i>
                <i data-lucide="moon" class="w-6 h-6 hidden theme-icon" data-theme-icon="dark"></i>
                <i data-lucide="monitor" class="w-6 h-6 hidden theme-icon" data-theme-icon="system"></i>
            </button>
        </div>

        <!-- Overlay for Mobile Sidebar -->
        <div id="sidebar-overlay" class="md:hidden fixed inset-0 bg-black bg-opacity-50 z-10 hidden"></div>

        <!-- Sidebar -->
        <aside id="sidebar" class="bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 w-64 flex flex-col py-7 px-2 fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 sidebar-transition z-20 shadow-lg md:shadow-none border-r border-slate-200 dark:border-slate-700">
            <div class="flex-grow">
                <a href="?page=dashboard" class="text-slate-900 dark:text-slate-100 text-2xl font-bold px-4 flex items-center space-x-2">
                    <i data-lucide="send" class="w-7 h-7 text-blue-600"></i>
                    <span>Threads App</span>
                </a>

                <nav class="mt-8">
                    <a href="?page=dashboard" class="flex items-center space-x-3 py-2.5 px-4 rounded-lg transition duration-200 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-slate-700 dark:hover:text-slate-100 <?php echo ($page === 'dashboard') ? 'bg-blue-50 text-blue-600 dark:bg-slate-700 dark:text-white font-semibold' : ''; ?>">
                        <i data-lucide="layout-dashboard"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="?page=create_post" class="flex items-center space-x-3 py-2.5 px-4 rounded-lg transition duration-200 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-slate-700 dark:hover:text-slate-100 <?php echo ($page === 'create_post') ? 'bg-blue-50 text-blue-600 dark:bg-slate-700 dark:text-white font-semibold' : ''; ?>">
                        <i data-lucide="plus-square"></i>
                        <span>Buat Postingan</span>
                    </a>
                    <a href="?page=manage_accounts" class="flex items-center space-x-3 py-2.5 px-4 rounded-lg transition duration-200 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-slate-700 dark:hover:text-slate-100 <?php echo ($page === 'manage_accounts') ? 'bg-blue-50 text-blue-600 dark:bg-slate-700 dark:text-white font-semibold' : ''; ?>">
                        <i data-lucide="users"></i>
                        <span>Kelola Akun</span>
                    </a>
                    <a href="?page=queue" class="flex items-center space-x-3 py-2.5 px-4 rounded-lg transition duration-200 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-slate-700 dark:hover:text-slate-100 <?php echo ($page === 'queue') ? 'bg-blue-50 text-blue-600 dark:bg-slate-700 dark:text-white font-semibold' : ''; ?>">
                        <i data-lucide="list-checks"></i>
                        <span>Antrean Postingan</span>
                    </a>
                </nav>
            </div>
            <div class="px-4">
                <button id="theme-toggle-desktop" class="w-full flex items-center justify-center space-x-2 py-2.5 px-4 rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 transition">
                    <i data-lucide="sun" class="w-5 h-5 hidden theme-icon" data-theme-icon="light"></i>
                    <i data-lucide="moon" class="w-5 h-5 hidden theme-icon" data-theme-icon="dark"></i>
                    <i data-lucide="monitor" class="w-5 h-5 hidden theme-icon" data-theme-icon="system"></i>
                    <span id="theme-text" class="text-sm font-medium text-slate-600 dark:text-slate-300"></span>
                </button>
            </div>
        </aside>

        <!-- Konten Utama -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="max-w-7xl mx-auto">
                <div class="max-w-7xl mx-auto pb-24"> <!-- Padding bottom for bulk action bar -->
                
                <?php if ($page === 'dashboard'): ?>
                    <?php
                    // Logika untuk sapaan dinamis berdasarkan waktu
                    date_default_timezone_set('Asia/Jakarta');
                    $hour = date('G');
                    $greeting = '';
                    if ($hour >= 5 && $hour < 12) {
                        $greeting = 'Selamat Pagi';
                    } elseif ($hour >= 12 && $hour < 15) {
                        $greeting = 'Selamat Siang';
                    } elseif ($hour >= 15 && $hour < 18) {
                        $greeting = 'Selamat Sore';
                    } else {
                        $greeting = 'Selamat Malam';
                    }
                    ?>
                    <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100">Halo User, <?php echo $greeting; ?></h1>
                    <p class="mt-2 text-slate-600 dark:text-slate-400">Selamat datang kembali, mari kita lihat laporan aktivitas Anda.</p>
                    
                    <div class="mt-10 mb-6">
                        <h2 class="text-xl font-semibold text-slate-800 dark:text-slate-200 flex items-center mb-4">
                            <i data-lucide="bar-chart-2" class="w-5 h-5 mr-2 text-slate-500 dark:text-slate-400"></i>
                            Laporan Aktifitas
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div class="bg-white dark:bg-slate-800 p-6 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center space-x-4">
                                <div class="bg-green-100 dark:bg-green-900/50 p-3 rounded-full"><i data-lucide="check-circle-2" class="w-6 h-6 text-green-600 dark:text-green-400"></i></div>
                                <div>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Terpublikasi</p>
                                    <p class="text-2xl font-bold text-slate-900 dark:text-slate-100"><?php echo $post_stats['published']; ?></p>
                                </div>
                            </div>
                            <div class="bg-white dark:bg-slate-800 p-6 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center space-x-4">
                                <div class="bg-red-100 dark:bg-red-900/50 p-3 rounded-full"><i data-lucide="x-circle" class="w-6 h-6 text-red-600 dark:text-red-400"></i></div>
                                <div>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Gagal</p>
                                    <p class="text-2xl font-bold text-slate-900 dark:text-slate-100"><?php echo $post_stats['failed']; ?></p>
                                </div>
                            </div>
                            <div class="bg-white dark:bg-slate-800 p-6 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center space-x-4">
                                <div class="bg-blue-100 dark:bg-blue-900/50 p-3 rounded-full"><i data-lucide="clock" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i></div>
                                <div>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Dalam Antrean</p>
                                    <p class="text-2xl font-bold text-slate-900 dark:text-slate-100"><?php echo $post_stats['scheduled']; ?></p>
                                </div>
                            </div>
                            <div class="bg-white dark:bg-slate-800 p-6 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center space-x-4">
                                <div class="bg-indigo-100 dark:bg-indigo-900/50 p-3 rounded-full"><i data-lucide="at-sign" class="w-6 h-6 text-indigo-600 dark:text-indigo-400"></i></div>
                                <div>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Akun Terhubung</p>
                                    <p class="text-2xl font-bold text-slate-900 dark:text-slate-100"><?php echo $total_accounts; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-10">
                        <h2 class="text-xl font-semibold text-slate-800 dark:text-slate-200 flex items-center mb-4">
                            <i data-lucide="zap" class="w-5 h-5 mr-2 text-slate-500 dark:text-slate-400"></i>
                            Pintasan
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <a href="?page=queue" class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center space-x-3 hover:border-blue-500 dark:hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-slate-700 transition">
                                <div class="bg-blue-100 dark:bg-blue-900/50 p-3 rounded-lg"><i data-lucide="list-checks" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i></div>
                                <span class="font-semibold text-slate-700 dark:text-slate-300">Antrean Postingan</span>
                            </a>
                            <a href="?page=create_post" class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center space-x-3 hover:border-blue-500 dark:hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-slate-700 transition">
                                <div class="bg-green-100 dark:bg-green-900/50 p-3 rounded-lg"><i data-lucide="plus-square" class="w-6 h-6 text-green-600 dark:text-green-400"></i></div>
                                <span class="font-semibold text-slate-700 dark:text-slate-300">Buat Postingan</span>
                            </a>
                            <a href="?page=manage_accounts" class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center space-x-3 hover:border-blue-500 dark:hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-slate-700 transition">
                                <div class="bg-indigo-100 dark:bg-indigo-900/50 p-3 rounded-lg"><i data-lucide="users" class="w-6 h-6 text-indigo-600 dark:text-indigo-400"></i></div>
                                <span class="font-semibold text-slate-700 dark:text-slate-300">Kelola Akun</span>
                            </a>
                        </div>
                    </div>

                <?php else: ?>
                    <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100 mb-6"><?php echo get_page_title($page); ?></h1>
                
                    <?php if ($page === 'create_post'): ?>
                        <div class="bg-white dark:bg-slate-800 p-6 rounded-xl border border-slate-200 dark:border-slate-700">
                            <form id="mainPostForm">
                                <div>
                                    <label for="post_content_main" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Isi Postingan</label>
                                    <textarea name="post_content" id="post_content_main" rows="8" class="w-full p-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" placeholder="Tulis sesuatu yang menarik..."></textarea>
                                </div>
                                <div class="mt-4">
                                    <button type="button" onclick="openSchedulerModal()" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition">
                                        <i data-lucide="calendar" class="w-5 h-5 mr-2"></i>
                                        Jadwalkan
                                    </button>
                                </div>
                            </form>
                        </div>

                    <?php elseif ($page === 'manage_accounts'): ?>
                        <div class="space-y-8">
                            <div class="bg-white dark:bg-slate-800 p-6 rounded-xl border border-slate-200 dark:border-slate-700">
                                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">Tambah Akun Threads Baru</h3>
                                <a href="start_auth.php?action=add" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition whitespace-nowrap">
                                    <i data-lucide="user-plus" class="w-5 h-5 mr-2"></i>
                                    Hubungkan Akun Baru
                                </a>
                            </div>
                            
                            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                                <div class="p-6 border-b border-slate-200 dark:border-slate-700">
                                   <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Akun Terhubung (<?php echo $total_accounts; ?>)</h3>
                                </div>
                                
                                <div class="space-y-4 md:space-y-0 p-4 md:p-0">
                                    <div class="hidden md:flex bg-slate-50 dark:bg-slate-700/50 text-xs text-slate-500 dark:text-slate-400 uppercase font-semibold">
                                        <div class="w-1/4 px-6 py-3">Akun</div>
                                        <div class="w-1/4 px-6 py-3">Status</div>
                                        <div class="w-1/4 px-6 py-3">Tanggal Integrasi</div>
                                        <div class="w-1/4 px-6 py-3 text-right">Aksi</div>
                                    </div>

                                    <?php if ($total_accounts > 0): ?>
                                        <?php foreach ($accounts_data_paginated as $account): 
                                            $is_expired = time() > strtotime($account['token_expires_at']);
                                            $updated_date = new DateTime($account['updated_at']);
                                        ?>
                                            <div class="md:flex md:items-center border md:border-0 border-slate-200 dark:border-slate-700 rounded-lg p-4 md:p-0">
                                                <div class="w-full md:w-1/4 md:px-6 md:py-4">
                                                    <div class="text-xs text-slate-500 dark:text-slate-400 md:hidden mb-1">Akun</div>
                                                    <div class="font-medium text-slate-900 dark:text-slate-100">@<?php echo htmlspecialchars($account['threads_username']); ?></div>
                                                </div>
                                                <div class="w-full md:w-1/4 md:px-6 md:py-4 mt-2 md:mt-0">
                                                    <div class="text-xs text-slate-500 dark:text-slate-400 md:hidden mb-1">Status</div>
                                                    <?php if ($is_expired): ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300">
                                                            <i data-lucide="alert-triangle" class="w-3 h-3 mr-1.5"></i>
                                                            Perlu Dihubungkan Ulang
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">
                                                            <i data-lucide="check-circle" class="w-3 h-3 mr-1.5"></i>
                                                            Terhubung
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="w-full md:w-1/4 md:px-6 md:py-4 mt-2 md:mt-0">
                                                    <div class="text-xs text-slate-500 dark:text-slate-400 md:hidden mb-1">Tanggal Integrasi</div>
                                                    <div class="text-slate-500 dark:text-slate-400"><?php echo $updated_date->format('d M Y, H:i'); ?></div>
                                                </div>
                                                <div class="w-full md:w-1/4 md:px-6 md:py-4 mt-4 md:mt-0">
                                                    <div class="text-xs text-slate-500 dark:text-slate-400 md:hidden mb-1">Aksi</div>
                                                    <div class="flex items-center justify-start md:justify-end space-x-2">
                                                        <a href="start_auth.php?action=reconnect&account_id=<?php echo $account['id']; ?>" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline">Hubungkan Ulang</a>
                                                        <span class="text-slate-300 dark:text-slate-600">|</span>
                                                        <form action="account_action.php" method="POST" class="requires-confirmation" data-confirmation-message="Anda yakin ingin menghapus akun @<?php echo htmlspecialchars($account['threads_username']); ?>?">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                                            <button type="submit" class="font-semibold text-red-600 dark:text-red-400 hover:underline">Hapus</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-10 text-slate-500 dark:text-slate-400">
                                            Belum ada akun Threads yang terhubung.
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($total_pages_accounts > 1): ?>
                                <div class="p-4 border-t border-slate-200 dark:border-slate-700 flex justify-center items-center space-x-1">
                                    <a href="?page=manage_accounts&p=<?php echo $current_page_accounts - 1; ?>" class="inline-flex items-center px-3 py-2 text-sm font-medium text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-800 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-slate-700 dark:hover:text-slate-200 <?php if($current_page_accounts <= 1){ echo 'pointer-events-none opacity-50'; } ?>">
                                        &lt; Prev
                                    </a>
                                    <?php for ($i = 1; $i <= $total_pages_accounts; $i++): ?>
                                        <a href="?page=manage_accounts&p=<?php echo $i; ?>" class="inline-flex items-center justify-center w-10 h-10 text-sm font-medium rounded-lg <?php echo ($i == $current_page_accounts) ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-slate-700 dark:hover:text-slate-200'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                    <a href="?page=manage_accounts&p=<?php echo $current_page_accounts + 1; ?>" class="inline-flex items-center px-3 py-2 text-sm font-medium text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-800 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-slate-700 dark:hover:text-slate-200 <?php if($current_page_accounts >= $total_pages_accounts){ echo 'pointer-events-none opacity-50'; } ?>">
                                        Next &gt;
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php elseif ($page === 'queue'): ?>
                        <div class="flex flex-wrap gap-2 mb-6">
                            <?php
                            $filter_buttons = ['all' => 'Semua', 'scheduled' => 'Scheduled', 'published' => 'Published', 'failed' => 'Failed'];
                            foreach ($filter_buttons as $status => $text):
                                $isActive = ($filter_status === $status);
                                $url = "?page=queue" . ($status !== 'all' ? "&status=$status" : "");
                                $activeClass = 'bg-blue-600 text-white';
                                $inactiveClass = 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700';
                            ?>
                                <a href="<?php echo $url; ?>" class="px-4 py-2 text-sm font-semibold rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 transition <?php echo $isActive ? $activeClass : $inactiveClass; ?>">
                                    <?php echo $text; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                            <?php
                            if ($resultPosts && $resultPosts->num_rows > 0):
                                while($rowPost = $resultPosts->fetch_assoc()):
                                    try {
                                        $scheduled_datetime = new DateTime($rowPost['scheduled_at']);
                                    } catch (Exception $e) {
                                        $scheduled_datetime = new DateTime(); // Fallback to current time
                                    }
                                    $post_content = $rowPost['post_content']; // No htmlspecialchars here to pass raw data to JS
                                    $display_content = htmlspecialchars($post_content);
                                    $char_limit = 150;
                                    $is_long_text = strlen($display_content) > $char_limit;
                                    $short_content = $is_long_text ? substr($display_content, 0, $char_limit) . '...' : $display_content;
                                ?>
                                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col">
                                    <div class="p-6 flex-grow">
                                        <div class="text-slate-700 dark:text-slate-300 leading-relaxed mb-4">
                                            <p data-content="short-<?php echo $rowPost['id']; ?>"><?php echo nl2br($short_content); ?></p>
                                            <?php if ($is_long_text): ?>
                                            <p data-content="full-<?php echo $rowPost['id']; ?>" class="hidden"><?php echo nl2br($display_content); ?></p>
                                            <a href="#" class="text-blue-600 dark:text-blue-400 hover:underline text-sm font-semibold read-more-toggle mt-2 inline-block" data-post-id="<?php echo $rowPost['id']; ?>">Baca selengkapnya...</a>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="space-y-2 text-sm text-slate-500 dark:text-slate-400">
                                            <div class="flex items-center">
                                                <i data-lucide="calendar" class="w-4 h-4 mr-2"></i>
                                                <span><?php echo $scheduled_datetime->format('Y-m-d'); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i data-lucide="clock" class="w-4 h-4 mr-2"></i>
                                                <span><?php echo $scheduled_datetime->format('H:i'); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i data-lucide="at-sign" class="w-4 h-4 mr-2"></i>
                                                <span><?php echo htmlspecialchars($rowPost['target_usernames'] ?? 'Tidak ada'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="px-6 pb-4">
                                        <div class="flex justify-between items-center mb-4">
                                            <?php 
                                            $status = htmlspecialchars($rowPost['status']);
                                            $badge_class = '';
                                            if ($status == 'published') $badge_class = 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300';
                                            elseif ($status == 'failed') $badge_class = 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300';
                                            else $badge_class = 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300';
                                            echo "<span class='px-2.5 py-1 text-xs font-semibold rounded-full $badge_class capitalize'>$status</span>";
                                            ?>
                                            
                                            <div class="flex items-center space-x-2">
                                                <?php if ($rowPost['status'] == 'scheduled'): ?>
                                                <button class="edit-post-btn text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 p-2 rounded-full hover:bg-blue-50 dark:hover:bg-slate-700 transition" title="Edit"
                                                        data-post-id="<?php echo $rowPost['id']; ?>"
                                                        data-post-content="<?php echo htmlspecialchars($post_content); ?>"
                                                        data-scheduled-at="<?php echo $scheduled_datetime->format('Y-m-d\TH:i'); ?>">
                                                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <form action="post_action.php" method="POST" class="requires-confirmation" data-confirmation-message="Anda yakin ingin menghapus postingan ini? Tindakan ini tidak dapat diurungkan.">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="post_id" value="<?php echo $rowPost['id']; ?>">
                                                    <button type="submit" class="text-slate-500 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 p-2 rounded-full hover:bg-red-50 dark:hover:bg-slate-700 transition" title="Hapus">
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>

                                        <?php if ($rowPost['status'] == 'failed' && !empty($rowPost['error_message'])): ?>
                                        <div class="bg-red-50 dark:bg-red-900/30 border-l-4 border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 p-3 rounded-r-lg">
                                            <div class="flex">
                                                <div class="py-1"><i data-lucide="alert-triangle" class="w-5 h-5 text-red-500 dark:text-red-400 mr-3"></i></div>
                                                <div>
                                                    <p class="text-sm"><?php echo htmlspecialchars($rowPost['error_message']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile;
                            else: ?>
                                <div class="md:col-span-2 xl:col-span-3 text-center py-10 text-slate-500 dark:text-slate-400">
                                    <p>Tidak ada postingan dengan status "<?php echo htmlspecialchars($filter_status); ?>".</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Kontrol Paginasi Baru -->
                        <?php if ($total_pages > 1): ?>
                        <div class="mt-8 flex justify-center items-center space-x-1">
                            <!-- Tombol Sebelumnya -->
                            <a href="?page=queue&status=<?php echo $filter_status; ?>&p=<?php echo $current_page - 1; ?>" class="inline-flex items-center px-3 py-2 text-sm font-medium text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-800 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-slate-700 dark:hover:text-slate-200 <?php if($current_page <= 1){ echo 'pointer-events-none opacity-50'; } ?>">
                                &lt; Prev
                            </a>

                            <!-- Nomor Halaman -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=queue&status=<?php echo $filter_status; ?>&p=<?php echo $i; ?>" class="inline-flex items-center justify-center w-10 h-10 text-sm font-medium rounded-lg <?php echo ($i == $current_page) ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-slate-700 dark:hover:text-slate-200'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <!-- Tombol Berikutnya -->
                            <a href="?page=queue&status=<?php echo $filter_status; ?>&p=<?php echo $current_page + 1; ?>" class="inline-flex items-center px-3 py-2 text-sm font-medium text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-800 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-slate-700 dark:hover:text-slate-200 <?php if($current_page >= $total_pages){ echo 'pointer-events-none opacity-50'; } ?>">
                                Next &gt;
                            </a>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </main>
    </div>
    
    <!-- Bulk Action Bar -->
    <div id="bulk-action-bar" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 shadow-lg p-4 transform translate-y-full bulk-action-bar z-40">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <input type="checkbox" id="select-all-checkbox" class="h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <span id="selected-count" class="font-semibold text-slate-700 dark:text-slate-300">0 item dipilih</span>
            </div>
            <div class="flex items-center space-x-2">
                <button id="bulk-delete-btn" disabled class="px-4 py-2 bg-red-600 text-white font-semibold rounded-lg shadow-md hover:bg-red-700 disabled:bg-red-300 dark:disabled:bg-red-800/50 disabled:cursor-not-allowed transition">
                    Hapus yang Dipilih
                </button>
                <button id="cancel-select-mode-btn" class="p-2 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-full">
                     <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Hidden form for bulk actions -->
    <form id="bulk-delete-form" method="POST" style="display:none;"></form>    
    
    <!-- Kontainer untuk Toast Notifications -->
    <div id="toast-container" class="fixed top-4 left-4 right-4 sm:left-auto sm:top-5 sm:right-5 z-50 space-y-2 sm:w-full sm:max-w-sm"></div>

    <!-- Modal Konfirmasi Kustom -->
    <div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/50">
                    <i data-lucide="alert-triangle" class="h-6 w-6 text-red-600 dark:text-red-400"></i>
                </div>
                <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Konfirmasi Aksi</h3>
                <div class="mt-2">
                    <p class="text-sm text-slate-500 dark:text-slate-400" id="confirmation-message">Apakah Anda yakin?</p>
                </div>
                <div class="mt-6 flex justify-center space-x-3">
                    <button id="cancel-confirmation-btn" class="px-4 py-2 bg-white dark:bg-slate-600 text-slate-700 dark:text-slate-300 font-semibold border border-slate-300 dark:border-slate-500 rounded-lg shadow-sm hover:bg-slate-50 dark:hover:bg-slate-500 focus:outline-none transition">
                        Batal
                    </button>
                    <button id="confirm-action-btn" class="px-4 py-2 bg-red-600 text-white font-semibold rounded-lg shadow-md hover:bg-red-700 focus:outline-none transition">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Penjadwalan -->
    <div id="schedulerModal" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg transform transition-all">
            <div class="flex justify-between items-center p-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Pengaturan Jadwal</h3>
                <button id="close-scheduler-modal" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <form action="post_handler.php" method="POST" id="scheduleForm" class="p-6 space-y-6">
                <input type="hidden" name="post_content" id="modal_post_content">
                <input type="hidden" name="action" value="save_post">
                <div>
                    <label for="modal_target_accounts" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Pilih Akun Tujuan</label>
                    <select name="target_accounts[]" id="modal_target_accounts" multiple>
                        <?php foreach ($all_accounts_data as $account): ?>
                            <option value="<?php echo htmlspecialchars($account['threads_username']); ?>"><?php echo htmlspecialchars($account['threads_username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Rentang Jam Publikasi</label>
                    <div class="flex items-center gap-2">
                        <input type="time" name="start_time" value="<?php echo htmlspecialchars($startTime); ?>" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        <span class="text-slate-500 dark:text-slate-400">sampai</span>
                        <input type="time" name="end_time" value="<?php echo htmlspecialchars($endTime); ?>" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                    </div>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" id="save_settings" name="save_settings_permanently" value="yes" class="h-4 w-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                    <label for="save_settings" class="ml-2 block text-sm text-slate-900 dark:text-slate-300">Simpan pengaturan ini secara permanen</label>
                </div>
                <div class="pt-4 border-t border-slate-200 dark:border-slate-700 flex justify-end">
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition">
                        <i data-lucide="calendar" class="w-5 h-5 mr-2"></i>
                        Jadwalkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Postingan -->
    <div id="editPostModal" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg transform transition-all">
            <div class="flex justify-between items-center p-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Edit Postingan</h3>
                <button id="close-edit-modal" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <form action="post_action.php" method="POST" id="editPostForm" class="p-6 space-y-4">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="post_id" id="edit_post_id">
                <div>
                    <label for="edit_post_content" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Isi Postingan</label>
                    <textarea name="post_content" id="edit_post_content" rows="8" class="w-full p-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"></textarea>
                </div>
                <div>
                    <label for="edit_scheduled_at" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Jadwal Publikasi</label>
                    <input type="datetime-local" name="scheduled_at" id="edit_scheduled_at" class="w-full p-2 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                </div>
                <div class="pt-6 border-t border-slate-200 dark:border-slate-700 flex justify-end space-x-3">
                    <button type="button" id="cancel-edit-btn" class="px-4 py-2 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm hover:bg-slate-50 dark:hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-400 transition">
                        Batal
                    </button>
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition">
                        <i data-lucide="save" class="w-5 h-5 mr-2"></i>
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Helper Functions ---
            const lockBodyScroll = () => document.body.classList.add('body-lock');
            const unlockBodyScroll = () => document.body.classList.remove('body-lock');

            // Inisialisasi Lucide Icons
            lucide.createIcons();

            // --- Modal & Overlay Logic ---
            const modals = {
                schedulerModal: { element: document.getElementById('schedulerModal'), openBtn: null, closeBtn: document.getElementById('close-scheduler-modal') },
                editPostModal: { element: document.getElementById('editPostModal'), openBtn: null, closeBtn: document.getElementById('close-edit-modal'), cancelBtn: document.getElementById('cancel-edit-btn') },
                confirmationModal: { element: document.getElementById('confirmationModal'), openBtn: null, closeBtn: document.getElementById('cancel-confirmation-btn') }
            };

            const openModal = (modalName) => {
                if (modals[modalName] && modals[modalName].element) {
                    modals[modalName].element.style.display = 'flex';
                    lockBodyScroll();
                }
            };

            const closeModal = (modalName) => {
                if (modals[modalName] && modals[modalName].element) {
                    modals[modalName].element.style.display = 'none';
                    unlockBodyScroll();
                }
            };
            
            // Event listeners for closing modals
            Object.keys(modals).forEach(name => {
                const modal = modals[name];
                if(modal.element) {
                    modal.element.addEventListener('click', (e) => { if (e.target === modal.element) closeModal(name); });
                    if(modal.closeBtn) modal.closeBtn.addEventListener('click', () => closeModal(name));
                    if(modal.cancelBtn) modal.cancelBtn.addEventListener('click', () => closeModal(name));
                }
            });

            // Global function to open scheduler modal
            window.openSchedulerModal = () => {
                const postContent = document.getElementById('post_content_main').value;
                if (postContent.trim() === '') {
                    showToast('Isi postingan tidak boleh kosong!', 'error');
                    return;
                }
                document.getElementById('modal_post_content').value = postContent;
                openModal('schedulerModal');
            };

            // Open Edit Modal Logic
            document.querySelectorAll('.edit-post-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('edit_post_id').value = this.dataset.postId;
                    document.getElementById('edit_post_content').value = this.dataset.postContent;
                    document.getElementById('edit_scheduled_at').value = this.dataset.scheduledAt;
                    openModal('editPostModal');
                });
            });

            // --- Tom Select Logic ---
            const placeholderText = 'Pilih satu atau lebih akun...';
            const tomSelect = new TomSelect('#modal_target_accounts', {
                plugins: ['remove_button'],
                placeholder: placeholderText,
                onInitialize: function() {
                    if (this.items.length > 0) {
                        this.control_input.setAttribute('placeholder', '');
                        this.control_input.classList.remove('empty');
                    } else {
                        this.control_input.classList.add('empty');
                    }
                },
                onItemAdd: function() {
                    this.control_input.setAttribute('placeholder', '');
                    this.control_input.classList.remove('empty');
                },
                onItemRemove: function() {
                    if (this.items.length === 0) {
                        this.control_input.setAttribute('placeholder', placeholderText);
                        this.control_input.classList.add('empty');
                    }
                }
            });

            // --- Mobile Sidebar Logic ---
            const hamburger = document.getElementById('hamburger');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            const openIcon = document.getElementById('hamburger-icon-open');
            const closeIcon = document.getElementById('hamburger-icon-close');

            const toggleSidebar = () => {
                const isOpening = sidebar.classList.contains('-translate-x-full');
                sidebar.classList.toggle('-translate-x-full');
                sidebarOverlay.classList.toggle('hidden');
                openIcon.classList.toggle('hidden');
                closeIcon.classList.toggle('hidden');
                if (isOpening) {
                    lockBodyScroll();
                } else {
                    unlockBodyScroll();
                }
            };

            if (hamburger && sidebar && sidebarOverlay) {
                hamburger.addEventListener('click', toggleSidebar);
                sidebarOverlay.addEventListener('click', toggleSidebar);
            }

            // --- "Baca selengkapnya..." Logic ---
            document.querySelectorAll('.read-more-toggle').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const postId = this.dataset.postId;
                    document.querySelector(`[data-content="short-${postId}"]`).classList.toggle('hidden');
                    document.querySelector(`[data-content="full-${postId}"]`).classList.toggle('hidden');
                    this.textContent = this.textContent.includes('Baca') ? 'Tampilkan lebih sedikit...' : 'Baca selengkapnya...';
                });
            });

            // --- Custom Confirmation Modal Logic ---
            let formToSubmit = null;
            document.querySelectorAll('form.requires-confirmation').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    formToSubmit = e.target;
                    const message = e.target.dataset.confirmationMessage || 'Apakah Anda yakin ingin melanjutkan?';
                    document.getElementById('confirmation-message').textContent = message;
                    openModal('confirmationModal');
                });
            });
            document.getElementById('confirm-action-btn').addEventListener('click', () => {
                if (formToSubmit) {
                    formToSubmit.submit();
                }
                closeModal('confirmationModal');
            });
            
            // --- Toast Notification & URL Parameter Handling ---
            const toastContainer = document.getElementById('toast-container');
            const showToast = (message, type = 'success') => {
                const toast = document.createElement('div');
                const isSuccess = type === 'success';
                const bgColor = isSuccess ? 'bg-green-500' : 'bg-red-500';
                const icon = isSuccess ? 'check-circle' : 'alert-circle';

                toast.className = `flex items-start text-white p-3 sm:p-4 rounded-lg shadow-lg toast-in ${bgColor}`;
                toast.innerHTML = `<i data-lucide="${icon}" class="w-5 h-5 sm:w-6 sm:h-6 mr-3 flex-shrink-0"></i><p class="flex-1 text-sm sm:text-base">${message}</p>`;
                
                toastContainer.appendChild(toast);
                lucide.createIcons();

                setTimeout(() => {
                    toast.classList.remove('toast-in');
                    toast.classList.add('toast-out');
                    toast.addEventListener('animationend', () => toast.remove());
                }, 5000);
            };

            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const username = urlParams.get('username');
            const errorMsg = urlParams.get('error');
            
            // --- BULK ACTION SCRIPT ---
            const bulkActionManager = {
                state: {
                    queue: {
                        selectedIds: new Set(),
                        inSelectMode: false
                    },
                    manage_accounts: {
                        selectedIds: new Set(),
                        inSelectMode: false
                    }
                },
                
                init(page) {
                    if (!this.state[page]) return;

                    const toggleBtn = document.getElementById(`select-mode-toggle-${page.replace('_', '-')}`);
                    const cancelBtn = document.getElementById('cancel-select-mode-btn');
                    const selectAllCheckbox = document.getElementById('select-all-checkbox');
                    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
                    const bulkDeleteForm = document.getElementById('bulk-delete-form');
                    
                    if (toggleBtn) toggleBtn.addEventListener('click', () => this.toggleSelectMode(page));
                    if (cancelBtn) cancelBtn.addEventListener('click', () => this.exitSelectMode(page));
                    if (selectAllCheckbox) selectAllCheckbox.addEventListener('change', (e) => this.handleSelectAll(page, e.target.checked));
                    if (bulkDeleteBtn) bulkDeleteBtn.addEventListener('click', () => this.prepareBulkDelete(page));

                    document.querySelectorAll(`.bulk-item-checkbox[data-id]`).forEach(cb => {
                        cb.addEventListener('change', (e) => this.handleItemSelect(page, e.target.dataset.id, e.target.checked));
                    });
                    
                    this.restoreState(page);
                },

                toggleSelectMode(page) {
                    this.state[page].inSelectMode = !this.state[page].inSelectMode;
                    if (this.state[page].inSelectMode) {
                        this.enterSelectMode(page);
                    } else {
                        this.exitSelectMode(page);
                    }
                },
                
                enterSelectMode(page) {
                    this.state[page].inSelectMode = true;
                    document.getElementById('bulk-action-bar').classList.remove('translate-y-full');
                    document.querySelectorAll(`.bulk-checkbox-container[data-page="${page}"]`).forEach(c => c.style.display = 'block');
                    this.updateUI(page);
                },

                exitSelectMode(page) {
                    this.state[page].inSelectMode = false;
                    this.state[page].selectedIds.clear();
                    document.getElementById('bulk-action-bar').classList.add('translate-y-full');
                    document.querySelectorAll(`.bulk-checkbox-container[data-page="${page}"]`).forEach(c => c.style.display = 'none');
                    document.querySelectorAll(`.bulk-item-checkbox`).forEach(cb => cb.checked = false);
                    document.getElementById('select-all-checkbox').checked = false;
                },

                handleItemSelect(page, id, isChecked) {
                    if (isChecked) {
                        this.state[page].selectedIds.add(id);
                    } else {
                        this.state[page].selectedIds.delete(id);
                    }
                    this.updateUI(page);
                },

                handleSelectAll(page, isChecked) {
                    document.querySelectorAll(`.bulk-item-checkbox[data-id]`).forEach(cb => {
                        cb.checked = isChecked;
                        this.handleItemSelect(page, cb.dataset.id, isChecked);
                    });
                },

                prepareBulkDelete(page) {
                    const form = document.getElementById('bulk-delete-form');
                    const ids = Array.from(this.state[page].selectedIds);
                    if (ids.length === 0) return;

                    form.innerHTML = ''; // Clear previous inputs
                    form.action = (page === 'queue') ? 'post_action.php' : 'account_action.php';

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'bulk_delete';
                    form.appendChild(actionInput);

                    ids.forEach(id => {
                        const idInput = document.createElement('input');
                        idInput.type = 'hidden';
                        idInput.name = 'ids[]';
                        idInput.value = id;
                        form.appendChild(idInput);
                    });

                    // Use custom confirmation modal
                    const message = `Anda yakin ingin menghapus ${ids.length} item yang dipilih?`;
                    document.getElementById('confirmation-message').textContent = message;
                    window.formToSubmit = form;
                    openModal('confirmationModal');
                },
                
                updateUI(page) {
                    const count = this.state[page].selectedIds.size;
                    document.getElementById('selected-count').textContent = `${count} item dipilih`;
                    document.getElementById('bulk-delete-btn').disabled = count === 0;

                    const allVisibleCheckboxes = document.querySelectorAll(`.bulk-item-checkbox[data-id]`);
                    const allVisibleChecked = Array.from(allVisibleCheckboxes).every(cb => cb.checked);
                    document.getElementById('select-all-checkbox').checked = allVisibleCheckboxes.length > 0 && allVisibleChecked;
                },
                
                restoreState(page) {
                    // This function is simple for now, but could be expanded to use sessionStorage
                    if (this.state[page].inSelectMode) {
                        this.enterSelectMode(page);
                    }
                }
            };

            const currentPage = '<?php echo $page; ?>';
            if (currentPage === 'queue' || currentPage === 'manage_accounts') {
                bulkActionManager.init(currentPage);
            }            

            if (status) {
                const messages = {
                    'post_delete_success': 'Postingan berhasil dihapus.',
                    'update_success': 'Perubahan berhasil disimpan.',
                    'delete_account_success': 'Akun berhasil dihapus.',
                    'auth_success': `Akun @${username} berhasil terhubung.`,
                    'auth_failed': `Otorisasi gagal: ${errorMsg || 'Silakan coba lagi.'}`,
                    'schedule_success': 'Postingan berhasil dijadwalkan.',
                    'bulk_delete_success': 'Item yang dipilih berhasil dihapus.'
                };
                const messageType = status.includes('fail') ? 'error' : 'success';
                if (messages[status]) {
                    showToast(messages[status], messageType);
                }
                
                // Clean the URL
                const newUrl = window.location.pathname + window.location.search
                    .replace(/[\?&]status=[^&]+/, '')
                    .replace(/[\?&]username=[^&]+/, '')
                    .replace(/[\?&]error=[^&]+/, '')
                    .replace(/^&/, '?');
                window.history.replaceState({}, document.title, newUrl);
            }
            
            // --- DARK MODE SCRIPT ---
            const themeToggles = document.querySelectorAll('#theme-toggle-desktop, #theme-toggle-mobile');
            const themeIcons = document.querySelectorAll('.theme-icon');
            const themeText = document.getElementById('theme-text');
            const themes = ['system', 'light', 'dark'];
            let currentThemeIndex = themes.indexOf(localStorage.getItem('theme') || 'system');

            const applyTheme = (theme) => {
                document.documentElement.classList.remove('light', 'dark');
                if (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                } else if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                }
                themeIcons.forEach(icon => icon.classList.toggle('hidden', icon.dataset.themeIcon !== theme));
                if(themeText) themeText.textContent = theme.charAt(0).toUpperCase() + theme.slice(1);
                localStorage.setItem('theme', theme);
            };
            
            applyTheme(themes[currentThemeIndex]);
            themeToggles.forEach(toggle => {
                toggle.addEventListener('click', () => {
                    currentThemeIndex = (currentThemeIndex + 1) % themes.length;
                    applyTheme(themes[currentThemeIndex]);
                });
            });
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (localStorage.getItem('theme') === 'system') applyTheme('system');
            });
        });
    </script>
    
<?php
// Menutup koneksi database di akhir file (jika ada)
if ($conn) {
    $conn->close();
}
?>
</body>
</html>
