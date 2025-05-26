<?php

namespace App\Livewire\Trading;

use Livewire\Component;
use App\Models\AssetPair;
use Livewire\Attributes\On;

class AssetInformation extends Component
{
    /** 
     * The AssetPair model we’re displaying. 
     */
    public AssetPair $pair;

    /**
     * The list of columns to show.
     */
    protected array $fields = [
        'pair_name',
        // 'alt_name',
        'base_currency',
        'quote_currency',
        'status',
        'pair_decimals',
        'tick_size',
        'ordermin',
        'costmin',
        'lot_multiplier',
        'leverage_buy',
        'fees',
    ];

    /**
     * Pre-built rows of [label => value] for the view.
     *
     * @var array<array{label:string,value:string}>
     */
    public array $rows = [];

    /**
     * @param  string|null  $wsName  the WebSocket name, if passed in
     */
    public function mount()
    {
        // Try to fetch by WS name; if missing or not found, pick the first watchlisted
        $this->pair = AssetPair::watchlisted()->first()  ?? AssetPair::make();

        $this->rows = $this->prepareRows();
    }

    #[On('selectAssetId')]
    public function selectPairById(AssetPair $pair): void
    {
        // Find the pair by ID and update the rows
        $this->pair = $pair;
        $this->rows = $this->prepareRows();
    }

    #[On('selectAssetName')]
    public function selectPairByName(string $pairName): void
    {
        // Find the pair by ID and update the rows
        $this->pair = AssetPair::where('pair_name', $pairName)->first();
        $this->rows = $this->prepareRows();
    }

    /**
     * Build the [label, value] rows based on $fields.
     */
    protected function prepareRows(): array
    {
        $labelMap = [
            'ordermin'      => 'Order Minimum',
            'costmin'       => 'Cost Minimum',
            'pair_decimals' => 'Decimal Places',
        ];

        $rows = [];

        foreach ($this->fields as $prop) {
            $raw = $this->pair->$prop;

            if (in_array($prop, ['fees', 'fees_maker'], true)) {
                // Decode if it’s stored as a JSON‐string
                $json = is_string($raw) ? trim($raw, '"') : $raw;
                $tiers = is_array($json) ? $json : (json_decode($json, true) ?: []);
                $formatted = $this->formatFeesAsTable($tiers);
            } else {
                $formatted = match ($prop) {
                    'leverage_buy' => is_array($raw)
                        ? implode(':1, ', $raw) . ':1'
                        : (string)$raw,
                    'status' => $raw === 'online'
                        ? '<span class="text-green-500">● Active</span>'
                        : '<span class="text-red-500">● Inactive</span>',
                    default => is_array($raw)
                        ? implode(', ', $raw)
                        : (string) $raw,
                };
            }

            $rows[] = [
                'label' => $labelMap[$prop] ?? ucwords(str_replace('_', ' ', $prop)),
                'value' => $formatted,
            ];
        }

        return $rows;
    }

    /**
     * Render a <ul> of fee tiers with indent and %.
     *
     * @param  array<array{0: float|int,1: float|int}>  $tiers
     */
    protected function formatFeesAsTable(array $tiers): string
    {
        if (empty($tiers)) {
            return '—';
        }

        $html  = '<table class="ml-[-80px] table-auto border border-gray-200">';
        $html .= '<thead><tr>';
        $html .= '<th class="px-2 py-1 text-left">Volume</th>';
        $html .= '<th class="px-2 py-1 text-left">Fee %</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($tiers as [$volume, $pct]) {
            $html .= "<tr>";
            $html .= "<td class=\"px-2 py-1\">{$volume}</td>";
            $html .= "<td class=\"px-2 py-1\">{$pct}%</td>";
            $html .= "</tr>";
        }

        $html .= '</tbody></table>';

        return $html;
    }


    public function render()
    {
        return view('livewire.trading.asset-information', [
            'rows' => $this->rows,
        ]);
    }
}
