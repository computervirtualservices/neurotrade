<?php

namespace App\Console\Commands;

use App\Helpers\KrakenStructure as HelpersKrakenStructure;
use Illuminate\Console\Command;
use App\Models\AssetPair;
use App\Models\OhlcvData;
use App\Helpers\OHLCIndicators;
use App\Services\KrakenMarketData;
use App\Services\KrakenStructure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FetchOhlcCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ohlcv:fetch-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch daily OHLC data for all asset pairs and intervals';

    public function __construct(
        private readonly KrakenMarketData $marketData,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $pairs = AssetPair::get()->pluck('pair_name');

        foreach ($pairs as $pair) {
            foreach (KrakenMarketData::VALID_OHLC_INTERVALS as $interval) {
                if ($interval === 0) {
                    continue;
                }

                try {
                    $this->info("Fetching {$interval}m for {$pair}");
                    $this->fetchOhlc($pair, $interval);
                    sleep(1); // Sleep to avoid rate limits
                } catch (\Throwable $e) {
                    Log::error("Error fetching OHLC for {$pair} @ {$interval}m: {$e->getMessage()}");
                    $this->error("Failed: {$pair} @ {$interval}m");
                }
            }
        }

        $this->info('OHLC fetch complete.');

        return Command::SUCCESS;
    }

    /**
     * Fetch and upsert OHLC data for a given pair and interval.
     *
     * @param string $pair
     * @param int    $interval
     * @return array
     */
    private function fetchOhlc(string $pair, int $interval): array
    {
        // Resolve ws_name and normalize
        $wsName =  AssetPair::getWsNameOrPairName($pair);

        // Use the AssetPair model to get the standardized pair name
        $tableName = (string) Str::of($wsName)
            ->lower()
            ->replace('/', '_')
            ->append('_ohlcv');

        // Check if the table exists
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('pair', 16);
                $table->integer('interval');
                $table->integer('timestamp');
                $table->double('open_price', 18, 8);
                $table->double('high_price', 18, 8);
                $table->double('low_price', 18, 8);
                $table->double('close_price', 18, 8);
                $table->double('vwap', 18, 8);
                $table->double('volume', 18, 8);
                $table->integer('trade_count')->nullable();
                $table->timestamps();

                $table->unique(['pair', 'interval', 'timestamp']);
                $table->index(['pair', 'interval', 'timestamp'], 'idx_pair_interval_time');
            });

            Log::info("Created table {$tableName}");
        }

        // Fetch raw OHLC from marketData service
        $raw = $this->marketData->ohlc($pair, $interval);

        foreach ($raw as $data) {
            try {
                // Check if the data is empty or malformed
                if (empty($data)) {
                    continue;
                }

                DB::table($tableName)->updateOrInsert([
                    'pair'      => $wsName,
                    'interval'  => $interval,
                    'timestamp' => $data[0],
                ], [
                    'open_price'   => $data[1],
                    'high_price'   => $data[2],
                    'low_price'    => $data[3],
                    'close_price'  => $data[4],
                    'vwap'         => $data[5],
                    'volume'       => $data[6],
                    'trade_count'  => $data[7],
                ]);
            } catch (\Exception $e) {
                dd($e);
                Log::error('Error inserting OHLC data: ' . $e->getMessage());
            }
        }

        $this->info("Upserted " . count($raw) . " rows for {$pair} @ {$interval}m");

        // Return structured data if needed
        return HelpersKrakenStructure::ohlcData($raw);
    }
}
