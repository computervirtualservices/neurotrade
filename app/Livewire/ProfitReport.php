<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\TradeSignal;
use App\Services\KrakenTicker;
use Illuminate\Support\Str;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class ProfitReport extends Component
{
    use WithPagination;

    private array $allRows = [];
    private float $totalPlPct = 0.0;
    public $showOpenSideOnly = true;
    public $showCompleteSideOnly = true;
    public $search = '';
    public $perPage = 13;

    public function mount(): void {}

    public function updatePositions(): void
    {
        $signals = TradeSignal::orderBy('created_at')->get()->groupBy('pair_name');

        $raw = app(KrakenTicker::class)->fetchTickerData()['result'] ?? [];
        $ticketData = collect($raw)
            ->mapWithKeys(fn($data, $pair) => isset($data['b'][0]) ? [$pair => (float)$data['b'][0]] : [])
            ->all();

        if ($this->search) {
            $search = Str::upper($this->search);

            $signals = $signals
                ->filter(fn($group, $pair) => Str::contains(Str::upper($pair), $search));

            $ticketData = collect($ticketData)
                ->filter(function (float $price, string $pair) use ($search) {
                    return Str::contains($pair, $search);
                })
                ->all();
        }

        $totalPl = 0;
        $totalEntry = 0;
        $openRows = [];
        $completeRows = [];

        foreach ($signals as $pair => $trades) {
            $openBuy = null;

            foreach ($trades as $trade) {
                if ($trade->action === 'BUY') {
                    $openBuy = $trade;
                } elseif ($trade->action === 'SELL' && $openBuy) {
                    $entry = (float)$openBuy->buy_price;
                    $exit = (float)$trade->sell_price;

                    $plAbs = round($exit - $entry, 8);
                    $plPct = $entry > 0 ? round((($exit - $entry) / $entry) * 100, 2) : 0;

                    $totalEntry += $entry;

                    $completeRows[] = [
                        'id'      => $trade->id,
                        'date'    => $trade->created_at->toDateTimeString(),
                        'pair'    => $pair,
                        'side'    => 'Complete',
                        'entry'   => $entry,
                        'exit'    => $exit,
                        'plAbs'   => $plAbs,
                        'plPct'   => $plPct,
                        'plClass' => ($exit - $entry) > 0 ? 'text-green-600' : 'text-red-600',
                    ];
                    $openBuy = null; // Reset after matched
                }
            }

            // Handle unmatched open BUY
            if ($openBuy) {
                $entry = (float)$openBuy->buy_price;
                $exit = $ticketData[$pair] ?? 0;

                $plAbs = round($exit - $entry, 8);
                $plPct = $entry > 0 ? round((($exit - $entry) / $entry) * 100, 2) : 0;

                $totalEntry += $entry;

                $openRows[] = [
                    'id'      => $openBuy->id,
                    'date'    => $openBuy->created_at->toDateTimeString(),
                    'pair'    => $pair,
                    'side'    => 'Open',
                    'entry'   => $entry,
                    'exit'    => $exit,
                    'plAbs'   => $plAbs,
                    'plPct'   => $plPct,
                    'plClass' => ($exit - $entry) > 0 ? 'text-green-600' : 'text-red-600',
                ];
            }
        }
        // sort by date
        usort($openRows, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));
        usort($completeRows, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));

        $filteredOpenRows = ($this->showOpenSideOnly) ? $openRows : [];
        $filteredCompleteRows = ($this->showCompleteSideOnly) ? $completeRows : [];

        $this->allRows = array_merge($filteredOpenRows, $filteredCompleteRows);
        $this->totalPlPct = $totalEntry > 0 ? collect($this->allRows)->sum('plPct') : 0;

        // Reset pagination when data changes
        //$this->resetPage();
    }

    public function getPaginatedRowsProperty()
    {
        $currentPage = $this->getPage();
        $perPage = $this->perPage;

        $items = collect($this->allRows);
        $total = $items->count();

        $currentPageItems = $items->forPage($currentPage, $perPage)->values();

        return new LengthAwarePaginator(
            $currentPageItems,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    public function updatedShowOpenSideOnly(): void
    {
        $this->resetPage();
    }

    public function updatedShowCompleteSideOnly(): void
    {
        $this->resetPage();
    }

    public function handleSearchUpdate(): void
    {
        $this->updatePositions();
    }

    public function selectRowPair(string $pair, int $id): void
    {
        // Optional: add selection behavior
    }

    public function render()
    {
        $this->updatePositions();

        return view('livewire.profit-report', [
            'rows' => $this->paginatedRows,
            'totalRows' => count($this->allRows),
            'totalPlPct' => $this->totalPlPct
        ]);
    }
}
