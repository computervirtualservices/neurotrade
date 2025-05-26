<?php

namespace App\Livewire;

use App\Helpers\MarketMath;
use App\Models\AssetPair;
use App\Models\OhlcvData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Validate;

class OhlcvFileUploadDashboard extends Component
{
    use WithFileUploads;

    #[Validate(['files.*' => 'file|mimes:csv,txt|max:204800'])]
    public $files = [];


    public $uploadStatus = '';
    public $processingProgress = 0;
    public $totalFiles = 0;
    public $processedFiles = 0;
    public $currentFile = '';
    public $fileResults = [];



    public function updatedFiles()
    {
        $this->totalFiles = count($this->files);
        $this->uploadStatus = "{$this->totalFiles} file(s) ready for upload.";
    }

    public function mount()
    {
        $this->uploadStatus = '';
        $this->fileResults = [];
    }

    public function processFiles()
    {
        $this->validate();

        $this->uploadStatus = 'Processing files...';
        $this->processingProgress = 0;
        $this->processedFiles = 0;
        $this->fileResults = [];
        $this->totalFiles = count($this->files);

        try {
            // Create a local copy of files to prevent Livewire state issues
            $filesToProcess = [];
            foreach ($this->files as $file) {
                $filesToProcess[] = $file;
            }

            foreach ($filesToProcess as $index => $file) {
                $this->currentFile = $file->getClientOriginalName();

                $this->dispatch('processingProgress', [
                    'progress' => $this->processingProgress,
                    'currentFile' => $this->currentFile
                ]);

                $this->uploadStatus = "Processing file " . ($this->processedFiles + 1) . " of {$this->totalFiles}: {$this->currentFile}";

                // Extract pair and interval from filename
                $fileInfo = $this->parseFilename($file->getClientOriginalName());

                if ($fileInfo) {
                    try {
                        $result = $this->processFile($file, $fileInfo['pair'], $fileInfo['interval']);

                        $this->fileResults[] = [
                            'file' => $this->currentFile,
                            'pair' => $fileInfo['pair'],
                            'interval' => $fileInfo['interval'],
                            'status' => 'Success',
                            'message' => "{$result['processed']} records processed"
                        ];
                    } catch (\Exception $e) {
                        Log::error("Error processing file {$this->currentFile}: " . $e->getMessage());

                        $this->fileResults[] = [
                            'file' => $this->currentFile,
                            'pair' => $fileInfo['pair'] ?? 'Unknown',
                            'interval' => $fileInfo['interval'] ?? 'Unknown',
                            'status' => 'Error',
                            'message' => "Failed: " . $e->getMessage()
                        ];
                    }
                } else {
                    $this->fileResults[] = [
                        'file' => $this->currentFile,
                        'status' => 'Error',
                        'message' => 'Invalid filename format. Expected format: PAIRNAME_INTERVAL.csv'
                    ];
                }

                $this->processedFiles++;
                $this->processingProgress = round(($this->processedFiles / $this->totalFiles) * 100);

                $this->dispatch('processingProgress', [
                    'progress' => $this->processingProgress,
                    'currentFile' => $this->currentFile
                ]);
            }

            $this->uploadStatus = "Processing complete. {$this->processedFiles} files processed.";
        } catch (\Exception $e) {
            Log::error("File processing error: " . $e->getMessage());
            $this->uploadStatus = 'Error: ' . $e->getMessage();
            $this->dispatch('errorProcessing', ['message' => $e->getMessage()]);
        } finally {
            // Clear file references properly
            $this->files = [];

            // Reset the file input in the DOM via dispatch
            // This is critical to allow new file uploads
            $this->dispatch('resetFileInput');
        }
    }

    public function resetUploader()
    {
        // Public method to reset the component state
        $this->files = [];
        $this->uploadStatus = '';
        $this->processingProgress = 0;
        $this->totalFiles = 0;
        $this->processedFiles = 0;
        $this->currentFile = '';
        // Keep the fileResults to show history

        $this->dispatch('resetFileInput');
    }

    public function addDroppedFiles($fileCount)
    {
        // This triggers validation and UI updates after Alpine updates the file input
        $this->uploadStatus = "{$fileCount} file(s) selected.";
    }

    private function parseFilename($filename)
    {
        // Remove file extension
        $filename = preg_replace('/\.[^.]+$/', '', $filename);

        // Split by underscore
        $parts = explode('_', $filename);

        if (count($parts) < 2) {
            return null;
        }

        $interval = (int)$parts[count($parts) - 1]; // Last part is interval

        // Everything before the last underscore is considered the pair
        $pairPart = implode('_', array_slice($parts, 0, count($parts) - 1));

        // Use your AssetPair model to get the standardized pair name
        try {
            $pair = AssetPair::getWsNameOrPairName($pairPart);

            if (!$pair) {
                $pair = $this->formatPairWithSlash($pairPart);
            }

            return [
                'pair' => $pair,
                'interval' => $interval
            ];
        } catch (\Exception $e) {
            Log::warning("Could not parse pair name: {$pairPart}. Using as-is.");
            // If the pair cannot be found, return the original pair name
            return [
                'pair' => $pairPart,
                'interval' => $interval
            ];
        }
    }

