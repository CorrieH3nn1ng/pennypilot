<?php

namespace App\Services;

/**
 * MerchantNormalizerService
 *
 * Strips card numbers, dates, and reference codes from transaction descriptions
 * to create clean merchant names for fuzzy matching.
 */
class MerchantNormalizerService
{
    /**
     * Patterns to strip from descriptions
     */
    protected array $stripPatterns = [
        // Card numbers (various formats)
        '/\b\d{4}\s?\d{2}\*+\d{0,4}\b/i',          // 4606 39****1234 or 4606 39****
        '/\b\d{6,}\*+\d*\b/i',                      // 460639****1234
        '/\*{4,}\d{0,4}\b/',                        // ****1234 or ****
        '/\b\d{16}\b/',                             // Full 16-digit card number
        '/\b\d{4}\s+\d{2}\b/',                      // 4606 39 (spaced card prefix)

        // Dates in various formats
        '/\b\d{1,2}(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\d{2,4}\b/i',  // 25Jan2025
        '/\b\d{4}-\d{2}-\d{2}\b/',                  // 2025-01-25
        '/\b\d{2}\/\d{2}\/\d{2,4}\b/',             // 25/01/2025
        '/\b\d{6}\b/',                              // 251201 (YYMMDD)
        '/\b\d{8}\b/',                              // 20250125

        // Reference/account numbers
        '/\bREF[\s:]*[A-Z0-9]+\b/i',                // REF: ABC123
        '/\bEFT[A-Z0-9]{8,}\b/i',                   // EFTBB1YSY6MSN002
        '/\*[A-Z0-9]+\*/i',                         // *ABC123*
        '/\b[A-Z]{2}\d{8,}\b/',                     // BA112010276
        '/\b[A-Z]{1,3}\d{9,}\b/',                   // Account numbers

        // Common noise words
        '/\bPOS\b/i',
        '/\bPURCHASE\b/i',
        '/\bDEBIT\s*ORDER\b/i',
        '/\bPAYMENT\b/i',
        '/\bTRANSFER\b/i',
        '/\b(PTY|LTD|CC|INC)\b/i',
        '/\bSA\b/',                                 // SA suffix

        // Trailing/leading special chars
        '/^[\s\-\*\.]+/',
        '/[\s\-\*\.]+$/',
    ];

    /**
     * Known merchant aliases mapping
     */
    protected array $merchantAliases = [
        'AFRIHOST' => ['AFRIHOST', 'AFRI HOST', 'AFRIHOST PTY'],
        'SHOWMAX' => ['SHOWMAX', 'PP *SHOWMAX', 'PP*SHOWMAX', 'PAYPAL *SHOWMAX'],
        'MTN' => ['MTN SP', 'MTN SERVICE', 'MTN MOBILE', 'MTN SA'],
        'SPAR' => ['SPAR', 'SUPERSPAR', 'SUPER SPAR', 'KWIKSPAR', 'SPAR GROUP'],
        'PNP' => ['PNP', 'PICK N PAY', 'PICK-N-PAY', 'PNP FAMILY', 'PNP LIQUOR'],
        'CHECKERS' => ['CHECKERS', 'CHECKERS HYPER', 'CHECKERS SIXTY60'],
        'WOOLWORTHS' => ['WOOLWORTHS', 'WOOLIES', 'WW FOOD'],
        'TEMU' => ['TEMU', 'TEMU.COM', 'TEMU COM', 'PP*TEMU', 'PAYPAL *TEMU'],
        'TAKEALOT' => ['TAKEALOT', 'TAKEALOT.COM', 'MR D'],
        'NETFLIX' => ['NETFLIX', 'NETFLIX.COM', 'PP*NETFLIX'],
        'YOUTUBE' => ['YOUTUBE', 'GOOGLE *YOUTUBE', 'YOUTUBE PREMIUM'],
        'GOOGLE' => ['GOOGLE', 'GOOGLE *', 'GOOGLE PLAY'],
        'AMAZON' => ['AMAZON', 'AMZN', 'AMAZON PRIME', 'AWS'],
        'UBER' => ['UBER', 'UBER EATS', 'UBER BV', 'UBER TRIP'],
        'BOLT' => ['BOLT', 'BOLT FOOD', 'BOLT RIDE'],
    ];

    /**
     * Normalize a transaction description for matching
     */
    public function normalize(string $description): string
    {
        $normalized = strtoupper(trim($description));

        // Apply strip patterns
        foreach ($this->stripPatterns as $pattern) {
            $normalized = preg_replace($pattern, ' ', $normalized);
        }

        // Collapse multiple spaces
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    /**
     * Extract the likely merchant name from a description
     */
    public function extractMerchant(string $description): string
    {
        $normalized = $this->normalize($description);

        // Check against known aliases first
        foreach ($this->merchantAliases as $merchant => $aliases) {
            foreach ($aliases as $alias) {
                if (stripos($normalized, $alias) !== false) {
                    return $merchant;
                }
            }
        }

        // Take first 2-3 significant words
        $words = array_filter(explode(' ', $normalized), fn($w) => strlen($w) > 2);
        $words = array_values($words);

        return implode(' ', array_slice($words, 0, 3));
    }

    /**
     * Check if a description matches a pattern (with aliases support)
     */
    public function matches(string $description, string $pattern, array $aliases = []): bool
    {
        $normalized = $this->normalize($description);
        $patternUpper = strtoupper($pattern);

        // Check main pattern
        if (stripos($normalized, $patternUpper) !== false) {
            return true;
        }

        // Check aliases
        foreach ($aliases as $alias) {
            if (stripos($normalized, strtoupper($alias)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate match score between description and pattern
     * Returns 0-100
     */
    public function calculateMatchScore(string $description, string $pattern, array $aliases = []): int
    {
        $normalized = $this->normalize($description);
        $patternUpper = strtoupper($pattern);

        // Exact match
        if ($normalized === $patternUpper) {
            return 100;
        }

        // Starts with pattern
        if (str_starts_with($normalized, $patternUpper)) {
            return 90;
        }

        // Contains pattern
        if (stripos($normalized, $patternUpper) !== false) {
            return 80;
        }

        // Check aliases
        foreach ($aliases as $alias) {
            $aliasUpper = strtoupper($alias);
            if (stripos($normalized, $aliasUpper) !== false) {
                return 75;
            }
        }

        // Fuzzy match using similar_text
        $similarity = 0;
        similar_text($normalized, $patternUpper, $similarity);

        if ($similarity > 60) {
            return (int) ($similarity * 0.7); // Scale down fuzzy matches
        }

        return 0;
    }

    /**
     * Get all known merchant aliases
     */
    public function getMerchantAliases(): array
    {
        return $this->merchantAliases;
    }

    /**
     * Add or update merchant aliases
     */
    public function addMerchantAliases(string $merchant, array $aliases): void
    {
        $merchant = strtoupper($merchant);
        if (isset($this->merchantAliases[$merchant])) {
            $this->merchantAliases[$merchant] = array_unique(
                array_merge($this->merchantAliases[$merchant], $aliases)
            );
        } else {
            $this->merchantAliases[$merchant] = $aliases;
        }
    }
}
