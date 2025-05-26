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
        Schema::create('asset_infos', function (Blueprint $table) {
            $table->id();
            $table->string('asset_id')->unique();
            $table->string('altname')->nullable();
            $table->string('aclass')->nullable();
            $table->integer('decimals')->nullable();
            $table->integer('display_decimals')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
