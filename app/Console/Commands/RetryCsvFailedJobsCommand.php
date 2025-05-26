<?php

namespace App\Console\Commands;

use App\Helpers\MarketMath;
use App\Models\AssetPair;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\LazyCollection;
use App\Models\FailedJob;

class RetryCsvFailedJobsCommand extends Command
{
    protected $signature = 'queue:retry-csv-failed';
    protected $description = 'Retry failed import jobs by reading failed_jobs table and processing CSV manually';

    public function handle()
    {
        $this->info('Checking failed jobs for CSV imports...');

        $jobs = DB::table('failed_jobs')->get();

        foreach ($jobs as $job) {
            $payload = json_decode($job->payload, true);

            $unserialized = @unserialize($payload['data']['command']);

            if (!is_object($unserialized)) {
                throw new \RuntimeException('Failed to unserialize job.');
            }

            $props = ['path', 'pair', 'interval', 'table'];
            $data = [];

            $reflection = new \ReflectionClass($unserialized);

            foreach ($props as $prop) {
                if ($reflection->hasProperty($prop)) {
                    $property = $reflection->getProperty($prop);
                    $property->setAccessible(true);
                    $data[$prop] = $property->getValue($unserialized);
                }
            }

            // Now you can use:
            $path     = $data['path'];
            $pair     = $data['pair'];
            $interval = $data['interval'];
            $table    = $data['table'];

            if (!file_exists($path)) {
                $this->error("File not found: {$path}");
                continue;
            }

            $this->info("Retrying import: {$path}");

            // Delete old records BEFORE importing any chunks
            DB::table($table)
                ->where('pair', $pair)
                ->where('interval', $interval)
                ->delete();

            LazyCollection::make(function () use ($path) {
                $handle = fopen($path, 'r');
                fgetcsv($handle); // Skip header
                while (($row = fgetcsv($handle)) !== false) {
                    yield $row;
                }
                fclose($handle);
            })
                ->chunk(1000)
                ->each(function (LazyCollection $chunk) use ($pair, $interval, $table) {
                    $now   = now();
                    $batch = $chunk->map(fn(array $data) => [
                        'pair'        => $this->normalizePair($pair),
                        'interval'    => $interval,
                        'timestamp'   => (int) $data[0],
                        'open_price'  => (float) $data[1],
                        'high_price'  => (float) $data[2],
                        'low_price'   => (float) $data[3],
                        'close_price' => (float) $data[4],
                        'vwap'        => (float) MarketMath::calculateVWAP($data),
                        'volume'      => (float) $data[5],
                        'trade_count' => (int) ($data[6] ?? 0),
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ])->all();

                    DB::table($table)->upsert(
                        $batch,
                        ['pair', 'interval', 'timestamp'],
                        ['open_price', 'high_price', 'low_price', 'close_price', 'vwap', 'volume', 'trade_count', 'updated_at']
                    );

                    Log::info("Imported chunk of " . count($batch) . " rows into {$table}");
                });

            // ✅ Delete failed job record after successful import
            DB::table('failed_jobs')->where('uuid', $job->uuid)->delete();

            Log::info("✅ Import complete for {$path} and failed job {$job->uuid} deleted.");
        }

        return Command::SUCCESS;
    }

    protected function normalizePair(string $pair): string
    {
        return preg_replace('/U\/SD([CT])$/', '/USD$1', $pair) ?? $pair;
    }
}
