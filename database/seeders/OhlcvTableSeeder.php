<?php

namespace Database\Seeders;

use App\Helpers\KrakenAssetPair;
use App\Models\AssetPair;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Throwable;

class OhlcvTableSeeder extends Seeder
{
    public function run(): void
    {
        try {
            // Refresh your list of valid asset pairs first
            KrakenAssetPair::refresh();

            $folder = storage_path('app/ohlcv');
            $files  = File::files($folder);

            foreach ($files as $file) {
                if (! preg_match('/^([A-Z0-9]+)([A-Z]{3})_(\d+)\.csv$/', $file->getFilename(), $m)) {
                    continue;
                }

                $rawPair = $m[1] . $m[2];
                $pair    = AssetPair::getWsNameOrPairName($rawPair);

                if (! $pair) {
                    Log::warning(
                        "OhlcvTableSeeder: skipping file because no AssetPair found",
                        ['filename' => $file->getFilename(), 'rawPair' => $rawPair]
                    );
                    continue;
                }

                // build table name: e.g. btc_usdt_ohlcv
                $tableName = (string) Str::of($pair)
                    ->lower()
                    ->replace('/', '_')
                    ->append('_ohlcv');

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

                    Log::info("OhlcvTableSeeder: Created table", ['table' => $tableName]);
                }
            }

        } catch (Exception $e) {
            Log::error(
                "OhlcvTableSeeder failed",
                [
                    'error'     => $e->getMessage(),
                    'exception' => $e,
                ]
            );
            // rethrow so the seeder stops and the error is visible
            // throw $e;
        }
    }
}
