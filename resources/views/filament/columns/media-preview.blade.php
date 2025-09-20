@php
    $record = $getRecord();
    $mediaType = $record->media_type ?? null;
    $mediaUrl = $record->media_url ?? null;
@endphp

@if($mediaUrl)
    @if($mediaType === 'image')
        <div class="image-preview-container">
            <img 
                src="{{ $mediaUrl }}" 
                alt="Property Image" 
                class="object-cover rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 cursor-pointer hover:opacity-80 transition-opacity"
                style="width: 120px; height: 60px; max-width: 120px; max-height: 60px;"
                onclick="window.open('{{ $mediaUrl }}', '_blank')"
            />
        </div>
    @elseif($mediaType === 'video')
        <div class="video-player-container">
            <video 
                width="128" 
                height="96" 
                controls 
                preload="metadata"
                class="rounded-lg shadow-sm border border-gray-200 dark:border-gray-700"
                style="max-width: 100%; height: auto;"
            >
                <source src="{{ $mediaUrl }}" type="video/mp4">
                <source src="{{ $mediaUrl }}" type="video/webm">
                <source src="{{ $mediaUrl }}" type="video/ogg">
                Your browser does not support the video tag.
            </video>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 text-center">
                <a 
                    href="{{ $mediaUrl }}" 
                    target="_blank" 
                    class="text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                >
                    Open in new tab
                </a>
            </div>
        </div>
    @else
        <div class="flex items-center justify-center w-32 h-24 bg-gray-100 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="text-center">
                <svg class="w-8 h-8 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <p class="mt-1 text-xs text-gray-500">Unknown type</p>
            </div>
        </div>
    @endif
@else
    <div class="flex items-center justify-center w-32 h-24 bg-gray-100 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="text-center">
            <svg class="w-8 h-8 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="mt-1 text-xs text-gray-500">No media</p>
        </div>
    </div>
@endif