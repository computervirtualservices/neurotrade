<?php

namespace App\Livewire\Trading;

use App\Helpers\KrakenStructure;
use App\Models\AssetPair;
use App\Services\KrakenMarketData;
use Livewire\Attributes\On;
use Livewire\Component;

class TradeChart extends Component
{
    // ← this makes $candles available to the view
    public AssetPair $assetPair;
    public array $chartData;
    public string $chartTitle;
    public int $interval;

    public function mount()
    {
        // Load initial bars if you like
        $this->interval = 0;
        $this->chartTitle = 'Click on Trading Watchlist to load chart';
        $this->chartData = [
            'series' => [],
        ];
    }

    /**
     * Called by the frontend (or wire:poll) to re-fetch & re‑push the latest OHLC data.
     */
    public function refreshChart()
    {
        if (isset($this->assetId) && $this->assetId !== null && $this->assetId !== 0) {
            // Re‑generate the chart payload for the currently selected symbol
            $this->generateChartData($this->assetPair);
        }
    }

    #[On('setChartDataByName')]
    public function generateChartDataByName(string $name)
    {
        try {
            $assetPair = AssetPair::where('pair_name', '=', $name)->first();
            if ($assetPair) {
                $this->generateChartData($assetPair['id']);
            }
        } catch (\Exception $e) {
            // Handle the exception, e.g., log it or show an error message
            //dd($e);
        }
    }
    
    #[On('setChartData')]
    public function generateChartData(AssetPair $pair)
    {
        try {
            $assetPair = $pair;
            $this->assetPair = $assetPair;
            $this->interval = $assetPair->interval;
            $pair = $assetPair['pair_name'];
            $client = app(KrakenMarketData::class);
            $data    = $client->ohlc($pair, $this->interval);
            $chartTitle = $assetPair['pair_name'] . ' - ' . $this->interval . ' min';

            $ohlcData = KrakenStructure::ohlcData($data);
            
            // Only keep the last 50 data points for display
            $chartBars = array_slice($ohlcData, -50);

            // Match signals to chart bars (by timestamp)
            $timestamps = array_column($chartBars, 'time');

            // Set chart options
            $chartData = [
                'series' => [
                    [
                        'name' => $pair,
                        'data' => $chartBars
                    ]
                ],
                'chart' => [
                    'type' => 'candlestick',
                    'height' => 400,
                    'toolbar' => [
                        'show' => true,
                        'tools' => [
                            'download' => false,
                            'selection' => false,
                            'zoom' => true,
                            'zoomin' => true,
                            'zoomout' => true,
                            'pan' => true,
                            'reset' => true
                        ]
                    ]
                    // Note: PHP can't define JavaScript events, they'll be added in the frontend JavaScript
                ],
                'xaxis' => [
                    'type' => 'datetime',
                    // Note: formatter function will be defined in JavaScript
                ],
                'yaxis' => [
                    'tooltip' => [
                        'enabled' => true
                    ]
                ],
                'plotOptions' => [
                    'candlestick' => [
                        'colors' => [
                            'upward' => '#26a69a',
                            'downward' => '#ef5350'
                        ]
                    ]
                ]
                // Note: custom tooltip function will be defined in JavaScript
            ];

            // Dispatch an event to update the chart data in the front-end
            $this->dispatch(
                'chartDataUpdated',
                chartData: $chartData,
                chartTitle: $chartTitle
            );
        } catch (\Exception $e) {
            // Handle error
            $this->chartData = [];
        }
    }

    public function render()
    {
        return view('livewire.trading.trade-chart', [
            'interval' => $this->interval,
        ]);
    }
}
