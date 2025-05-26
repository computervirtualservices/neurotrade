<?php

declare(strict_types=1);

namespace App\Services\CryptoML;

use App\Services\CryptoML\Contracts\FeatureExtractorInterface;
use Laratrade\Trader\Contracts\Trader;
use ReflectionClass;
use ReflectionMethod;

final class FeatureExtractor implements FeatureExtractorInterface
{
    /** @var string[] Map vector index → human-readable feature key */
    private static array $featureMap;

    private Trader $trader;

    public function __construct(Trader $trader)
    {
        $this->trader = $trader;
        self::initFeatureMap();
    }

    public static function getFeatureMap(): array
    {
        self::initFeatureMap();
        return self::$featureMap;
    }

    /**
     * Reflect all public Trader methods into an index→name map.
     */
    private static function initFeatureMap(): void
    {
        if (isset(self::$featureMap)) {
            return;
        }

        $reflect  = new ReflectionClass(Trader::class);
        $methods  = array_filter(
            $reflect->getMethods(ReflectionMethod::IS_PUBLIC),
            fn(ReflectionMethod $m) => $m->getDeclaringClass()->getName() === Trader::class
        );

        usort($methods, fn($a, $b) => strcmp($a->getName(), $b->getName()));

        self::$featureMap = array_values(array_map(
            fn(ReflectionMethod $m) => $m->getName(),
            $methods
        ));
    }

    /**
     * Build the N-dimensional feature vector used by the ML model.
     *
     * @param  array<int,array<string,mixed>>  $indicators    Full indicator history
     * @param  array{y:array{0:float,1:float,2:float,3:float,4:int}}  $currentCandle
     * @return array<float|int>
     */
    public function extract(array $indicators, array $currentCandle, int $i): array
    {
        // Destructure current candle
        [$open, $high, $low, $close, $vol] = $currentCandle['y'];

        // Grab the latest indicator snapshot
        // $last = end($indicators) ?: [];
        $last = $indicators[$i] ?? [];

        $features = [];

        // 1) Push every raw indicator value for each Trader method
        //    If your indicator history stores them under the same method name:
        $valid = array_intersect(self::$featureMap, array_keys($last));
        $features = [];
        foreach ($valid as $feature) {
            $features[] = $last[$feature];
        }

        // 2) Tack on any computed or custom features (e.g. momentum)
        $momentum = $this->computeMomentumFeatures(
            $indicators,
            $open,
            $high,
            $low,
            $close,
            $vol,
            $last['obv'] ?? 0.0
        );

        return array_merge($features, array_values($momentum));
    }

    private function computeMomentumFeatures(
        array $indicators,
        float $open,
        float $high,
        float $low,
        float $close,
        float $volume,
        float $obv
    ): array {
        $count = count($indicators);
        if ($count < 2) {
            return array_fill(0, 6, 0.0);
        }

        $prev      = $indicators[$count - 2];
        $prevClose = $prev['close']  ?? $open;
        $prevVol   = $prev['volume'] ?? $volume;
        $prevObv   = $prev['obv']    ?? $obv;

        $roc       = $prevClose != 0.0 ? (($close - $prevClose) / $prevClose) * 100 : 0.0;
        $volDiff   = $prevVol   != 0    ? (($volume - $prevVol) / $prevVol) * 100     : 0.0;
        $obvGrad   = $prevObv   != 0.0  ? ($obv - $prevObv) / abs($prevObv)          : 0.0;

        $slice8    = array_slice($indicators, -8);
        $maxHigh   = max(array_column($slice8, 'high') ?: [0.0]);
        $minLow    = min(array_column($slice8, 'low')  ?: [PHP_FLOAT_MAX]);
        $hh        = $high > $maxHigh ? 1 : 0;
        $hl        = $low  > $minLow  ? 1 : 0;

        $slice20   = array_slice($indicators, -20);
        $resLevels = array_column($slice20, 'high');
        $supLevels = array_column($slice20, 'low');
        $res       = $this->findNextResistance($resLevels, $close);
        $sup       = $this->findNextSupport($supLevels, $close);
        $supDist   = $close != 0.0 ? ($close - $sup) / $close      : 0.0;
        $resDist   = $close != 0.0 ? ($res   - $close) / $close    : 0.0;
        $srProx    = ($supDist && $resDist)
            ? ($supDist - $resDist) / max($supDist, $resDist)
            : 0.0;

        return [
            'price_rate_of_change'         => $roc,
            'higher_high'                  => $hh,
            'higher_low'                   => $hl,
            'volume_change'                => $volDiff,
            'obv_gradient'                 => $obvGrad,
            'support_resistance_proximity' => $srProx,
        ];
    }

    private function findNextResistance(array $highs, float $price): float
    {
        $next = PHP_FLOAT_MAX;
        foreach ($highs as $h) {
            if ($h > $price && $h < $next) {
                $next = $h;
            }
        }
        return $next === PHP_FLOAT_MAX ? $price * 1.05 : $next;
    }

    private function findNextSupport(array $lows, float $price): float
    {
        $next = 0.0;
        foreach ($lows as $l) {
            if ($l < $price && $l > $next) {
                $next = $l;
            }
        }
        return $next <= 0.0 ? $price * 0.95 : $next;
    }
}
