<div class="m-2 border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 rounded-lg">
     <div class="flex flex-col items-start justify-between gap-4 mb-6 sm:flex-row sm:items-center">
        <div class="p-4">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                Profit Report Dashboard 
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ $totalRows }} assets found.
            </p>
        </div>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <input wire:model.debounce.300ms="search" wire:input.debounce.300ms="handleSearchUpdate"
                        type="text" placeholder="Search assets..."
                        class="py-2 pl-10 pr-4 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <div class="absolute left-3 top-2.5 text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
                <div class="flex items-center">
                    <input wire:model.live="showOpenSideOnly" type="checkbox" id="openSideOnly"
                        class="mr-2 text-blue-600 border-gray-300 rounded shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <label for="openSideOnly" class="text-gray-700">Show Open Side</label>
                </div>
                <div class="flex items-center">
                    <input wire:model.live="showCompleteSideOnly" type="checkbox" id="completeSideOnly"
                        class="mr-2 text-blue-600 border-gray-300 rounded shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <label for="completeSideOnly" class="text-gray-700">Show Complete Side</label>
                </div>
        </div>
        </div>

        <div wire:poll.3s="updatePositions" class="h-full w-full flex flex-col rounded-md border">
            <!-- Header -->
            <div class="grid grid-cols-6 bg-gray-100 text-xs font-medium uppercase text-gray-500">
                <div class="py-2 px-4 text-left">Date</div>
                <div class="py-2 px-4 text-left">Pair</div>
                <div class="py-2 px-4 text-center">Side</div>
                <div class="py-2 px-4 text-right">Entry</div>
                <div class="py-2 px-4 text-right">Exit</div>
                <div class="py-2 px-4 text-right">
                    P&amp;L
                    (Total: {{ number_format($totalPlPct, 2) }}%)
                </div>
            </div>

            <!-- Scrollable Rows -->
            <div class="flex-1 overflow-y-auto">
                <div class="divide-y divide-gray-200">
                    @forelse($rows as $row)
                        <div wire:click="selectRowPair('{{ $row['pair'] }}', {{ $row['id'] }})"
                            class="grid grid-cols-6 text-sm text-center bg-white odd:bg-white even:bg-gray-50 hover:bg-gray-100 transition-colors cursor-pointer py-2 px-4">
                            <div class="text-left">{{ $row['date'] }}</div>
                            <div class="text-left font-medium">{{ $row['pair'] }}</div>
                            <div>{{ $row['side'] }}</div>
                            <div class="text-right">{{ number_format($row['entry'], 8) }}</div>
                            <div class="text-right">{{ number_format($row['exit'], 8) }}</div>
                            <div class="text-right font-semibold {{ $row['plClass'] }}">
                                {{ number_format($row['plAbs'], 8) }}<br>
                                <span class="ml-1 text-xs">({{ $row['plPct'] }}%)</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-4 text-gray-500 text-center">
                            No trades found.
                        </div>
                    @endforelse
                </div>
                
            </div>
        </div>
        <div class="px-4 py-3 bg-white border-t border-gray-200 sm:px-6">
                {{ $rows->links() }}
            </div>
    </div>
</div>

            