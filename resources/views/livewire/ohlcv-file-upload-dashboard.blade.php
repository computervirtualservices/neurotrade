<div>
    <div class="m-2 border border-neutral-200 dark:border-neutral-700 bg-white rounded-lg">
        <div class="flex flex-col items-start justify-between gap-4 mb-6 sm:flex-row sm:items-center">
            <div class="p-4">
                <h1 class="text-2xl font-bold text-gray-800">OHLCV Data Bulk Uploader</h1>
                <p class="text-gray-600">Upload Historical OHLCVT Data</p>
            </div>
        </div>
    </div>
    <div class="m-2 border border-neutral-200 dark:border-neutral-700 bg-white rounded-lg">
        <div class="overflow-hidden bg-white rounded-lg shadow">
            <div class="overflow-x-auto">
                <form wire:submit.prevent="processFiles" enctype="multipart/form-data">
                    <!-- File upload area -->
                    <div class="m-4">
                        <label for="files" class="block text-sm font-medium text-gray-700 mb-1">OHLCV CSV
                            Files</label>

                        <div 
                            x-data="{ 
                                isHovering: false,
                                resetInput: function() {
                                    this.$refs.fileInput.value = '';
                                }
                            }" 
                            x-ref="dropArea" 
                            x-on:dragover.prevent="isHovering = true"
                            x-on:dragleave.prevent="isHovering = false"
                            x-on:drop.prevent="
                                isHovering = false;
                                const droppedFiles = Array.from($event.dataTransfer.files);
                                const input = $refs.fileInput;

                                // Merge new files into input (Livewire won't do this automatically)
                                const dataTransfer = new DataTransfer();
                                Array.from(input.files).forEach(file => dataTransfer.items.add(file));
                                droppedFiles.forEach(file => dataTransfer.items.add(file));

                                input.files = dataTransfer.files;
                                input.dispatchEvent(new Event('change', { bubbles: true }));

                                // Let Livewire know we dropped files
                                $wire.addDroppedFiles(dataTransfer.files.length);
                            "
                            @resetFileInput.window="resetInput()"
                            class="border-2 border-dashed p-6 m-4 rounded-lg cursor-pointer"
                            :class="{ 'border-blue-500 bg-blue-50': isHovering, 'border-gray-300 bg-white': !isHovering }">
                            <p class="text-sm text-gray-500">Drag & drop CSV files here or click to browse</p>

                            <!-- File input (triggered by drop and browse) -->
                            <input x-ref="fileInput" id="file-upload" type="file" class="hidden" wire:model="files"
                                accept=".csv,.txt" multiple />
                            <button type="button" class="mt-2 text-blue-600 text-sm underline"
                                @click="$refs.fileInput.click()">Browse Files</button>
                        </div>
                    </div>

                    <!-- File info and status -->
                    @if (count($files) > 0)
                        <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                            <h3 class="text-sm font-medium text-gray-700 mb-2">Selected Files:</h3>
                            <ul class="text-sm space-y-1">
                                @foreach ($files as $file)
                                    <li class="flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500 mr-2"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <span>
                                            {{ is_object($file) && method_exists($file, 'getClientOriginalName')
                                                ? $file->getClientOriginalName()
                                                : (is_array($file) && isset($file['name'])
                                                    ? $file['name']
                                                    : 'Unknown file') }}
                                            ({{ is_object($file) && method_exists($file, 'getSize')
                                                ? round($file->getSize() / 1024, 2)
                                                : (is_array($file) && isset($file['size'])
                                                    ? round($file['size'] / 1024, 2)
                                                    : 'N/A') }}
                                            KB)
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Status message -->
                    @if ($uploadStatus)
                        <div
                            class="mb-4 ml-4 p-3 rounded-lg {{ str_contains($uploadStatus, 'Error') ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700' }}">
                            {{ $uploadStatus }}
                        </div>
                    @endif

                    <!-- Process buttons -->
                    <div class="flex justify-end p-4 space-x-2">
                        @if (count($files) > 0)
                            <button type="button"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
                                wire:click="resetUploader">
                                Clear Files
                            </button>
                            
                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="processFiles">Process Files</span>
                                <span wire:loading wire:target="processFiles">
                                    Processing...
                                </span>
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- Processing Results -->
        @if (count($fileResults) > 0)
            <div class="m-2 border-t p-4">
                <h3 class="text-lg font-semibold mb-3">Processing Results</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    File</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Pair</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Interval</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Result</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($fileResults as $result)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $result['file'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $result['pair'] ?? '—' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $result['interval'] ?? '—' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $result['status'] === 'Success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $result['status'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $result['message'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div class="m-2 border border-neutral-200 dark:border-neutral-700 bg-white rounded-lg">
        <div class="m-4 sm:flex-row sm:items-center">
            <h3 class="text-lg font-semibold mb-2">CSV Format & Naming Instructions</h3>
            <p class="text-sm text-gray-600 mb-2">Your CSV files should follow these guidelines:</p>

            <div class="mb-4">
                <h4 class="text-sm font-semibold text-gray-700">File Naming:</h4>
                <p class="text-sm text-gray-600 ml-2">PAIRNAME_INTERVAL.csv (e.g., ZEUSEUR_1440.csv)</p>
            </div>

            <div>
                <h4 class="text-sm font-semibold text-gray-700">CSV Column Format:</h4>
                <ol class="list-decimal list-inside text-sm text-gray-600 ml-2">
                    <li>Timestamp (Unix timestamp in seconds)</li>
                    <li>Open price</li>
                    <li>High price</li>
                    <li>Low price</li>
                    <li>Close price</li>
                    <li>VWAP (optional)</li>
                    <li>Volume (optional)</li>
                    <li>Trade Count (optional)</li>
                </ol>
                <p class="text-sm text-gray-600 mt-2">Example:
                    <code>1620000000,34500.50,35000.25,34200.75,34800.00,34750.25,12.5,320</code>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('livewire:initialized', () => {
        // Listen for the resetFileInput event
        Livewire.on('resetFileInput', () => {
            console.log('Resetting file input');
            // Dispatch an Alpine event to reset the file input
            window.dispatchEvent(new CustomEvent('resetFileInput'));
        });

        // Listen for processing progress updates
        Livewire.on('processingProgress', (data) => {
            console.log('Processing progress:', data.progress);
        });

        // Listen for error processing
        Livewire.on('errorProcessing', (data) => {
            console.error('Processing error:', data.message);
        });
    });
</script>