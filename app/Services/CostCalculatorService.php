<?php

namespace App\Services;

use App\Models\AiModelProvider;

class CostCalculatorService
{
    /**
     * Calculate cost in credits and USD based on provider strategy and usage metrics.
     * 
     * @param AiModelProvider $provider
     * @param array $metrics keys: count, duration, tokens
     * @return array{credits: int, provider_cost: float, profit_usd: float}
     */
    public function calculate(AiModelProvider $provider, array $metrics): array
    {
        $strategy = $provider->costStrategy;
        
        if (!$strategy) {
             return [
                'credits' => 0,
                'provider_cost' => 0,
                'profit_usd' => 0,
             ];
        }

        $units = 0;
        switch ($strategy->calc_type) {
            case 'fixed':
                $units = 1;
                break;
            case 'per_unit':
                $units = $metrics['count'] ?? 1;
                break;
            case 'per_second':
                $units = $metrics['duration'] ?? 0;
                break;
            case 'per_token':
                $units = $metrics['tokens'] ?? 0;
                break;
            default:
                $units = 1;
        }

        // Calculate Provider Cost in USD
        $providerCostUsd = $units * $strategy->provider_unit_price;

        // Calculate Retail Price in USD (Cost * Markup)
        $retailInUsd = $providerCostUsd * $strategy->markup_multiplier;

        // Convert to Credits
        $credits = ceil($retailInUsd * $strategy->credit_conversion_rate);

        // Apply Minimum Limit
        if ($credits < $strategy->min_credit_limit) {
            $credits = $strategy->min_credit_limit;
            // Recalculate implied retail USD if min limit hit? 
            // Revenue is credits / rate.
            // let's stick to standard formula for profit based on realized credits.
        }

        // Profit = Revenue - Cost
        // Revenue = Credits / Rate
        $revenueUsd = $strategy->credit_conversion_rate > 0 
            ? $credits / $strategy->credit_conversion_rate 
            : 0;

        $profitUsd = $revenueUsd - $providerCostUsd;

        return [
            'credits' => (int) $credits,
            'provider_cost' => (float) $providerCostUsd,
            'profit_usd' => (float) $profitUsd,
        ];
    }
}
