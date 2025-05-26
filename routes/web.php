<?php

use App\Livewire\TradeReport;
use App\Livewire\AssetPairsDashboard;
use App\Livewire\OhlcvFileUploadDashboard;
use App\Livewire\ProfitReport;
use App\Livewire\TradingDashboard;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');
    Route::get('/dashboard', TradingDashboard::class)->name('dashboard');
    Route::get('/asset-pairs', AssetPairsDashboard::class)->name('asset-pairs');
    Route::get('/profit-report', ProfitReport::class)->name('profit-report');
    Route::get('/reports', TradeReport::class)->name('reports');
    Route::get('/ohlcv-upload', OhlcvFileUploadDashboard::class)->name('ohlcv-upload');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
