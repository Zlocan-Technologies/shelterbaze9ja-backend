@if($getRecord()->media_type === 'video' && $getRecord()->media_url)
    <div class="video-player-container">
        <video 
            width="200" 
            height="150" 
            controls 
            preload="metadata"
            class="rounded-lg shadow-sm border border-gray-200 dark:border-gray-700"
            style="max-width: 100%; height: auto;"
        >
            <source src="{{ $getRecord()->media_url }}" type="video/mp4">
            <source src="{{ $getRecord()->media_url }}" type="video/webm">
            <source src="{{ $getRecord()->media_url }}" type="video/ogg">
            Your browser does not support the video tag.
        </video>
        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            <a 
                href="{{ $getRecord()->media_url }}" 
                target="_blank" 
                class="text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
            >
                Open in new tab
            </a>
        </div>
    </div>
@else
    <div class="text-xs text-gray-500 dark:text-gray-400">
        No video available
    </div>
@endif