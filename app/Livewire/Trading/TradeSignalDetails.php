<?php

namespace App\Livewire\Trading;

use Livewire\Component;
use App\Models\TradeSignal;
use Livewire\Attributes\On;

class TradeSignalDetails extends Component
{
    private TradeSignal $signal;
    private array       $rows = [];

    public function mount() {}

    #[On('openSignalDetails')]
    public function openSignalDetails(TradeSignal $signal): void
    {
        // set the signal and build the rows
        $this->signal = $signal;
        $this->rows   = $this->buildRows();
    }

    protected function buildRows(): array
    {
        try {
            // decode JSON fields
            $supports   = $this->signal->support_levels   ?? [];
            $resists    = $this->signal->resistance_levels ?? [];
            $indicators = $this->signal->key_indicators     ?? [];

            // format indicators as an HTML <ul>
            $indHtml = '<ul class="list-none list-inside">';
            foreach ($indicators as $key => $info) {
                $note  = e($info['note'] ?? '');
                $value = e($info['value'] ?? '');
                $indHtml .= "<li><strong>{$key}</strong>: {$note} ({$value})</li>";
            }
            $indHtml .= '</ul>';

            return [
                ['label' => 'Pair',                 'value' => e($this->signal->pair_name)],
                ['label' => 'Interval',             'value' => e($this->signal->interval)],
                ['label' => 'Signal',               'value' => e($this->signal->signal)],
                ['label' => 'Confidence',           'value' => e($this->signal->confidence)],
                ['label' => 'Action',               'value' => e($this->signal->action)],
                ['label' => 'Strength',             'value' => e($this->signal->strength)],
                ['label' => 'Confidence %',         'value' => e($this->signal->confidence_percent)],
                ['label' => 'Confidence Level',     'value' => e($this->signal->confidence_level)],
                ['label' => 'Explanation',          'value' => e($this->signal->explanation)],
                ['label' => 'Suggested Entry',      'value' => e($this->signal->suggested_entry)],
                ['label' => 'Stop Loss',            'value' => e($this->signal->suggested_stop_loss)],
                ['label' => 'Take Profit',          'value' => e($this->signal->suggested_take_profit)],
                ['label' => 'Buy Price',            'value' => e($this->signal->buy_price)],
                ['label' => 'Sell Price',           'value' => e($this->signal->sell_price)],
                ['label' => 'Support Levels',       'value' => e(implode(', ', $supports))],
                ['label' => 'Resistance Levels',    'value' => e(implode(', ', $resists))],
                ['label' => 'Key Indicators',       'value' => $indHtml],
                ['label' => 'Created At',           'value' => e($this->signal->created_at)],
            ];
        } catch (\Exception $e) {
            //dd($e);
            // Handle the exception, e.g., log it or show an error message
            return [];
        }
    }

    public function render()
    {
        return view('livewire.trading.trade-signal-details', [
            'rows' => $this->rows,
        ]);
    }
}
