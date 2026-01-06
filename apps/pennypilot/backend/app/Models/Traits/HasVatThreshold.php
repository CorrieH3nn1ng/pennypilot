<?php

namespace App\Models\Traits;

use App\Services\FinancialCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * VAT Registration Threshold Tracking
 *
 * SARS Requirements:
 * - SMEs must register for VAT when turnover exceeds R1,000,000 in any 12-month period
 * - This includes rolling 12-month calculations, not just financial years
 *
 * Usage: Add this trait to User or BusinessProfile model
 */
trait HasVatThreshold
{
    /**
     * Calculate rolling 12-month turnover using PostgreSQL INTERVAL
     *
     * @return string BCMath-formatted turnover amount
     */
    public function getRolling12MonthTurnover(): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL: Use native INTERVAL for precise date arithmetic
            $result = DB::table('invoices')
                ->where('user_id', $this->id)
                ->where('status', 'paid')
                ->whereRaw("paid_date >= CURRENT_DATE - INTERVAL '12 months'")
                ->selectRaw('COALESCE(SUM(total), 0) as turnover')
                ->first();
        } else {
            // MySQL fallback
            $result = DB::table('invoices')
                ->where('user_id', $this->id)
                ->where('status', 'paid')
                ->whereRaw('paid_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)')
                ->selectRaw('COALESCE(SUM(total), 0) as turnover')
                ->first();
        }

        return FinancialCalculator::toScale($result->turnover ?? 0);
    }

    /**
     * Get VAT threshold status for this user/business
     *
     * @return array{status: string|null, turnover: string, threshold: string, warning_threshold: string, percentage: float}
     */
    public function getVatThresholdStatus(): array
    {
        $turnover = $this->getRolling12MonthTurnover();
        $status = FinancialCalculator::checkVatThresholdStatus($turnover);

        // Calculate percentage of threshold
        $percentage = 0.0;
        if (bccomp(FinancialCalculator::VAT_THRESHOLD, '0', 2) > 0) {
            $percentage = (float) bcmul(
                bcdiv($turnover, FinancialCalculator::VAT_THRESHOLD, 4),
                '100',
                2
            );
        }

        return [
            'status' => $status,
            'turnover' => $turnover,
            'threshold' => FinancialCalculator::VAT_THRESHOLD,
            'warning_threshold' => FinancialCalculator::VAT_WARNING_THRESHOLD,
            'percentage' => $percentage,
        ];
    }

    /**
     * Check if user should register for VAT
     */
    public function shouldRegisterForVat(): bool
    {
        $status = FinancialCalculator::checkVatThresholdStatus(
            $this->getRolling12MonthTurnover()
        );

        return $status === 'exceeded';
    }

    /**
     * Check if user is approaching VAT threshold (warning zone)
     */
    public function isApproachingVatThreshold(): bool
    {
        $status = FinancialCalculator::checkVatThresholdStatus(
            $this->getRolling12MonthTurnover()
        );

        return $status === 'warning';
    }

    /**
     * Scope: Users exceeding VAT threshold
     */
    public function scopeExceedingVatThreshold(Builder $query): Builder
    {
        $driver = DB::connection()->getDriverName();

        $subquery = $driver === 'pgsql'
            ? "SELECT user_id, COALESCE(SUM(total), 0) as turnover
               FROM invoices
               WHERE status = 'paid'
               AND paid_date >= CURRENT_DATE - INTERVAL '12 months'
               GROUP BY user_id
               HAVING COALESCE(SUM(total), 0) >= " . FinancialCalculator::VAT_THRESHOLD
            : "SELECT user_id, COALESCE(SUM(total), 0) as turnover
               FROM invoices
               WHERE status = 'paid'
               AND paid_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
               GROUP BY user_id
               HAVING COALESCE(SUM(total), 0) >= " . FinancialCalculator::VAT_THRESHOLD;

        return $query->whereIn('id', function ($q) use ($subquery) {
            $q->selectRaw('user_id FROM (' . $subquery . ') AS exceeded');
        });
    }

    /**
     * Scope: Users in VAT warning zone (R800k - R1M)
     */
    public function scopeApproachingVatThreshold(Builder $query): Builder
    {
        $driver = DB::connection()->getDriverName();

        $subquery = $driver === 'pgsql'
            ? "SELECT user_id, COALESCE(SUM(total), 0) as turnover
               FROM invoices
               WHERE status = 'paid'
               AND paid_date >= CURRENT_DATE - INTERVAL '12 months'
               GROUP BY user_id
               HAVING COALESCE(SUM(total), 0) >= " . FinancialCalculator::VAT_WARNING_THRESHOLD .
              " AND COALESCE(SUM(total), 0) < " . FinancialCalculator::VAT_THRESHOLD
            : "SELECT user_id, COALESCE(SUM(total), 0) as turnover
               FROM invoices
               WHERE status = 'paid'
               AND paid_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
               GROUP BY user_id
               HAVING COALESCE(SUM(total), 0) >= " . FinancialCalculator::VAT_WARNING_THRESHOLD .
              " AND COALESCE(SUM(total), 0) < " . FinancialCalculator::VAT_THRESHOLD;

        return $query->whereIn('id', function ($q) use ($subquery) {
            $q->selectRaw('user_id FROM (' . $subquery . ') AS warning');
        });
    }

    /**
     * Get monthly turnover breakdown for the past 12 months
     */
    public function getMonthlyTurnoverBreakdown(): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $results = DB::table('invoices')
                ->where('user_id', $this->id)
                ->where('status', 'paid')
                ->whereRaw("paid_date >= CURRENT_DATE - INTERVAL '12 months'")
                ->selectRaw("TO_CHAR(paid_date, 'YYYY-MM') as month, COALESCE(SUM(total), 0) as turnover")
                ->groupByRaw("TO_CHAR(paid_date, 'YYYY-MM')")
                ->orderByRaw("TO_CHAR(paid_date, 'YYYY-MM')")
                ->get();
        } else {
            $results = DB::table('invoices')
                ->where('user_id', $this->id)
                ->where('status', 'paid')
                ->whereRaw('paid_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)')
                ->selectRaw("DATE_FORMAT(paid_date, '%Y-%m') as month, COALESCE(SUM(total), 0) as turnover")
                ->groupByRaw("DATE_FORMAT(paid_date, '%Y-%m')")
                ->orderByRaw("DATE_FORMAT(paid_date, '%Y-%m')")
                ->get();
        }

        return $results->mapWithKeys(function ($row) {
            return [$row->month => FinancialCalculator::toScale($row->turnover)];
        })->toArray();
    }
}
