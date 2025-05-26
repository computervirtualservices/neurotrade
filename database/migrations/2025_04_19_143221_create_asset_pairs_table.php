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
    Schema::create('asset_pairs', function (Blueprint $table) {
        $table->id();
        $table->string('pair_name')->unique();
        $table->string('alt_name')->nullable();
        $table->string('ws_name')->nullable();
        $table->string('base_currency');
        $table->string('quote_currency');
        $table->string('aclass_base')->nullable();
        $table->string('aclass_quote')->nullable();
        $table->string('lot')->nullable();
        $table->integer('cost_decimals')->nullable();
        $table->integer('pair_decimals')->nullable();
        $table->integer('lot_decimals')->nullable();
        $table->integer('lot_multiplier')->nullable();
        $table->json('leverage_buy')->nullable();
        $table->json('leverage_sell')->nullable();
        $table->json('fees')->nullable();
        $table->json('fees_maker')->nullable();
        $table->string('fee_volume_currency')->nullable();
        $table->integer('margin_call')->nullable();
        $table->integer('margin_stop')->nullable();
        $table->string('ordermin')->nullable();
        $table->string('costmin')->nullable();
        $table->string('tick_size')->nullable();
        $table->string('status')->nullable();
        $table->string('interval')->nullable();
        $table->boolean('is_watchlisted')->default(false);
        $table->timestamps();
        
        // Index for faster lookups
        $table->index('is_watchlisted');
        $table->index('status');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_pairs');
    }
};
