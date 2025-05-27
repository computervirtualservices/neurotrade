<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\KrakenStructure;
use App\Helpers\OHLCIndicators;
use App\Jobs\ProcessCryptoPair;
use App\Models\AssetPair;
use App\Models\OhlcvData;
use App\Services\CryptoML\CryptoPredictor;
use App\Services\SignalLogger;
use App\Services\KrakenMarketData;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laratrade\Trader\Contracts\Trader as TraderContract;
use InvalidArgumentException;

final class CryptoPredictCommand extends Command
{
    protected $signature = 'crypto:predict
        {--i|interval=    : Interval in minutes or label (e.g. 15 or 15M)}
        {--p|pair=        : Specific trading pair to process}
        {--a|auto-trade   : Execute trades automatically based on predictions}
        {--d|dry-run      : Do not execute any trades}
        {--now : Run immediately, regardless of configured intervals}';

    protected $description = 'Run ML-based crypto analysis for configured pairs and intervals';

    public function __construct(
        private readonly TraderContract  $trader,
        private readonly KrakenMarketData $marketData,
        private readonly CryptoPredictor  $predictor,
        private readonly SignalLogger     $logger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Log::info("CryptoPredictCommand started");
        // Set custom temp path for Symfony Process
        $customTmpPath = storage_path('app/tmp');

        if (!file_exists($customTmpPath)) {
            mkdir($customTmpPath, 0777, true);
        }

        putenv("TMP={$customTmpPath}");
        putenv("TEMP={$customTmpPath}");

        $rawInterval = $this->option('interval');
        $pairFilter  = $this->option('pair');
        $autoTrade   = (bool) $this->option('auto-trade');
        $dryRun      = (bool) $this->option('dry-run');
        $runNow      = (bool) $this->option('now');

        if ($dryRun) {
            $this->warn('DRY RUN: no trades will be executed.');
        }
        if ($autoTrade && ! $dryRun) {
            $this->warn('AUTO-TRADE enabled: trades will be executed.');
        }

        $pairs = $this->loadPairs($pairFilter, (int)$rawInterval, $runNow);
       
        foreach ($pairs as $pair) {
            $pairName = $pair->ws_name;
            $interval = (int)$rawInterval ?: (int)$pair->interval;
            $this->info("Processing {$interval}m interval for pair: " . $pairName);
            dispatch(new ProcessCryptoPair($pairName, $interval, $autoTrade, $dryRun));
            sleep(2);
        }

        $this->info('Analysis complete.');
        return self::SUCCESS;
    }

    private function loadPairs(?string $pair, int $interval, bool $runNow = false): Collection
    {
        if ($pair) {
            $pair = strtoupper($pair);
            return AssetPair::where('pair_name', $pair)
                ->orWhere('alt_name', $pair)
                ->orWhere('ws_name', $pair)
                ->get();
        }

        $all = AssetPair::getWatchlisted($interval);

        if ($runNow) {
            return $all;
        }

        $minutesSinceMidnight = Carbon::now()->diffInMinutes(Carbon::today());

        return $all->filter(
            fn($p) => $p->interval > 0
                && $minutesSinceMidnight % $p->interval === 0
        )->values();
    }
}
