<?php
declare(strict_types=1);

namespace App\Livewire;

use App\Models\TradeSignal;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

class TradeReport extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        // 1) Load all signals, ordered by time
        /** @var Collection<TradeSignal> $signals */
        $signals = TradeSignal::orderBy('created_at')->get();

        // 2) Group by pair_name & interval
        $grouped = $signals
            ->groupBy(fn(TradeSignal $t) => $t->pair_name . '|' . $t->interval);

        $data = [];

        foreach ($grouped as $key => $trades) {
            [$pair, $interval] = explode('|', $key);

            $roundTrips = [];
            $openBuy     = null;

            // 3) Walk the trades in time order, pairing buy→sell
            foreach ($trades as $t) {
                if ($t->action === 'BUY') {
                    // start a new open trade
                    $openBuy = $t;
                } elseif ($t->action === 'SELL' && $openBuy) {
                    // close the open trade
                    $roundTrips[] = [
                        'buy'  => $openBuy->buy_price,
                        'sell' => $t->sell_price,
                    ];
                    $openBuy = null;
                }
            }

            if (empty($roundTrips)) {
                continue; // no completed trades for this pair/interval
            }

            // 4) Compute stats
            $wins    = 0;
            $losses  = 0;
            $pnlSum  = 0.0;

            foreach ($roundTrips as $rt) {
                $profit = $rt['sell'] - $rt['buy'];
                $pnlSum += $profit;
                if ($profit > 0) {
                    $wins++;
                } elseif ($profit < 0) {
                    $losses++;
                }
            }

            $data[] = [
                'pair_name'    => $pair,
                'interval'     => (int) $interval,
                'wins'         => $wins,
                'losses'       => $losses,
                'total_trades' => count($roundTrips),
                'pnl'          => round($pnlSum, 4),
            ];
        }

        // 5) Sort by pair for display
        usort($data, fn($a, $b) => strcmp($a['pair_name'], $b['pair_name']));

        $this->stats = $data;
    }

    public function render()
    {
        return view('livewire.trade-report');
    }
}