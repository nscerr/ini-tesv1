// Video Data
const videos = [
    {
        id: 1,
        title: "ngapain tuhh",
        thumbnail: "https://cdn.jsdelivr.net/gh/vinzvid/tes@main/images/sezora-m08.webp",
        duration: "01:17",
        category: "viral",
        isNew: true,
        views: "245K",
        src: "https://cdn.videy.co/9UNy95XN1.mp4"
    },
    {
        id: 2,
        title: "anjiddd",
        thumbnail: "https://cdn.jsdelivr.net/gh/vinzvid/tes@main/images/wucipo-kh0.webp",
        duration: "02:18",
        category: "indo",
        isNew: true,
        views: "189K",
        src: "https://cdn.videy.co/4uNZJYT21.mp4"
    },
    {
        id: 3,
        title: "Testing Video Ke-16: Eksplorasi Fitur Dashboard Terbaru",
        thumbnail: "https://picsum.photos/seed/v16/400/225",
        duration: "12:45",
        category: "barat",
        isNew: false,
        views: "56K",
        src: "https://example.com/video16"
    },
    {
        id: 4,
        title: "Testing Video Ke-17",
        thumbnail: "https://picsum.photos/seed/v17/400/225",
        duration: "04:20",
        category: "asian",
        isNew: false,
        views: "34K",
        src: "https://example.com/video17"
    },
    {
        id: 5,
        title: "Testing Video Ke-18",
        thumbnail: "https://picsum.photos/seed/v18/400/225",
        duration: "09:15",
        category: "viral",
        isNew: false,
        views: "892K",
        src: "https://example.com/video18"
    },
    {
        id: 6,
        title: "Testing Video Ke-19",
        thumbnail: "https://picsum.photos/seed/v19/400/225",
        duration: "15:30",
        category: "indo",
        isNew: false,
        views: "123K",
        src: "https://example.com/video19"
    },
    {
        id: 7,
        title: "Testing Video Ke-20: Judul Sangat Panjang Sekali Untuk Mengetahui Batas Maksimal Tampilan Grid Pada Mobile Device",
        thumbnail: "https://picsum.photos/seed/v20/400/225",
        duration: "08:50",
        category: "barat",
        isNew: false,
        views: "67K",
        src: "https://example.com/video20"
    },
    {
        id: 8,
        title: "Testing Video Ke-21 (Halaman 3)",
        thumbnail: "https://picsum.photos/seed/v21/400/225",
        duration: "06:10",
        category: "asian",
        isNew: false,
        views: "45K",
        src: "https://example.com/video21"
    },
    {
        id: 9,
        title: "Testing Video Ke-22",
        thumbnail: "https://picsum.photos/seed/v22/400/225",
        duration: "11:05",
        category: "viral",
        isNew: false,
        views: "234K",
        src: "https://example.com/video22"
    },
    {
        id: 10,
        title: "Testing Video Ke-23",
        thumbnail: "https://picsum.photos/seed/v23/400/225",
        duration: "03:45",
        category: "indo",
        isNew: false,
        views: "89K",
        src: "https://example.com/video23"
    },
    {
        id: 11,
        title: "Testing Video Ke-24",
        thumbnail: "https://picsum.photos/seed/v24/400/225",
        duration: "07:20",
        category: "barat",
        isNew: false,
        views: "156K",
        src: "https://example.com/video24"
    },
    {
        id: 12,
        title: "Testing Video Ke-25",
        thumbnail: "https://picsum.photos/seed/v25/400/225",
        duration: "14:40",
        category: "asian",
        isNew: false,
        views: "78K",
        src: "https://example.com/video25"
    }
];

let currentCategory = 'all';
let displayedVideos = 8;
let currentVideoIndex = 0;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    renderVideos();
    
    // Handle back button on mobile
    window.addEventListener('popstate', (e) => {
        const modal = document.getElementById('videoModal');
        if (!modal.classList.contains('hidden')) {
            e.preventDefault();
            closeVideo();
        }
    });
});

// Render Videos
function renderVideos(append = false) {
    const grid = document.getElementById('videoGrid');
    if (!append) grid.innerHTML = '';
    
    const filtered = currentCategory === 'all' 
        ? videos 
        : videos.filter(v => v.category === currentCategory);
    
    const toShow = filtered.slice(0, displayedVideos);
    
    toShow.forEach((video, index) => {
        const card = createVideoCard(video);
        grid.appendChild(card);
        
        // Stagger animation
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 50);
    });
    
    // Hide load more if all shown
    if (displayedVideos >= filtered.length) {
        document.getElementById('loadMoreBtn').style.display = 'none';
    } else {
        document.getElementById('loadMoreBtn').style.display = 'flex';
    }
    
    lucide.createIcons();
}

