<div wire:poll.3s="updatePositions" class="h-full w-full flex flex-col rounded-md border">
    <!-- Header -->
    <div class="grid grid-cols-5 bg-gray-100 text-xs font-medium uppercase text-gray-500">
        <div class="py-2 px-4 text-left">Pair</div>
        <div class="py-2 px-4 text-center">Side</div>
        <div class="py-2 px-4 text-right">Entry</div>
        <div class="py-2 px-4 text-right">Current</div>
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
                    class="grid grid-cols-5 text-sm text-center bg-white odd:bg-white even:bg-gray-50 hover:bg-gray-100 transition-colors cursor-pointer py-2 px-4">
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
                    No open signals.
                </div>
            @endforelse
        </div>
    </div>
</div>
