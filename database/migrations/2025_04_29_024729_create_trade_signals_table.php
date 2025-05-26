<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trade_signals', function (Blueprint $table) {
            $table->id();
            $table->string('pair_name');                       // e.g. BTC/USD
            $table->string('interval');                   // 1m / 30s / …

            // top-level data
            $table->string('signal');                     // BREAKOUT, REVERSAL, …
            $table->double('confidence', 15, 10);         // 0.5294893130

            // nested “recommendation” block
            $table->string('action');                     // BUY / SELL
            $table->string('strength');                   // STRONG / WEAK …
            $table->double('confidence_percent', 5, 2);   // 52.95
            $table->string('confidence_level');           // LOW / MEDIUM / HIGH
            $table->text('explanation');                  // free-form text

            $table->decimal('suggested_entry',        18, 8)->nullable();
            $table->decimal('suggested_stop_loss',    18, 8)->nullable();
            $table->decimal('suggested_take_profit',  18, 8)->nullable();
            $table->decimal('buy_price', 18, 8)->nullable();
            $table->decimal('sell_price', 18, 8)->nullable();
            // variable-length structures
            $table->json('support_levels');               // e.g. [0.2102]
            $table->json('resistance_levels');            // e.g. [0.2170]
            $table->json('key_indicators');               // nested RSI / MACD / ADX objects

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trade_signals');
    }
};
