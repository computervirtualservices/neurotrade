<?php

namespace App\Livewire;

use App\Helpers\AssetPairHelper;
use App\Models\AssetPair;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\OHLCIndicators;
use App\Helpers\KrakenAssetPair;
use App\Models\AssetInfo;

class AssetPairsDashboard extends Component
{
    use WithPagination;

    public $search = '';
    public $sortField = 'pair_name';
    public $sortDirection = 'asc';
    public $showWatchlistedOnly = false;
    public $refreshing = false;
    public $errorMessage = null;

    protected $paginationTheme = 'tailwind';

    protected $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'pair_name'],
        'sortDirection' => ['except' => 'asc'],
        //'showWatchlistedOnly' => ['except' => false],
    ];

    protected $listeners = [
        'refreshAssetPairs' => 'refreshAssetPairs'
    ];

    public function mount()
    {
        // Check if the asset_pairs table is empty, if so, fetch data
        if (AssetPair::count() === 0) {
            $this->fetchAssetPairsFromKraken();
        }

        // Check if asset data exists in the local database.
        if (AssetInfo::count() === 0) {
            // Fetch asset data from Kraken's Assets endpoint.
            $response = Http::withOptions(['verify' => false])->get('https://api.kraken.com/0/public/Assets');
            $json = $response->json();
            if (isset($json['result'])) {
                foreach ($json['result'] as $assetID => $assetInfo) {
                    AssetInfo::create([
                        'asset_id' => $assetID,
                        'altname' => $assetInfo['altname'] ?? $assetID,
                        'aclass' => $assetInfo['aclass'] ?? null,
                        'decimals' => $assetInfo['decimals'] ?? 0,
                        'display_decimals' => $assetInfo['display_decimals'] ?? 0,
                    ]);
                }
            }
        }
    }

    /**
     * Whenever showWatchlistedOnly flips, go back to page 1.
     */
    public function updatedShowWatchlistedOnly(): void
    {
        $this->resetPage();
    }

    public function handleSearchUpdate()
    {
        $this->resetPage();
    }


    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }

        $this->sortField = $field;
    }

    public function toggleWatchlist($id)
    {
        $assetPair = AssetPair::findOrFail($id);
        $assetPair->is_watchlisted = !$assetPair->is_watchlisted;
        $assetPair->save();

        // Add a session flash message
        session()->flash(
            'message',
            $assetPair->is_watchlisted
                ? "Added {$assetPair->formatted_name} to watchlist"
                : "Removed {$assetPair->formatted_name} from watchlist"
        );

        // reset any pagination/state you have
        //$this->resetPage();

        // <- this will re-run render() and rerender your entire table
        $this->dispatch('$refresh');
    }

    public function updateLogInterval(int $id, int $minutes): void
    {
        $assetPair = AssetPair::findOrFail($id);
        $assetPair->interval = $minutes;
        $assetPair->save();
    }

    public function fetchAssetPairsFromKraken()
    {
        $this->refreshing = true;
        $this->errorMessage = null;

        KrakenAssetPair::refresh();

        $this->refreshing = false;
    }

    public function render()
    {
        $query = AssetPair::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('pair_name', 'like', '%' . $this->search . '%')
                    ->orWhere('alt_name', 'like', '%' . $this->search . '%')
                    ->orWhere('base_currency', 'like', '%' . $this->search . '%')
                    ->orWhere('quote_currency', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->showWatchlistedOnly) {
            $query->watchlisted();
        }

        $assetPairs = $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate(13);

        return view('livewire.asset-pairs-dashboard', [
            'assetPairs' => $assetPairs
        ]);
    }
}