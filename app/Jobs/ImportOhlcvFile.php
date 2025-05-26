<?php

namespace App\Jobs;

use App\Helpers\MarketMath;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use Throwable;

class ImportOhlcvFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $path;
    protected string $pair;
    protected int    $interval;
    protected string $table;

    /** max MySQL placeholders (default ~65k) */
    protected int $maxPlaceholders = 60000;

    public function __construct(string $path, string $pair, int $interval, string $table)
    {
        $this->path     = $path;
        $this->pair     = $pair;
        $this->interval = $interval;
        $this->table    = $table;
    }

    public function handle(): void
    {
        if (! file_exists($this->path)) {
            Log::warning("ImportOhlcvFile: file not found", ['path' => $this->path]);
            return;
        }

        Log::info("Import starting for {$this->pair} → {$this->table}");

        // determine how many rows per batch so we never exceed placeholders
        // 10 columns to insert + 10 for update = ~20 placeholders per row
        $columnsPerRow = 10 + 10;
        $batchSize     = max(100, intdiv($this->maxPlaceholders, $columnsPerRow));

        LazyCollection::make(function () {
            $handle = fopen($this->path, 'r');
            fgetcsv($handle); // skip header
            while (($row = fgetcsv($handle)) !== false) {
                yield $row;
            }
            fclose($handle);
        })
        ->chunk($batchSize)
        ->each(function (LazyCollection $chunk) {
            $now   = now();
            $batch = $chunk->map(fn(array $data) => [
                'pair'        => $this->normalizePair($this->pair),
                'interval'    => $this->interval,
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

            // transactionally upsert this chunk
            DB::transaction(function () use ($batch) {
                DB::table($this->table)
                  ->upsert(
                      $batch,
                      ['pair','interval','timestamp'],
                      ['open_price','high_price','low_price','close_price','vwap','volume','trade_count','updated_at']
                  );
            });

            Log::info("Imported chunk of " . count($batch) . " rows into {$this->table}");
        });

        Log::info("Import finished for {$this->pair} → {$this->table}");
    }

    protected function normalizePair(string $pair): string
    {
        return preg_replace('/U\/SD([CT])$/', '/USD$1', $pair) ?? $pair;
    }
}
