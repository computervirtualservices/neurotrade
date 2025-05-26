<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CryptoML\Contracts\FeatureExtractorInterface;
use App\Services\CryptoML\Contracts\ModelManagerInterface;
use App\Services\CryptoML\Contracts\MomentumDetectorInterface;
use App\Services\CryptoML\Contracts\RecommendationBuilderInterface;
use App\Services\CryptoML\Contracts\SupportResistanceFinderInterface;
use App\Services\CryptoML\Contracts\MetricsCalculatorInterface;
use App\Services\CryptoML\Contracts\SignalLabelerInterface;
use App\Services\CryptoML\FeatureExtractor;
use App\Services\CryptoML\ModelManager;
use App\Services\CryptoML\MomentumDetector;
use App\Services\CryptoML\RecommendationBuilder;
use App\Services\CryptoML\SupportResistanceFinder;
use App\Services\CryptoML\MetricsCalculator;
use App\Services\CryptoML\SignalLabeler;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;

class CryptoMLServiceProvider extends ServiceProvider
{
    /**
     * Register CryptoML services and bindings.
     */
    public function register(): void
    {
        // Bind interfaces to concrete implementations
        $this->app->bind(FeatureExtractorInterface::class, FeatureExtractor::class);
        $this->app->bind(ModelManagerInterface::class, function ($app) {
            return new ModelManager(
                config('cryptoml.model_path'),
                config('cryptoml.confidence_threshold'),
                config('cryptoml.cache_ttl'),
                $app->make(Filesystem::class),
                $app->make(CacheRepository::class),
                $app->make(LoggerInterface::class),
                config('cryptoml.is_regressor'),
            );
        });
        $this->app->bind(MomentumDetectorInterface::class, MomentumDetector::class);
        $this->app->bind(RecommendationBuilderInterface::class, RecommendationBuilder::class);
        $this->app->bind(SupportResistanceFinderInterface::class, SupportResistanceFinder::class);
        $this->app->bind(MetricsCalculatorInterface::class, MetricsCalculator::class);
        $this->app->bind(SignalLabelerInterface::class, SignalLabeler::class);
    }

    /**
     * Boot any package services.
     */
    public function boot(): void
    {
        // Optionally load configuration or routes if needed
    }
}
