<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;

class TradingDashboard extends Component
{
    /**
     * Optional: fetch any initial data here (candles, positions, orderbook, tickers)
     * you could inject services and set public properties to pass down to child components.
     */
    public function mount()
    {
        //
    }

    public function render()
    {
        return view('livewire.trading-dashboard');
    }
}
