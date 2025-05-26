<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Asset Model
 *
 * Represents a cryptocurrency asset. Asset data is fetched from Kraken's Assets endpoint.
 */
class AssetInfo extends Model
{
    // Allow mass assignment for these fields.
    protected $fillable = ['asset_id', 'altname', 'aclass', 'decimals', 'display_decimals'];

    // Disable timestamps (as we don't need created_at/updated_at for static asset data).
    public $timestamps = false;
}