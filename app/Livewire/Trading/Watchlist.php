<?php

namespace App\Livewire\Trading;

use Livewire\Component;
use App\Models\AssetPair;

class Watchlist extends Component
{
    // Listen for a global event that we’ll fire whenever a pair is toggled
    protected $listeners = [
        // Not needed if you call the method directly from Blade
    ];

    public function mount()
    {
    }


    public function selectPair(int $pairId): void
    {
        $this->dispatch('selectAssetId', $pairId);
        $this->dispatch('setChartData', $pairId);
    }

    public function render()
    {
        // 1) Grab each watchlisted pair *and* inject ->price in ZUSD
        $pairs = AssetPair::getWatchlistedWithPrice()
            // 2) Sort them by pair_name (or whatever field you prefer)
            ->sortBy('pair_name')
            // 3) Re-index the collection so Blade’s @foreach works smoothly
            ->values();

        return view('livewire.trading.watchlist', [
            'pairs' => $pairs,
        ]);
    }
}
