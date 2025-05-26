<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ohlcv_data', function (Blueprint $table) {
            $table->id();
            $table->string('pair', 16);         // e.g., 1INCH/USD
            $table->string('interval', 6);      // e.g., 1, 15, 60, 1440
            $table->integer('timestamp');      // candle time
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
    }

    public function down(): void
    {
        Schema::dropIfExists('ohlcv_data');
    }
};