    private function formatPairWithSlash(string $rawPair): string
    {
        $quoteCurrencies = ['USD', 'USDT', 'BTC', 'ETH', 'EUR']; // Add more as needed

        foreach ($quoteCurrencies as $quote) {
            if (str_ends_with($rawPair, $quote)) {
                $base = substr($rawPair, 0, -strlen($quote));
                return "{$base}/{$quote}";
            }
        }

        return $rawPair; // fallback: return original if no match
    }

    private function processFile($file, $pair, $interval)
    {
        $processedRows = 0;
        $path = $file->getRealPath();
        $rows = [];

        // Safely read the file
        if (($handle = fopen($path, "r")) !== false) {
            try {
                // Check if first row is header
                $firstRow = fgetcsv($handle, 0, ",");

                // If first column isn't numeric, assume it's a header and skip
                if ($firstRow && isset($firstRow[0]) && !is_numeric($firstRow[0])) {
                    // First row is header, don't include it
                } else {
                    // First row contains data, add it
                    $rows[] = $this->formatRow($firstRow);
                }

                // Process remaining rows in batches for memory efficiency
                $batchSize = 1000;
                $rowBatch = [];

                while (($data = fgetcsv($handle, 0, ",")) !== false) {
                    if (is_array($data) && count($data) >= 5) { // Ensure we have at least OHLCV data
                        $rowBatch[] = $this->formatRow($data);

                        // Process in smaller chunks to manage memory
                        if (count($rowBatch) >= $batchSize) {
                            $inserted = $this->upsertOhlcvData($pair, $interval, $rowBatch);
                            $processedRows += $inserted;
                            $rowBatch = []; // Clear batch after processing
                        }
                    }
                }

                // Process any remaining rows
                if (!empty($rowBatch)) {
                    $inserted = $this->upsertOhlcvData($pair, $interval, $rowBatch);
                    $processedRows += $inserted;
                }
            } finally {
                // Always close the file handle
                fclose($handle);
            }

            return [
                'processed' => $processedRows
            ];
        } else {
            throw new \Exception("Failed to open file: {$file->getClientOriginalName()}");
        }
    }

    private function formatRow($row)
    {
        if (!is_array($row) || count($row) < 5) {
            return [0, 0, 0, 0, 0, 0, 0, 0]; // Safe default
        }

        // Expected CSV format: timestamp, open, high, low, close, [vwap], [volume], [trade_count]
        return [
            isset($row[0]) ? (int) $row[0] : 0, // timestamp
            isset($row[1]) ? (float) $row[1] : 0, // open
            isset($row[2]) ? (float) $row[2] : 0, // high
            isset($row[3]) ? (float) $row[3] : 0, // low
            isset($row[4]) ? (float) $row[4] : 0, // close
            isset($row[5]) ? (float) $row[5] : 0, // vwap (optional)
            isset($row[6]) ? (float) $row[6] : 0, // volume (optional)
            isset($row[7]) ? (int) $row[7] : 0,   // trade_count (optional)
        ];
    }

    private function upsertOhlcvData($pair, $interval, $rows)
    {
        if (empty($rows)) {
            return 0;
        }

        try {
            DB::beginTransaction();

            // Get all timestamps from the batch
            $timestamps = array_column($rows, 0);

            // Use the AssetPair model to get the standardized pair name
            $tableName = (string) Str::of($pair)
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

            // Find existing records to avoid duplicates - use raw DB query for better performance
            $existingTimestamps = DB::table($tableName)
                ->where('pair', $pair)
                ->where('interval', $interval)
                ->whereIn('timestamp', $timestamps)
                ->pluck('timestamp')
                ->toArray();

            // Convert to integers for strict comparison
            $existingTimestamps = array_map('intval', $existingTimestamps);

            // Filter out existing records
            $newRows = [];
            foreach ($rows as $data) {
                $timestamp = (int) $data[0];
                if (!in_array($timestamp, $existingTimestamps, true)) {
                    $newRows[] = [
                        'pair'        => $pair,
                        'interval'    => $interval,
                        'timestamp'   => $timestamp,
                        'open_price'  => $data[1],
                        'high_price'  => $data[2],
                        'low_price'   => $data[3],
                        'close_price' => $data[4],
                        'vwap'        => $data[5], // The 6th column is vwap as per your data
                        'volume'      => $data[6], // The 7th column is volume
                        'trade_count' => $data[7], // The 8th column is trade count
                        'created_at'  => Carbon::now(),
                        'updated_at'  => Carbon::now(),
                    ];
                }
            }

            if (empty($newRows)) {
                DB::commit();
                return 0; // nothing to insert
            }

            // Insert in smaller chunks to prevent memory issues
            $chunkSize = 500;
            $inserted = 0;

            foreach (array_chunk($newRows, $chunkSize) as $chunk) {
                DB::table('ohlcv_data')->insert($chunk);
                $inserted += count($chunk);
            }

            DB::commit();
            return $inserted;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e; // Re-throw to be handled by the caller
        }
    }

    public function render()
    {
        return view('livewire.ohlcv-file-upload-dashboard');
    }
}
