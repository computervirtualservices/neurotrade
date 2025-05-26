{{-- resources/views/livewire/trading-dashboard.blade.php --}}
<div class="h-screen grid grid-cols-1 lg:grid-cols-3 gap-4 ">
    <!-- LEFT (2/3 width) now a single row split 2∶1 -->
    <div class="grid grid-cols-[2fr,1fr] gap-4 lg:col-span-2 min-h-0">
        <!-- ─── Chart ( 2 / 3 ) ───────────────────────────────────────────── -->
        <div
            class="relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 mt-2 ml-2 bg-white min-h-0">
            <livewire:trading.trade-chart class="h-full w-full" />
        </div>

        <!-- ─── Positions / Trade Log ( 1 / 3 ) ───────────────────────────── -->
        <div
            class="relative flex flex-col rounded-xl border border-neutral-200 dark:border-neutral-700 mb-2 ml-2 bg-white min-h-0">
            <!-- optional header -->
            <div class="shrink-0 p-2 border-b text-sm font-semibold">
                Open Positions
            </div>

            <!-- Livewire table: fills remaining space and scrolls -->
            <div class="flex-1 overflow-y-auto">
                <livewire:trading.trade-positions /> {{-- component outputs ONLY the <table> --}}
            </div>
        </div>
    </div>

        <!-- RIGHT (1/3 width) one column with 3 stacked cards -->
        <div class="flex flex-col gap-4 lg:col-span-1 overflow-auto">
            <!-- Trade Form -->
            <div
                class="flex-1 relative overflow-auto rounded-xl border border-neutral-200 dark:border-neutral-700 mt-2 mr-2 bg-white">
                <div class="p-4">
                    <livewire:trading.watchlist />
                </div>
            </div>

            <!-- Order Book -->
            <div
                class="flex-1 relative overflow-auto rounded-xl border border-neutral-200 dark:border-neutral-700 mr-2 bg-white">
                <div class="p-4">
                    <livewire:trading.asset-information />
                </div>
            </div>

            <!-- Market Ticker -->
            <div
                class="flex-1 relative overflow-auto rounded-xl border border-neutral-200 dark:border-neutral-700 mb-2 mr-2 bg-white">
                <div class="p-4">
                    <livewire:trading.trade-signal-details />
                </div>
            </div>
        </div>
    </div>

