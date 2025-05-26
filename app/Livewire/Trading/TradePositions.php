<?php

namespace App\Livewire\Trading;

use Livewire\Component;
use App\Models\AssetPair;
use App\Models\TradeSignal;
use App\Services\KrakenTicker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;

class TradePositions extends Component
{
    private array $rows = [];

    public function mount()
    {
        $this->rows = $this->loadPositions();
    }

    public function hydrate(): void
    {
        $this->rows = $this->loadPositions();
    }

    /**
     * Initial load & every poll interval
     */
    public function loadAllTickerData(): array
    {
        // 1) grab the list of distinct pairs
        $pairNames = TradeSignal::query()
            ->groupBy('pair_name')
            ->pluck('pair_name')
            ->all();

        // 2) fetch raw Kraken data once
        $raw = app(KrakenTicker::class)
            ->fetchTickerData();

        // 3) shrink it down to pair => bidPrice
        $ticketData = [];
        foreach (($raw['result'] ?? []) as $pair => $data) {
            if (isset($data['b'][0])) {
                $ticketData[$pair] = (float) $data['b'][0];
            }
        }

        return $ticketData;
    }

    #[On('updatePositions')]
    public function updatePositions(): void
    {
        $this->rows = $this->loadPositions();
    }

    public function selectRowPair(string $pair, int $rowId): void
    {
        try {
            // check if the pair exists in the database
            $pair = AssetPair::where('pair_name', $pair)->first();
            $asset = TradeSignal::find($rowId);
            $this->dispatch('selectAssetId', $pair);
            $this->dispatch('setChartData', $pair);
            $this->dispatch('openSignalDetails', $asset);
        } catch (\Exception $e) {
            // handle the case where the pair does not exist
            Log::error('Pair not found: ' . $pair);
        }
    }

    /**
     * Computed property: sum of all plPct values.
     */
    public function getTotalPlPctProperty(): float
    {
        return collect($this->rows)
            ->sum(fn(array $r) => $r['plPct']);
    }


    public function loadPositions(): array
    {
        // A) get the latest signal IDs
        $latestIds = TradeSignal::select(DB::raw('MAX(id) as id'))
            ->groupBy('pair_name')
            ->pluck('id');

        $signals = TradeSignal::whereIn('id', $latestIds)->get();

        
    // 🔥 Filter out pairs where the latest action is SELL
    $signals = $signals->filter(fn($s) => $s->action !== 'SELL');

        // B) fetch Kraken prices once
        $raw       = app(KrakenTicker::class)->fetchTickerData()['result'] ?? [];
        $ticketData = collect($raw)
            ->mapWithKeys(fn($data, $pair) => isset($data['b'][0])
                ? [$pair => (float)$data['b'][0]]
                : [])
            ->all();

        // C) build your rows exactly like before
        $sellPairs    = $signals->where('action', 'SELL')->pluck('pair_name')->all();
        $latestBuyIds = TradeSignal::select(DB::raw('MAX(id) as id'))
            ->whereIn('pair_name', $sellPairs)
            ->where('action', 'BUY')
            ->groupBy('pair_name')
            ->pluck('id');
        $buySignals = TradeSignal::whereIn('id', $latestBuyIds)
            ->get()
            ->keyBy('pair_name');

        return $signals->map(function ($s) use ($buySignals, $ticketData) {
            $entry = ($s->action === 'SELL' && isset($buySignals[$s->pair_name]))
                ? (float)$buySignals[$s->pair_name]->buy_price
                : (float)$s->buy_price;

            $exit = ($s->action === 'BUY')
                ? ($ticketData[$s->pair_name] ?? 0)
                : (float)$s->sell_price;

            $plAbs = round($exit - $entry, 8);

            $plPct = $entry > 0 ? round((($exit - $entry) / $entry) * 100, 2) : 0;

            $plClass = ($exit - $entry) > 0 ? 'text-green-600' : 'text-red-600';

            return [
                'id'      => $s->id,
                'pair'    => $s->pair_name,
                'side'    => ucfirst(strtolower($s->action)),
                'entry'   => $entry,
                'exit'    => $exit,
                'plAbs'   => $plAbs,
                'plPct'   => $plPct,
                'plClass' => $plClass,
            ];
        })->sortBy('pair')->all();
    }


    public function render()
    {
        return view('livewire.trading.trade-positions', [
            'rows' => $this->rows,
            'totalPlPct' => $this->totalPlPct,
        ]);
    }
}
