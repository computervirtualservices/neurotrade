<div class="m-2 border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 rounded-lg">
  <div class="p-4">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
        Trade Performance Dashboard
      </h2>
    </div>

    {{-- Header row --}}
    <div class="grid grid-cols-6 gap-4 bg-gray-100 dark:bg-neutral-700 text-gray-700 dark:text-gray-300 font-semibold p-2 rounded">
      <div>Pair</div>
      <div>Interval</div>
      <div>Wins</div>
      <div>Losses</div>
      <div>Total Closed Trades</div>
      <div>P&amp;L</div>
    </div>

    {{-- Data rows --}}
    <div class="divide-y divide-gray-200 dark:divide-neutral-600">
      @forelse($stats as $row)
        <div class="grid grid-cols-6 gap-4 items-center p-2 hover:bg-gray-50 dark:hover:bg-neutral-700">
          <div class="text-gray-900 dark:text-gray-100">{{ $row['pair_name'] }}</div>
          <div class="text-gray-600 dark:text-gray-300">{{ $row['interval'] }}m</div>
          <div class="text-green-600">{{ $row['wins'] }}</div>
          <div class="text-red-600">{{ $row['losses'] }}</div>
          <div class="text-gray-800 dark:text-gray-200">{{ $row['total_trades'] }}</div>
          <div>
            @if($row['pnl'] >= 0)
              <span class="text-green-600">${{ number_format($row['pnl'], 4) }}</span>
            @else
              <span class="text-red-600">-${{ number_format(abs($row['pnl']), 4) }}</span>
            @endif
          </div>
        </div>
      @empty
        <div class="p-4 text-center text-gray-500 dark:text-gray-400">
          No closed trades to report.
        </div>
      @endforelse
    </div>
  </div>
</div>