// Create Video Card HTML
function createVideoCard(video) {
    const div = document.createElement('div');
    div.className = 'video-card opacity-0 translate-y-4 cursor-pointer group';
    div.onclick = () => openVideo(video);
    
    div.innerHTML = `
        <div class="relative aspect-[16/9] rounded-xl overflow-hidden bg-gray-800 mb-2">
            <img src="${video.thumbnail}" 
                 alt="${video.title}" 
                 class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                 loading="lazy">
            
            <!-- Play Overlay -->
            <div class="play-overlay absolute inset-0 bg-black/40 opacity-0 transition-opacity duration-300 flex items-center justify-center">
                <div class="w-12 h-12 bg-amber rounded-full flex items-center justify-center shadow-lg transform scale-90 group-hover:scale-100 transition-transform">
                    <i data-lucide="play" class="w-5 h-5 text-black fill-current ml-0.5"></i>
                </div>
            </div>
            
            <!-- Duration Badge -->
            <div class="absolute bottom-2 right-2 px-2 py-1 bg-black/80 rounded-md text-xs font-medium text-white backdrop-blur-sm">
                ${video.duration}
            </div>
            
            <!-- New Badge -->
            ${video.isNew ? `
            <div class="absolute top-2 left-2 px-2 py-1 bg-amber text-black rounded-md text-[10px] font-bold uppercase tracking-wider">
                NEW
            </div>
            ` : ''}
            
            <!-- Viral Badge -->
            <div class="absolute top-2 right-2 px-2 py-1 bg-red-600/90 text-white rounded-md text-[10px] font-bold uppercase tracking-wider flex items-center gap-1">
                <i data-lucide="flame" class="w-3 h-3"></i> Viral
            </div>
        </div>
        
        <div class="space-y-1">
            <h3 class="text-sm font-semibold text-white line-clamp-2 leading-tight group-hover:text-amber transition-colors">
                ${video.title}
            </h3>
            <div class="flex items-center gap-2 text-xs text-gray-400">
                <span>${video.views} views</span>
                <span class="w-1 h-1 bg-gray-600 rounded-full"></span>
                <span>${video.category.toUpperCase()}</span>
            </div>
        </div>
    `;
    
    return div;
}

// Filter Category
function filterCategory(category) {
    currentCategory = category;
    displayedVideos = 8;
    
    // Update active button
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.remove('category-active');
        if (btn.textContent.toLowerCase().includes(category) || 
            (category === 'all' && btn.textContent === 'Semua')) {
            btn.classList.add('category-active');
        }
    });
    
    // Show loading
    const grid = document.getElementById('videoGrid');
    grid.style.opacity = '0.5';
    
    setTimeout(() => {
        renderVideos();
        grid.style.opacity = '1';
    }, 200);
}

// Load More
function loadMore() {
    const loadingState = document.getElementById('loadingState');
    const btn = document.getElementById('loadMoreBtn');
    
    btn.style.display = 'none';
    loadingState.classList.remove('hidden');
    
    setTimeout(() => {
        displayedVideos += 4;
        renderVideos(true);
        loadingState.classList.add('hidden');
    }, 800);
}

// Open Video Modal
function openVideo(video) {
    const modal = document.getElementById('videoModal');
    const videoEl = document.getElementById('mainVideo');
    const title = document.getElementById('videoTitle');
    const views = document.getElementById('videoViews');
    
    videoEl.src = video.src;
    title.textContent = video.title;
    views.textContent = video.views + ' views';
    
    modal.classList.remove('hidden');
    modal.classList.add('modal-enter');
    
    // Auto play after short delay
    setTimeout(() => {
        videoEl.play().catch(e => console.log('Autoplay prevented'));
        document.getElementById('customPlayOverlay').style.display = 'none';
    }, 500);
    
    // Push state for back button handling
    history.pushState({ video: true }, '');
    
    lucide.createIcons();
}

// Close Video
function closeVideo() {
    const modal = document.getElementById('videoModal');
    const video = document.getElementById('mainVideo');
    
    video.pause();
    video.currentTime = 0;
    modal.classList.add('hidden');
    modal.classList.remove('modal-enter');
    document.getElementById('customPlayOverlay').style.display = 'flex';
    
    if (history.state && history.state.video) {
        history.back();
    }
}

// Toggle Play
function togglePlay() {
    const video = document.getElementById('mainVideo');
    const overlay = document.getElementById('customPlayOverlay');
    
    if (video.paused) {
        video.play();
        overlay.style.display = 'none';
    } else {
        video.pause();
        overlay.style.display = 'flex';
    }
}

// Quick Exit Function
function quickExit() {
    // Clear history and redirect to neutral site
    if (confirm('Tutup aplikasi?')) {
        // Clear local storage
        localStorage.clear();
        sessionStorage.clear();
        
        // Replace current history
        window.location.replace('https://www.google.com');
        
        // Alternative: Close window if possible
        window.close();
        
        // If can't close, show blank page
        setTimeout(() => {
            document.body.innerHTML = '';
            document.title = 'New Tab';
            window.location.href = 'about:blank';
        }, 100);
    }
}

// Toggle Search
function toggleSearch() {
    const searchBar = document.getElementById('searchBar');
    if (searchBar.classList.contains('-translate-y-full')) {
        searchBar.classList.remove('-translate-y-full');
        searchBar.querySelector('input').focus();
    } else {
        searchBar.classList.add('-translate-y-full');
    }
}

// Set Active Navigation
function setActiveNav(btn) {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('text-amber');
        item.classList.add('text-gray-400');
    });
    btn.classList.remove('text-gray-400');
    btn.classList.add('text-amber');
}

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    // ESC to close video
    if (e.key === 'Escape') {
        closeVideo();
    }
    
    // Space to toggle play when video open
    if (e.code === 'Space' && !document.getElementById('videoModal').classList.contains('hidden')) {
        e.preventDefault();
        togglePlay();
    }
});

// Handle visibility change (pause video when tab hidden)
document.addEventListener('visibilitychange', () => {
    const video = document.getElementById('mainVideo');
    if (document.hidden && video && !video.paused) {
        video.pause();
    }
});
