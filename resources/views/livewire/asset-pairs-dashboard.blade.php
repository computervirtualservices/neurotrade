
    <div class="m-2  border border-neutral-200 dark:border-neutral-700 bg-white rounded-lg">
        <div class="flex flex-col items-start justify-between gap-4 mb-6 sm:flex-row sm:items-center">
            <div class="p-4">
                <h1 class="text-2xl font-bold text-gray-800">Crypto Asset Pairs Watchlist</h1>
                <p class="text-gray-600">Data sourced from Kraken API</p>
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
                    <input wire:model.live="showWatchlistedOnly" type="checkbox" id="watchlistedOnly"
                        wire:change="resetPage"
                        class="mr-2 text-blue-600 border-gray-300 rounded shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <label for="watchlistedOnly" class="text-gray-700">Show watchlisted only</label>
                </div>
                <button wire:click="fetchAssetPairsFromKraken" wire:loading.attr="disabled"
                    class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 active:bg-blue-600 disabled:opacity-50">
                    <svg wire:loading wire:target="fetchAssetPairsFromKraken"
                        class="w-4 h-4 mr-2 -ml-1 text-white animate-spin" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    Refresh Data
                </button>
            </div>
        </div>
        @if (session()->has('message'))
            <div class="p-4 mb-4 text-green-700 bg-green-100 rounded-lg">
                {{ session('message') }}
            </div>
        @endif
        @if ($errorMessage)
            <div class="p-4 mb-4 text-red-700 bg-red-100 rounded-lg">
                {{ $errorMessage }}
            </div>
        @endif
        <div class="overflow-hidden bg-white rounded-lg shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                <button wire:click="sortBy('pair_name')" class="flex items-center">
                                    Asset Pair
                                    @if ($sortField === 'pair_name')
                                        <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            @if ($sortDirection === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="2" d="M5 15l7-7 7 7"></path>
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            @endif
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                <button wire:click="sortBy('base_currency')" class="flex items-center">
                                    Base Currency
                                    @if ($sortField === 'base_currency')
                                        <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            @if ($sortDirection === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="2" d="M5 15l7-7 7 7"></path>
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            @endif
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                <button wire:click="sortBy('quote_currency')" class="flex items-center">
                                    Quote Currency
                                    @if ($sortField === 'quote_currency')
                                        <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            @if ($sortDirection === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="2" d="M5 15l7-7 7 7"></path>
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            @endif
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">
                                Interval
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">
                                Watchlist
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($assetPairs as $index => $pair)
                            <tr class="{{ $index % 2 ? 'bg-gray-50' : 'bg-white' }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $pair->display_name }}</div>
                                    <div class="text-sm text-gray-500">{{ $pair->pair_name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $pair->base_currency }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $pair->quote_currency }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 whitespace-nowrap">
                                    <select
                                        wire:change="updateLogInterval({{ $pair->id }}, $event.target.value)"
                                        class="border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        @foreach (\App\Helpers\OHLCIndicators::all() as $label => $minutes)
                                            <option value="{{ $minutes }}"
                                                {{ $pair->interval == $minutes ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <label class="flex items-center justify-center">
                                        <input type="checkbox" wire:click="toggleWatchlist({{ $pair->id }})"
                                            wire:key="watch-{{ $pair->id }}"
                                            {{ $pair->is_watchlisted ? 'checked' : '' }}
                                            class="w-5 h-5 text-blue-600 border-gray-300 rounded shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    </label>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                    @if ($refreshing)
                                        <div class="flex items-center justify-center">
                                            <svg class="w-5 h-5 mr-3 text-gray-500 animate-spin"
                                                xmlns="http://www.w3.org/2000/svg" fill="none"
                                                viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                </path>
                                            </svg>
                                            Loading asset pairs...
                                        </div>
                                    @else
                                        No asset pairs found.
                                        <button wire:click="fetchAssetPairsFromKraken"
                                            class="text-blue-600 underline">Refresh data</button>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 bg-white border-t border-gray-200 sm:px-6">
                {{ $assetPairs->links() }}
            </div>
        </div>
    </div>
