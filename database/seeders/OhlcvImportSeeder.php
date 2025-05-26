<?php

namespace Database\Seeders;

use App\Helpers\KrakenAssetPair;
use App\Jobs\ImportOhlcvFile;
use App\Models\AssetPair;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class OhlcvImportSeeder extends Seeder
{
    /** How many jobs may run simultaneously */
    protected int $maxConcurrent = 4;

    /** How many files to dispatch before pausing */
    protected int $maxBatch = 10;

    /** Seconds to sleep between batches */
    protected int $batchSleep = 1;

    public function run(): void
    {
        try {
            KrakenAssetPair::refresh();

            $folder = storage_path('app/ohlcv');
            $files  = File::files($folder);

            $dispatched = 0;

            foreach ($files as $file) {
                // parse filename
                if (! preg_match('/^([A-Z0-9]+)([A-Z]{3})_(\d+)\.csv$/', $file->getFilename(), $m)) {
                    continue;
                }

                $rawPair = $m[1] . $m[2];
                $pair    = AssetPair::getWsNameOrPairName($rawPair);

                if (! $pair) {
                    Log::warning("Skipping {$file->getFilename()}: no AssetPair for {$rawPair}");
                    continue;
                }

                $interval  = (int) $m[3];
                $tableName = (string) Str::of($pair)
                    ->lower()
                    ->replace('/', '_')
                    ->append('_ohlcv');

                // throttle: wait until fewer than maxConcurrent jobs are in flight
                while (Redis::llen('queues:default:reserved') >= $this->maxConcurrent) {
                    $inFlight = Redis::llen('queues:default:reserved');
                    Log::info("Throttling: {$inFlight} ≥ {$this->maxConcurrent}, waiting…");
                    sleep(1);
                }

                // dispatch the job
                Log::info("Dispatching import for {$pair} into {$tableName} (interval {$interval})");
                dispatch(new ImportOhlcvFile(
                    $file->getPathname(),
                    $pair,
                    $interval,
                    $tableName
                ));

                $dispatched++;

                // every maxBatch files, pause for batchSleep seconds
                if ($dispatched % $this->maxBatch === 0) {
                    Log::info("Dispatched {$dispatched} files, sleeping {$this->batchSleep}s before next batch");
                    sleep($this->batchSleep);
                }
            }

            Log::info("OhlcvImportSeeder complete: dispatched {$dispatched} files total.");

        } catch (Throwable $e) {
            Log::error("OhlcvImportSeeder failed", [
                'error'     => $e->getMessage(),
                'exception' => $e,
            ]);
            throw $e;
        }
    }
}
