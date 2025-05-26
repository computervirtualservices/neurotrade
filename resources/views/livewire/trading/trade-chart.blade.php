{{-- Chart container --}}
<div id="chart-wrapper" {{-- poll every N seconds; N = minutes × 60 --}} wire:poll.{{ $interval * 60 }}s="refreshChart">
    <div class="h-full w-full" id="chart" wire:ignore>
        <!-- Chart will be rendered here -->
    </div>
</div>
<!-- Script to render chart -->
@script
    <script>
        // Wait for page to be fully loaded
        document.addEventListener('DOMContentLoaded', () => {
            patchApexCharts();
            setTimeout(initializeChart, 1000);
        });
        
        document.addEventListener('livewire:initialized', () => {
            setTimeout(initializeChart, 1000);
        });
        
        Livewire.on('chartDataUpdated', initializeChart);

        // Handle navigation events
        document.addEventListener('livewire:navigated', () => {
            setTimeout(() => {
                if (document.getElementById('chart')) {
                    console.log('Chart detected after navigation, reinitializing');
                    chartInitAttempts = 0;
                    initializeChart();
                }
            }, 1000);
        });

        // Add visibility detection
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                setTimeout(() => {
                    if (document.getElementById('chart')) {
                        console.log('Page became visible, checking chart');
                        ensureChartExists();
                    }
                }, 1000);
            }
        });

        // Livewire component initialization hook
        Livewire.hook('component.initialized', (component) => {
            if (component.el.id === 'chart-wrapper') {
                setTimeout(initializeChart, 1000);
            }
        });

        // Global chart instance
        let chartInstance = null;
        let chartInitAttempts = 0;
        const MAX_INIT_ATTEMPTS = 3;

        // Patch ApexCharts to handle the 'querySelectorAll' error
        function patchApexCharts() {
            if (typeof ApexCharts !== 'undefined') {
                console.log('Patching ApexCharts...');
                
                // Backup the original prototype methods
                const originalPrototype = Object.getPrototypeOf(ApexCharts.prototype);
                
                // If we can identify the specific method causing issues
                if (originalPrototype.getPreviousPaths) {
                    const originalGetPreviousPaths = originalPrototype.getPreviousPaths;
                    
                    // Override the problematic method
                    originalPrototype.getPreviousPaths = function() {
                        try {
                            // First check if the base element exists
                            if (!this.w || !this.w.globals || !this.w.globals.dom || !this.w.globals.dom.baseEl) {
                                console.warn('ApexCharts: Missing baseEl in getPreviousPaths');
                                // Instead of failing, just set empty previous paths
                                if (this.w && this.w.globals) {
                                    this.w.globals.previousPaths = [];
                                    this.w.globals.allSeriesCollapsed = false;
                                }
                                return;
                            }
                            
                            // If baseEl exists, proceed with original method
                            return originalGetPreviousPaths.apply(this, arguments);
                        } catch (error) {
                            console.warn('ApexCharts: Error in getPreviousPaths, using empty path', error);
                            // Recover from error
                            if (this.w && this.w.globals) {
                                this.w.globals.previousPaths = [];
                                this.w.globals.allSeriesCollapsed = false;
                            }
                        }
                    };
                }
                
                // Create a general error handler for ApexCharts methods
                const safeApexMethod = function(method, context, args) {
                    try {
                        return method.apply(context, args);
                    } catch (error) {
                        console.warn(`ApexCharts: Error in method ${method.name || 'unknown'}`, error);
                        // Return a non-breaking value
                        return null;
                    }
                };
                
                // Add a general monkey patch to handle render/update errors
                const originalRender = ApexCharts.prototype.render;
                ApexCharts.prototype.render = function() {
                    try {
                        return originalRender.apply(this, arguments);
                    } catch (error) {
                        console.error('ApexCharts: Error in render method', error);
                        // Create a basic recovery state
                        if (this.w && this.w.globals && this.w.globals.dom && this.w.globals.dom.elWrap) {
                            // Try to show error in chart
                            const errorDiv = document.createElement('div');
                            errorDiv.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;';
                            errorDiv.innerHTML = 'Chart rendering error occurred.<br>Please try refreshing.';
                            this.w.globals.dom.elWrap.appendChild(errorDiv);
                        }
                    }
                };
                
                console.log('ApexCharts patched successfully');
            } else {
                console.warn('Cannot patch ApexCharts - library not loaded yet');
                // We'll try again later when it might be available
                setTimeout(patchApexCharts, 1000);
            }
        }

        // Ensure chart exists and is rendered
        function ensureChartExists() {
            const chartContainer = document.getElementById('chart');
            if (!chartContainer) return false;
            
            // Check if ApexCharts has rendered anything in our container
            const apexElements = chartContainer.querySelector('.apexcharts-canvas');
            if (!apexElements && chartInstance) {
                console.log('Chart container exists but no ApexCharts elements found. Reinitializing...');
                try {
                    chartInstance.destroy();
                } catch (e) { /* ignore */ }
                chartInstance = null;
                chartInitAttempts = 0;
                setTimeout(initializeChart, 300);
                return false;
            }
            
            return true;
        }

        function initializeChart(data = null) {
            console.log(`Chart initialization requested (attempt ${chartInitAttempts + 1})`);
            
            // Safety: exit if too many attempts
            // if (chartInitAttempts >= MAX_INIT_ATTEMPTS) {
            //     console.warn('Too many chart initialization attempts, aborting');
            //     return;
            // }
            // chartInitAttempts++;
            
            // Safely exit if needed elements aren't available
            const chartContainer = document.getElementById('chart');
            if (!chartContainer) {
                console.error('Chart container not found');
                return;
            }

            // Check if ApexCharts is loaded
            if (typeof ApexCharts === 'undefined') {
                console.error('ApexCharts library not loaded');
                // Try again later
                setTimeout(() => initializeChart(data), 1000);
                return;
            }

            // Make sure our patch is applied
            patchApexCharts();

            // Handle existing chart instance
            if (chartInstance) {
                try {
                    // If we have new data to update
                    if (data && data.chartData && data.chartData.series) {
                        console.log('Updating existing chart with new data');
                        chartInstance.updateSeries(data.chartData.series, true);
                        chartInstance.updateOptions(getChartOptions(data.chartTitle), false, true);
                        console.log('Chart updated successfully');
                    }
                    
                    // Check if chart elements exist in DOM
                    if (!ensureChartExists()) {
                        throw new Error('Chart needs recreation - missing DOM elements');
                    }
                    
                    return; // Exit as chart is working
                    
                } catch (error) {
                    console.error('Error with existing chart, will recreate:', error);
                    try {
                        chartInstance.destroy();
                    } catch (e) {
                        console.warn('Failed to destroy old chart:', e);
                    }
                    chartInstance = null;
                }
            }

            // Clean the container before creating a new chart
            while (chartContainer.firstChild) {
                chartContainer.removeChild(chartContainer.firstChild);
            }

            // First-time initialization with safety checks
            try {
                // Get initial chart options from server data
                const initialOptions = @json($chartData);
                
                // Safety check for data
                if (!initialOptions || !initialOptions.series || initialOptions.series.length === 0) {
                    console.warn('No valid chart data available');
                    // Create empty chart with message
                    const emptyOptions = {
                        chart: {
                            type: 'candlestick',
                            height: 350,
                            animations: {
                                enabled: false
                            },
                            background: '#f8f9fa'
                        },
                        series: [{
                            data: []
                        }],
                        title: {
                            text: 'No chart data available',
                            align: 'center',
                            style: {
                                fontSize: '16px',
                                fontWeight: 'bold'
                            }
                        },
                        noData: {
                            text: 'Loading or no data available',
                            align: 'center',
                            verticalAlign: 'middle'
                        }
                    };
                    
                    // Create chart with minimum options to avoid errors
                    chartInstance = new ApexCharts(chartContainer, emptyOptions);
                    
                    try {
                        chartInstance.render();
                        console.log('Empty chart initialized successfully');
                        // Reset attempt counter on success
                        chartInitAttempts = 0;
                    } catch (renderError) {
                        console.error('Empty chart render failed:', renderError);
                        // Show a basic text message instead
                        chartContainer.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;width:100%;"><p>Chart data unavailable. Please reload page.</p></div>';
                    }
                    return;
                }

                // Create chart options with comprehensive error handling
                const chartOptions = enhanceChartOptions(initialOptions);
                
                // Add an additional safety delay before creating the chart
                setTimeout(() => {
                    try {
                        // Create and render chart
                        chartInstance = new ApexCharts(chartContainer, chartOptions);
                        
                        // Define what happens on success
                        const onSuccess = () => {
                            console.log('Chart initialized successfully');
                            chartInitAttempts = 0; // Reset counter on success
                        };
                        
                        // Define what happens on failure
                        const onFailure = (error) => {
                            console.error('Chart render failed:', error);
                            
                            // Handle rendering failure
                            try {
                                if (chartInstance) chartInstance.destroy();
                            } catch (e) { /* ignore */ }
                            chartInstance = null;
                            
                            // Create simplified fallback
                            const fallbackOptions = {
                                chart: {
                                    type: 'line', // Use simpler chart type
                                    height: 350,
                                    animations: { enabled: false },
                                    background: '#f8f9fa'
                                },
                                series: [{
                                    name: 'Price',
                                    data: initialOptions.series[0].data.map(d => ({
                                        x: d.x,
                                        y: d.y[3] // Use closing price for simplicity
                                    }))
                                }],
                                title: {
                                    text: initialOptions.title?.text || 'Chart (Simplified)',
                                    align: 'left'
                                }
                            };
                            
                            try {
                                chartInstance = new ApexCharts(chartContainer, fallbackOptions);
                                chartInstance.render().then(onSuccess).catch(e => {
                                    console.error('Fallback chart also failed:', e);
                                    chartContainer.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;width:100%;"><p>Unable to load chart. Please try again later.</p></div>';
                                });
                            } catch (e) {
                                console.error('Could not create fallback chart:', e);
                                chartContainer.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;width:100%;"><p>Chart rendering unavailable.</p></div>';
                            }
                        };
                        
                        // Use promise-based rendering with success/failure handling
                        chartInstance.render()
                            .then(onSuccess)
                            .catch(onFailure);
                            
                    } catch (initError) {
                        console.error('Fatal error in chart initialization:', initError);
                        chartContainer.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;width:100%;"><p>Error initializing chart.</p></div>';
                    }
                }, 50);
                
            } catch (error) {
                console.error('Unexpected error in chart flow:', error);
                const chartContainer = document.getElementById('chart');
                if (chartContainer) {
                    chartContainer.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;width:100%;"><p>Chart unavailable due to an error.</p></div>';
                }
            }
        }

        function enhanceChartOptions(baseOptions) {
            // Create a deep copy of the options
            const options = JSON.parse(JSON.stringify(baseOptions));

            // Set chart type and defaults
            options.chart = options.chart || {};
            options.chart.type = 'candlestick';
            options.chart.animations = options.chart.animations || { enabled: false };
            
            // Add error handling events
            options.chart.events = options.chart.events || {};
            const originalEvents = {...options.chart.events};
            
            options.chart.events = {
                ...originalEvents,
                beforeMount: function(chartContext) {
                    if (originalEvents.beforeMount) {
                        try {
                            originalEvents.beforeMount.call(this, chartContext);
                        } catch (e) {
                            console.warn('Error in chart beforeMount event', e);
                        }
                    }
                    // Additional safety checks before mounting
                    if (!chartContext.w.globals.dom.baseEl) {
                        console.warn('baseEl missing in beforeMount');
                    }
                },
                mounted: function(chartContext) {
                    if (originalEvents.mounted) {
                        try {
                            originalEvents.mounted.call(this, chartContext);
                        } catch (e) {
                            console.warn('Error in chart mounted event', e);
                        }
                    }
                    console.log('Chart mounted successfully');
                    // Reset attempts counter
                    chartInitAttempts = 0;
                },
                updated: function(chartContext) {
                    if (originalEvents.updated) {
                        try {
                            originalEvents.updated.call(this, chartContext);
                        } catch (e) {
                            console.warn('Error in chart updated event', e);
                        }
                    }
                },
                // Handle any potential error
                error: function(chartContext, error) {
                    console.error('ApexCharts error event triggered:', error);
                    if (originalEvents.error) {
                        try {
                            originalEvents.error.call(this, chartContext, error);
                        } catch (e) {
                            console.warn('Error in chart error event handler', e);
                        }
                    }
                }
            };
            
            // Ensure series data
            options.series = options.series || [{ data: [] }];
            
            // Ensure title is properly set
            options.title = options.title || {};
            options.title.text = options.title.text || 'Click on Trading Watchlist to load chart';
            options.title.align = options.title.align || "left";
            options.title.style = options.title.style || {
                fontSize: '16px',
                fontWeight: 'bold'
            };

            // Configure tooltip
            options.tooltip = options.tooltip || {};
            options.tooltip.custom = function(opts) {
                try {
                    const {
                        seriesIndex,
                        dataPointIndex,
                        w
                    } = opts;
                    
                    // Safety check
                    if (!w.config.series[seriesIndex] || 
                        !w.config.series[seriesIndex].data || 
                        !w.config.series[seriesIndex].data[dataPointIndex]) {
                        return '<div>No data available</div>';
                    }
                    
                    const data = w.config.series[seriesIndex].data[dataPointIndex];
                    if (!data || !data.x || !data.y || !Array.isArray(data.y) || data.y.length < 4) {
                        return '<div>Incomplete data point</div>';
                    }
                    
                    const date = new Date(data.x);

                    return `
                    <div class="apexcharts-tooltip-box">
                        <div style="padding:5px 10px">
                            <div><b>Time:</b> ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>
                            <div><b>Open:</b> $${data.y[0]}</div>
                            <div><b>High:</b> $${data.y[1]}</div>
                            <div><b>Low:</b> $${data.y[2]}</div>
                            <div><b>Close:</b> $${data.y[3]}</div>
                        </div>
                    </div>
                    `;
                } catch (error) {
                    console.error('Tooltip error:', error);
                    return '<div>Error generating tooltip</div>';
                }
            };

            // Configure x-axis date formatting
            options.xaxis = options.xaxis || {};
            options.xaxis.labels = options.xaxis.labels || {};
            options.xaxis.labels.formatter = function(val) {
                try {
                    return new Date(val).toLocaleDateString();
                } catch (e) {
                    return val;
                }
            };

            // Add empty data handling
            options.noData = options.noData || {
                text: 'No data available',
                align: 'center',
                verticalAlign: 'middle',
                offsetX: 0,
                offsetY: 0,
                style: {
                    color: '#888',
                    fontSize: '14px'
                }
            };

            return options;
        }

        function getChartOptions(chartTitle) {
            return {
                chart: {
                    type: 'candlestick',
                    animations: {
                        enabled: false
                    },
                    events: {
                        // Add error handling
                        error: function(chartContext, error) {
                            console.error('Chart error in update:', error);
                        }
                    }
                },
                xaxis: {
                    labels: {
                        formatter: function(val) {
                            try {
                                return new Date(val).toLocaleDateString();
                            } catch (e) {
                                return val;
                            }
                        }
                    }
                },
                title: {
                    text: chartTitle || 'Click on Trading Watchlist to load chart',
                    align: 'left',
                    style: {
                        fontSize: '16px',
                        fontWeight: 'bold'
                    }
                },
                tooltip: {
                    custom: function(opts) {
                        try {
                            const {
                                seriesIndex,
                                dataPointIndex,
                                w
                            } = opts;
                            
                            // Safety check
                            if (!w || !w.config || !w.config.series || 
                                !w.config.series[seriesIndex] || 
                                !w.config.series[seriesIndex].data || 
                                !w.config.series[seriesIndex].data[dataPointIndex]) {
                                return '<div>No data available</div>';
                            }
                            
                            const data = w.config.series[seriesIndex].data[dataPointIndex];
                            if (!data || !data.x || !data.y || !Array.isArray(data.y) || data.y.length < 4) {
                                return '<div>Incomplete data point</div>';
                            }
                            
                            const date = new Date(data.x);

                            return `
                            <div class="apexcharts-tooltip-box">
                                <div style="padding:5px 10px">
                                    <div><b>Time:</b> ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>
                                    <div><b>Open:</b> $${data.y[0]}</div>
                                    <div><b>High:</b> $${data.y[1]}</div>
                                    <div><b>Low:</b> $${data.y[2]}</div>
                                    <div><b>Close:</b> $${data.y[3]}</div>
                                </div>
                            </div>
                            `;
                        } catch (error) {
                            console.error('Tooltip error:', error);
                            return '<div>Error generating tooltip</div>';
                        }
                    }
                },
                noData: {
                    text: 'No data available',
                    align: 'center',
                    verticalAlign: 'middle'
                }
            };
        }
    </script>
@endscript