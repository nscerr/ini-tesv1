<?php
require_once 'cdn_helper.php';

$cdnBaseUrl = 'https://cdn.videy.co/';
$fileName = isset($_GET['id']) ? basename($_GET['id']) : '';
$fileUrl = '';
$fileExists = false;

if (!empty($fileName)) {
    $potentialFileUrl = $cdnBaseUrl . rawurlencode($fileName);
    
    if (checkRemoteFileExists($potentialFileUrl)) {
        $fileExists = true;
        $fileUrl = $potentialFileUrl;
    }
}

if (!$fileExists) {
    http_response_code(404);
    die("<h1>404 Not Found</h1>");
}

$videoTitle = htmlspecialchars(pathinfo($fileName, PATHINFO_FILENAME));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watch on Videy — Free and Simple Video Hosting</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .video-container { aspect-ratio: 16/9; background: linear-gradient(135deg, #1e293b, #0f172a); }
        .dropdown:hover .dropdown-menu { display: block; }
        .report-modal { transition: all 0.3s ease; }
        .modal-open { opacity: 1; pointer-events: auto; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex flex-col">
    <header class="bg-gray-800 py-4 px-6 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <a href="https://videy.my.id/" class="text-2xl font-bold text-blue-400 hover:text-blue-300 transition">
                    <i class="fas fa-play-circle mr-2"></i>videy
                </a>
            </div>
            <nav class="hidden md:flex space-x-6">
                <a href="https://videy.my.id/" class="hover:text-blue-400 transition flex items-center">
                    <i class="fas fa-upload mr-1"></i> Upload
                </a>
                <a href="#" class="hover:text-blue-400 transition flex items-center">
                    <i class="fas fa-ad mr-1"></i> Advertise
                </a>
            </nav>
            <div class="md:hidden">
                <button id="mobile-menu-button" class="text-gray-300 hover:text-white">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
        </div>
        <div id="mobile-menu" class="hidden md:hidden bg-gray-800 mt-2 py-2 px-4">
            <a href="https://videy.my.id/" class="block py-2 hover:text-blue-400 transition"><i class="fas fa-upload mr-2"></i> Upload</a>
            <a href="#" class="block py-2 hover:text-blue-400 transition"><i class="fas fa-ad mr-2"></i> Advertise</a>
        </div>
    </header>

    <main class="flex-grow p-4 md:p-6">
        <div class="max-w-4xl mx-auto">
            <div class="video-container w-full rounded-xl overflow-hidden shadow-2xl mb-6 relative">
                <video controls class="w-full h-full" controlslist="nodownload" oncontextmenu="return false" autoplay>
                    <source src="<?php echo $fileUrl; ?>" type="video/mp4">
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="text-center">
                            <i class="fas fa-play-circle text-6xl text-blue-400 opacity-70"></i>
                            <p class="mt-4 text-gray-300">Your browser does not support the video tag.</p>
                        </div>
                    </div>
                </video>
            </div>
            
            <div class="flex flex-wrap justify-between items-center mb-8 gap-4">
                <div class="flex space-x-4">
                    <button id="like-btn" class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded-full transition flex items-center">
                        <i class="far fa-thumbs-up mr-2"></i> Like
                    </button>
                    <button id="share-btn" class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded-full transition flex items-center">
                        <i class="fas fa-share mr-2"></i> Share Link
                    </button>
                </div>
                <button id="report-btn" class="bg-gray-800 hover:bg-red-600 px-4 py-2 rounded-full transition flex items-center">
                    <i class="fas fa-flag mr-2"></i> Report
                </button>
            </div>
            
            <div class="mb-8">
                <h2 class="text-xl font-bold mb-4 flex items-center"><i class="fas fa-film mr-2"></i> Get more like this</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <a href="https://google.com" class="bg-gray-800 rounded-lg overflow-hidden hover:scale-105 transition cursor-pointer">
                        <div class="aspect-video relative">
                            <img src="https://picsum.photos/640/360?random=1.jpg" alt="Video thumbnail 1" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
                                <i class="fas fa-play text-4xl text-blue-400 opacity-90 drop-shadow-lg"></i>
                            </div>
                        </div>
                        <div class="p-3">
                            <h3 class="font-medium truncate">Sample Video Title 1</h3>
                            <p class="text-sm text-gray-400">120K views</p>
                        </div>
                    </a>
                    <a href="https://google.com" class="bg-gray-800 rounded-lg overflow-hidden hover:scale-105 transition cursor-pointer">
                        <div class="aspect-video relative">
                            <img src="https://picsum.photos/640/360?random=2.jpg" alt="Video thumbnail 2" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
                                <i class="fas fa-play text-4xl text-blue-400 opacity-90 drop-shadow-lg"></i>
                            </div>
                        </div>
                        <div class="p-3">
                            <h3 class="font-medium truncate">Sample Video Title 2</h3>
                            <p class="text-sm text-gray-400">85K views</p>
                        </div>
                    </a>
                    <a href="https://google.com" class="bg-gray-800 rounded-lg overflow-hidden hover:scale-105 transition cursor-pointer">
                        <div class="aspect-video relative">
                            <img src="https://picsum.photos/640/360?random=3.jpg" alt="Video thumbnail 3" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
                                <i class="fas fa-play text-4xl text-blue-400 opacity-90 drop-shadow-lg"></i>
                            </div>
                        </div>
                        <div class="p-3">
                            <h3 class="font-medium truncate">Sample Video Title 3</h3>
                            <p class="text-sm text-gray-400">210K views</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-gray-800 py-6 px-6 border-t border-gray-700">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <p class="text-gray-400">Copyright © 2025 videy</p>
                </div>
                <div class="flex space-x-6">
                    <a href="#" class="text-gray-400 hover:text-blue-400 transition">Terms of Service</a>
                    <a href="#" class="text-gray-400 hover:text-blue-400 transition">Report Abuse</a>
                </div>
            </div>
        </div>
    </footer>

    <div id="report-modal" class="report-modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 opacity-0 pointer-events-none">
        <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4 shadow-xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Report Content</h3>
                <button id="close-modal" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
            </div>
            <p class="mb-6 text-gray-300">What's wrong with this content?</p>
            <div class="space-y-3">
                <label class="flex items-center space-x-3 cursor-pointer"><input type="radio" name="report" class="form-radio text-blue-500"><span>I don't like it</span></label>
                <label class="flex items-center space-x-3 cursor-pointer"><input type="radio" name="report" class="form-radio text-blue-500"><span>It is hateful or offensive</span></label>
                <label class="flex items-center space-x-3 cursor-pointer"><input type="radio" name="report" class="form-radio text-blue-500"><span>Child Exploitation Material (CSAM)</span></label>
                <label class="flex items-center space-x-3 cursor-pointer"><input type="radio" name="report" class="form-radio text-blue-500"><span>It is illegal</span></label>
            </div>
            <button onclick="window.location.href='https://google.com'" class="mt-6 w-full bg-blue-600 hover:bg-blue-700 py-2 rounded transition">Submit Report</button>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        mobileMenuButton.addEventListener('click', () => { mobileMenu.classList.toggle('hidden'); });
        
        // Report modal
        const reportBtn = document.getElementById('report-btn');
        const closeModal = document.getElementById('close-modal');
        const reportModal = document.getElementById('report-modal');
        reportBtn.addEventListener('click', () => {
            reportModal.classList.add('modal-open');
            reportModal.classList.remove('opacity-0');
            reportModal.classList.remove('pointer-events-none');
        });
        closeModal.addEventListener('click', () => {
            reportModal.classList.remove('modal-open');
            reportModal.classList.add('opacity-0');
            reportModal.classList.add('pointer-events-none');
        });
        
        // Share button
        const shareBtn = document.getElementById('share-btn');
        shareBtn.addEventListener('click', () => {
            navigator.clipboard.writeText(window.location.href)
                .then(() => {
                    shareBtn.innerHTML = '<i class="fas fa-check mr-2"></i> Copied!';
                    setTimeout(() => { shareBtn.innerHTML = '<i class="fas fa-share mr-2"></i> Share Link'; }, 2000);
                })
                .catch(err => {
                    console.error('Failed to copy: ', err);
                    shareBtn.innerHTML = '<i class="fas fa-times mr-2"></i> Error';
                    setTimeout(() => { shareBtn.innerHTML = '<i class="fas fa-share mr-2"></i> Share Link'; }, 2000);
                });
        });

        // Like button
        const likeBtn = document.getElementById('like-btn');
        likeBtn.addEventListener('click', () => {
            likeBtn.classList.toggle('text-blue-400');
            likeBtn.innerHTML = likeBtn.classList.contains('text-blue-400') ? 
                '<i class="fas fa-thumbs-up mr-2"></i> Liked' : 
                '<i class="far fa-thumbs-up mr-2"></i> Like';
        });
    </script>
</body>
</html>





<?php
require_once 'cdn_helper.php';

$cdnBaseUrl = 'https://cdn.videy.co/';
$fileName = isset($_GET['id']) ? basename($_GET['id']) : '';
$fileUrl = '';
$fileExists = false;

// Enhanced security: Validate file extension
$allowedExtensions = ['mp4', 'webm', 'mov', 'avi'];
$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

if (!empty($fileName) && in_array(strtolower($fileExtension), $allowedExtensions)) {
    $sanitizedFileName = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', $fileName);
    $potentialFileUrl = $cdnBaseUrl . rawurlencode($sanitizedFileName);
    
    if (checkRemoteFileExists($potentialFileUrl)) {
        $fileExists = true;
        $fileUrl = $potentialFileUrl;
    }
}

if (!$fileExists) {
    http_response_code(404);
    // Enhanced 404 page with requested styling changes
    die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>404 Not Found - Videy</title><script src="https://cdn.tailwindcss.com"></script><style>body{background:linear-gradient(135deg,#1e293b,#0f172a);min-height:100vh;display:flex;align-items:center;justify-content:center}</style></head><body><div class="text-center p-8 rounded-xl shadow-2xl"><h1 class="text-3xl font-bold text-red-400 mb-4"><i class="fas fa-exclamation-triangle mr-2"></i>404 Not Found</h1><p class="text-xl mb-6 text-white">The requested video does not exist</p><a href="https://videy.my.id/" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-full transition"><i class="fas fa-arrow-left mr-2"></i>Return to Homepage</a></div></body></html>');
}

$videoTitle = htmlspecialchars(pathinfo($fileName, PATHINFO_FILENAME));

// Determine MIME type dynamically
$mimeTypes = [
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'mov' => 'video/quicktime',
    'avi' => 'video/x-msvideo'
];
$videoType = $mimeTypes[strtolower($fileExtension)] ?? 'video/mp4';

?>
