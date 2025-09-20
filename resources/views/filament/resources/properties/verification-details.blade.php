<div class="p-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div>
            <h3 class="text-lg font-semibold mb-2">Verification Details</h3>
            <dl class="space-y-2">
                <div>
                    <dt class="font-medium text-gray-700">Verification ID:</dt>
                    <dd class="text-gray-900">{{ $verification->id }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-700">Agent:</dt>
                    <dd class="text-gray-900">{{ $verification->agent ? $verification->agent->name : 'Unknown Agent' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-700">Status:</dt>
                    <dd>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            {{ $verification->status === 'verified' ? 'bg-green-100 text-green-800' : 
                               ($verification->status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                            {{ ucfirst($verification->status ?? 'pending') }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-700">Verification Date:</dt>
                    <dd class="text-gray-900">{{ $verification->verification_date ? $verification->verification_date->format('M d, Y H:i') : 'Not set' }}</dd>
                </div>
            </dl>
        </div>
        
        <div>
            <h3 class="text-lg font-semibold mb-2">Location</h3>
            <dl class="space-y-2">
                <div>
                    <dt class="font-medium text-gray-700">Latitude:</dt>
                    <dd class="text-gray-900">{{ $verification->latitude ?? 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-700">Longitude:</dt>
                    <dd class="text-gray-900">{{ $verification->longitude ?? 'Not set' }}</dd>
                </div>
                @if($verification->latitude && $verification->longitude)
                <div>
                    <dt class="font-medium text-gray-700">Coordinates:</dt>
                    <dd class="text-gray-900 text-sm font-mono">{{ number_format($verification->latitude, 6) }}, {{ number_format($verification->longitude, 6) }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </div>
    
    @if($verification->verification_notes)
    <div class="mb-6">
        <h3 class="text-lg font-semibold mb-2">Notes</h3>
        <div class="bg-gray-50 p-3 rounded-lg">
            <p class="text-gray-900">{{ $verification->verification_notes }}</p>
        </div>
    </div>
    @endif
    
    @if($verification->status === 'rejected' && $verification->rejection_reason)
    <div class="mb-6">
        <h3 class="text-lg font-semibold mb-2">Rejection Reason</h3>
        <div class="bg-red-50 border border-red-200 p-3 rounded-lg">
            <p class="text-red-900">{{ $verification->rejection_reason }}</p>
        </div>
    </div>
    @endif
    
    @if(is_array($verification->verification_images) && !empty($verification->verification_images))
    <div>
        <h3 class="text-lg font-semibold mb-3">Verification Images ({{ count($verification->verification_images) }})</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach($verification->verification_images as $index => $imageUrl)
            <div class="group relative">
                <img src="{{ $imageUrl }}" 
                     alt="Verification Image {{ $index + 1 }}"
                     class="w-full h-32 object-cover rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow cursor-pointer"
                     onclick="window.open('{{ $imageUrl }}', '_blank')">
                <div class="absolute bottom-2 left-2 bg-black bg-opacity-75 text-white text-xs px-2 py-1 rounded">
                    Image {{ $index + 1 }}
                </div>
            </div>
            @endforeach
        </div>
        <p class="text-sm text-gray-500 mt-2">Click on any image to view full size</p>
    </div>
    @else
    <div class="text-center py-8 text-gray-500">
        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <p class="mt-2">No verification images available</p>
    </div>
    @endif
</div>