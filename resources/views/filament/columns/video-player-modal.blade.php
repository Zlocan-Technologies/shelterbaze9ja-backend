@if($getRecord()->media_type === 'video')
    <div class="video-player-container">
        <!-- Thumbnail with play button -->
        <div 
            class="relative cursor-pointer group"
            onclick="openVideoModal('{{ $getRecord()->id }}')"
        >
            <video 
                width="200" 
                height="150" 
                preload="metadata"
                class="rounded-lg shadow-sm border border-gray-200 dark:border-gray-700"
                style="max-width: 100%; height: auto;"
                muted
            >
                <source src="{{ $getRecord()->media_url }}#t=1" type="video/mp4">
            </video>
            
            <!-- Play button overlay -->
            <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-30 rounded-lg group-hover:bg-opacity-50 transition-all">
                <svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                </svg>
            </div>
        </div>
        
        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 text-center">
            Click to play video
        </div>
    </div>

    <!-- Modal for full-screen video -->
    <div 
        id="video-modal-{{ $getRecord()->id }}" 
        class="fixed inset-0 bg-black bg-opacity-75 z-50 hidden items-center justify-center p-4"
        onclick="closeVideoModal('{{ $getRecord()->id }}')"
    >
        <div class="relative max-w-4xl w-full" onclick="event.stopPropagation()">
            <button 
                onclick="closeVideoModal('{{ $getRecord()->id }}')"
                class="absolute -top-10 right-0 text-white hover:text-gray-300 text-2xl font-bold"
            >
                ×
            </button>
            <video 
                id="modal-video-{{ $getRecord()->id }}"
                width="100%" 
                controls 
                autoplay
                class="rounded-lg"
            >
                <source src="{{ $getRecord()->media_url }}" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div>
    </div>

    <script>
        function openVideoModal(recordId) {
            const modal = document.getElementById('video-modal-' + recordId);
            const video = document.getElementById('modal-video-' + recordId);
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            video.currentTime = 0;
        }

        function closeVideoModal(recordId) {
            const modal = document.getElementById('video-modal-' + recordId);
            const video = document.getElementById('modal-video-' + recordId);
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            video.pause();
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('[id^="video-modal-"]');
                modals.forEach(modal => {
                    if (!modal.classList.contains('hidden')) {
                        const recordId = modal.id.replace('video-modal-', '');
                        closeVideoModal(recordId);
                    }
                });
            }
        });
    </script>
@endif