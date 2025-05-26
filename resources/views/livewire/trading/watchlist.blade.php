<div>
    <!-- resources/views/livewire/trading/watchlist.blade.php -->
    <div class="p-2 font-bold text-white bg-blue-600 rounded-t-xl">
        <h2>Trading Watchlist</h2>
    </div>

    <div class="overflow-y-auto" style="height: calc(100% - 3rem)">
        <ul class="divide-y divide-gray-100">
            @forelse($pairs as $pair)
            <li
            wire:key="pair-{{ $pair->id }}"
            wire:click="selectPair({{ $pair->id }})"
            @class([
                // always-on utilities
                'p-3 cursor-pointer transition duration-150 border-l-4 hover:bg-gray-100',
        
                // zebra striping
                'bg-gray-50' => $loop->odd,
                'bg-white'   => $loop->even,
            ])>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-900">
                                {{ $pair->formatted_name }}
                            </p>
                            <p class="text-sm text-gray-00">
                                {{ $pair->base_currency }}/{{ $pair->quote_currency }}
                            </p>                  
                            <div class="text-left text-xs">
                                ${{ number_format($pair->price, 8) }}
                            </div>   
                        </div>       
                        <div class="text-right">
                            <p class="font-medium">
                                {{ $pair->interval ?? '–' }}M
                            </p>
                        </div>
                    </div>
                </li>
            @empty
                <li class="p-3 text-gray-500">
                    No pairs in your watchlist.
                </li>
            @endforelse
        </ul>
    </div>
</div>
