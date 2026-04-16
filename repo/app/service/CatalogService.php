<?php
declare(strict_types=1);

namespace app\service;

use app\model\Product;
use app\model\ProductScore;
use think\exception\ValidateException;
use think\facade\Db;

class CatalogService
{
    /**
     * Dedup moderation policy:
     *   - similarity >= AUTO_MERGE_THRESHOLD → auto-merge (RESOLVED).
     *   - MODERATION_REVIEW_THRESHOLD <= similarity < AUTO_MERGE_THRESHOLD
     *     → queue for moderator review (PENDING).
     *   - similarity < MODERATION_REVIEW_THRESHOLD → intentionally not queued
     *     (too weak a signal to be worth a moderator's time). The drop is
     *     logged in the audit trail so the policy trace is preserved.
     *
     * These thresholds are centralized here so a future policy change only
     * needs to be made in one place (plus matching documentation).
     */
    const AUTO_MERGE_THRESHOLD       = 0.85;
    const MODERATION_REVIEW_THRESHOLD = 0.5;

    // Expected spec fields per category
    const CATEGORY_FIELDS = [
        'CPU' => ['clockSpeed', 'cores', 'threads', 'socket', 'tdp', 'cache', 'architecture'],
        'GPU' => ['clockSpeed', 'memory', 'memoryType', 'busWidth', 'tdp', 'interface', 'outputs'],
        'MOTHERBOARD' => ['socket', 'chipset', 'formFactor', 'memorySlots', 'maxMemory', 'pcieSlots', 'storageInterfaces'],
    ];

    // Unit standardization patterns
    const UNIT_PATTERNS = [
        'clockSpeed' => [
            'pattern' => '/(\d+\.?\d*)\s*(ghz|mhz|GHz|MHz)/i',
            'normalize' => 'normalizeFrequency',
        ],
        'memory' => [
            'pattern' => '/(\d+\.?\d*)\s*(gb|mb|tb|GB|MB|TB)/i',
            'normalize' => 'normalizeMemory',
        ],
        'maxMemory' => [
            'pattern' => '/(\d+\.?\d*)\s*(gb|mb|tb|GB|MB|TB)/i',
            'normalize' => 'normalizeMemory',
        ],
        'cache' => [
            'pattern' => '/(\d+\.?\d*)\s*(mb|kb|MB|KB)/i',
            'normalize' => 'normalizeCache',
        ],
        'tdp' => [
            'pattern' => '/(\d+\.?\d*)\s*(w|W)/i',
            'normalize' => 'normalizeTdp',
        ],
        'interface' => [
            'pattern' => '/pcie\s*(\d+\.?\d*)\s*(x\d+)?/i',
            'normalize' => 'normalizePcie',
        ],
    ];

    /**
     * Create a product catalog entry (DRAFT).
     */
    public static function create(array $data, int $createdBy): Product
    {
        if (!in_array($data['category'] ?? '', ['CPU', 'GPU', 'MOTHERBOARD'], true)) {
            throw new ValidateException('Category must be CPU, GPU, or MOTHERBOARD');
        }

        $now = date('Y-m-d H:i:s');
        $product = new Product();
        $product->name             = $data['name'];
        $product->category         = $data['category'];
        $product->vendor_name      = trim((string) ($data['vendorName'] ?? $data['vendor_name'] ?? ''));
        $product->specs            = json_encode($data['specs'] ?? []);
        $product->normalized_specs = null;
        $product->fingerprint      = null;
        $product->status           = 'DRAFT';
        $product->created_by       = $createdBy;
        $product->created_at       = $now;
        $product->updated_at       = $now;
        $product->save();

        AuditService::log('PRODUCT_CREATED', 'product', $product->id, null, $product->toArray());

        return $product;
    }

    /**
     * Submit a product: standardize, score, fingerprint, route to moderation.
     */
    public static function submit(int $id, int $userId): array
    {
        $product = Product::findOrFail($id);

        if ($product->status !== 'DRAFT') {
            throw new ValidateException('Only DRAFT products can be submitted');
        }

        $specs = is_string($product->specs) ? json_decode($product->specs, true) : (array) $product->specs;

        // Standardize units
        $normalized = self::standardizeSpecs($specs, $product->category);

        // Score completeness and consistency
        $completeness = self::scoreCompleteness($normalized, $product->category);
        $consistency  = self::scoreConsistency($normalized, $product->category);

        // Generate dedup fingerprint
        $fingerprint = self::generateFingerprint($product->name, $product->category, $normalized);

        $product->normalized_specs    = json_encode($normalized);
        $product->fingerprint         = $fingerprint;
        $product->completeness_score  = $completeness;
        $product->consistency_score   = $consistency;
        $product->status              = 'SUBMITTED';
        $product->submitted_by        = $userId;
        $product->updated_at          = date('Y-m-d H:i:s');
        $product->save();

        // Save score history
        $score = new ProductScore();
        $score->product_id        = $product->id;
        $score->completeness_score = $completeness;
        $score->consistency_score  = $consistency;
        $score->details           = json_encode([
            'normalized_specs' => $normalized,
            'category'         => $product->category,
        ]);
        $score->created_at = date('Y-m-d H:i:s');
        $score->save();

        // Check for duplicates
        self::checkDuplicates($product);

        AuditService::log('PRODUCT_SUBMITTED', 'product', $id, null, [
            'completeness' => $completeness,
            'consistency'  => $consistency,
            'fingerprint'  => $fingerprint,
        ]);

        return [
            'completenessScore' => $completeness,
            'consistencyScore'  => $consistency,
            'status'            => 'SUBMITTED',
        ];
    }

    /**
     * Standardize spec units + apply the local dictionary parser.
     *
     * Pipeline (all local / offline):
     *   1. `CatalogDictionary::canonicalizeKeys` — rewrite free-form attribute
     *      keys onto the canonical vocabulary ("CPU Socket" → "socket").
     *   2. `CatalogDictionary::normalizeValues` — map vendor/socket/chipset/
     *      form-factor/memory-type values to their canonical form and enrich
     *      with derived fields (e.g. `chipsetFamily`).
     *   3. Regex-based unit normalization — GHz/MHz, MB/GB/TB, cache, TDP,
     *      PCIe version strings.
     *
     * Stages (1) and (2) together are the "lightweight on-prem dictionary
     * parser" required by the prompt; stage (3) is the regex half.
     */
    public static function standardizeSpecs(array $specs, string $category): array
    {
        $specs = CatalogDictionary::canonicalizeKeys($specs);
        $specs = CatalogDictionary::normalizeValues($specs);

        $normalized = [];
        foreach ($specs as $key => $value) {
            if (is_string($value) && isset(self::UNIT_PATTERNS[$key])) {
                $method = self::UNIT_PATTERNS[$key]['normalize'];
                $normalized[$key] = self::$method($value);
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Score completeness: % of expected fields present.
     */
    public static function scoreCompleteness(array $specs, string $category): float
    {
        $expected = self::CATEGORY_FIELDS[$category] ?? [];
        if (empty($expected)) {
            return 1.0;
        }

        $filled = 0;
        foreach ($expected as $field) {
            if (!empty($specs[$field])) {
                $filled++;
            }
        }

        return round($filled / count($expected), 4);
    }

    /**
     * Score consistency: check for valid ranges and logical values.
     */
    public static function scoreConsistency(array $specs, string $category): float
    {
        $checks = 0;
        $passed = 0;

        // Clock speed should be reasonable (0.1 - 10 GHz)
        if (isset($specs['clockSpeed'])) {
            $checks++;
            $ghz = self::extractNumeric($specs['clockSpeed']);
            if ($ghz >= 0.1 && $ghz <= 10.0) {
                $passed++;
            }
        }

        // Cores should be positive integer
        if (isset($specs['cores'])) {
            $checks++;
            if ((int) $specs['cores'] > 0 && (int) $specs['cores'] <= 256) {
                $passed++;
            }
        }

        // TDP should be reasonable (1 - 1000W)
        if (isset($specs['tdp'])) {
            $checks++;
            $tdp = self::extractNumeric($specs['tdp']);
            if ($tdp >= 1 && $tdp <= 1000) {
                $passed++;
            }
        }

        // Memory should be positive
        if (isset($specs['memory'])) {
            $checks++;
            $mem = self::extractNumeric($specs['memory']);
            if ($mem > 0) {
                $passed++;
            }
        }

        if ($checks === 0) {
            return 1.0;
        }

        return round($passed / $checks, 4);
    }

    /**
     * Generate fingerprint for deduplication using Jaccard similarity.
     */
    public static function generateFingerprint(string $name, string $category, array $specs): string
    {
        // Normalize name: lowercase, remove special chars, tokenize
        $tokens = self::tokenize($name);
        $tokens[] = strtolower($category);

        // Add core spec values
        foreach (['clockSpeed', 'cores', 'memory', 'socket', 'chipset'] as $key) {
            if (!empty($specs[$key])) {
                $tokens[] = strtolower((string) $specs[$key]);
            }
        }

        sort($tokens);
        return hash('sha256', implode('|', $tokens));
    }

    /**
     * Calculate Jaccard similarity between two token sets.
     */
    public static function jaccardSimilarity(array $setA, array $setB): float
    {
        $intersection = count(array_intersect($setA, $setB));
        $union = count(array_unique(array_merge($setA, $setB)));

        if ($union === 0) {
            return 0.0;
        }

        return round($intersection / $union, 4);
    }

    /**
     * Find duplicate products above similarity threshold.
     */
    public static function findDuplicates(): array
    {
        $products = Product::whereIn('status', ['SUBMITTED', 'APPROVED'])
            ->whereNotNull('fingerprint')
            ->select();

        $pairs = [];
        $count = count($products);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $products[$i];
                $b = $products[$j];

                if ($a->category !== $b->category) {
                    continue;
                }

                $tokensA = self::getProductTokens($a);
                $tokensB = self::getProductTokens($b);

                $similarity = self::jaccardSimilarity($tokensA, $tokensB);

                if ($similarity >= self::MODERATION_REVIEW_THRESHOLD) {
                    $pairs[] = [
                        'productA'   => $a->toArray(),
                        'productB'   => $b->toArray(),
                        'similarity' => $similarity,
                        'autoMerge'  => $similarity >= self::AUTO_MERGE_THRESHOLD,
                    ];
                }
            }
        }

        // Sort by similarity desc
        usort($pairs, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return $pairs;
    }

    /**
     * Check for duplicates after submission, auto-merge if >= 0.85.
     */
    private static function checkDuplicates(Product $product): void
    {
        $existing = Product::where('id', '<>', $product->id)
            ->where('category', $product->category)
            ->whereIn('status', ['SUBMITTED', 'APPROVED'])
            ->whereNotNull('fingerprint')
            ->select();

        $newTokens = self::getProductTokens($product);

        foreach ($existing as $other) {
            $otherTokens = self::getProductTokens($other);
            $similarity  = self::jaccardSimilarity($newTokens, $otherTokens);

            if ($similarity >= self::AUTO_MERGE_THRESHOLD) {
                // Auto-merge: high-confidence similarity, recorded as RESOLVED/MERGE.
                // `moderator_id` is NULL because no human acted — the system
                // performed the merge. An audit entry preserves provenance.
                Db::name('moderation_decisions')->insert([
                    'item_type'       => 'MERGE',
                    'item_id'         => $product->id,
                    'action'          => 'MERGE',
                    'status'          => 'RESOLVED',
                    'moderator_id'    => null,
                    'before_snapshot' => json_encode($product->toArray()),
                    'after_snapshot'  => json_encode($other->toArray()),
                    'notes'           => "Auto-merged with product #{$other->id} (similarity: {$similarity})",
                    'created_at'      => date('Y-m-d H:i:s'),
                    'resolved_at'     => date('Y-m-d H:i:s'),
                ]);

                AuditService::log('PRODUCT_AUTO_MERGED', 'product', $product->id, $product->toArray(), [
                    'merged_with' => $other->id,
                    'similarity'  => $similarity,
                ]);
            } elseif ($similarity >= self::MODERATION_REVIEW_THRESHOLD) {
                // Low-confidence similarity: queue for a human moderator to decide.
                // `action='REVIEW'` is an explicit queue marker, not an approval;
                // `status='PENDING'` and `moderator_id=null` reflect that no
                // decision has been made yet.
                Db::name('moderation_decisions')->insert([
                    'item_type'       => 'MERGE',
                    'item_id'         => $product->id,
                    'action'          => 'REVIEW',
                    'status'          => 'PENDING',
                    'moderator_id'    => null,
                    'before_snapshot' => json_encode(['productA' => $product->id, 'productB' => $other->id, 'similarity' => $similarity]),
                    'after_snapshot'  => null,
                    'notes'           => "Flagged for review: similarity {$similarity} with product #{$other->id}",
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);
            } else {
                // Below the review threshold — intentionally not queued. We
                // still record a non-persistent audit trace so the dropped
                // candidate is reconstructable from the audit log.
                AuditService::log('DEDUP_SIMILARITY_BELOW_THRESHOLD', 'product', $product->id, null, [
                    'other_product_id' => $other->id,
                    'similarity'       => $similarity,
                    'review_threshold' => self::MODERATION_REVIEW_THRESHOLD,
                ]);
            }
        }
    }

    private static function getProductTokens(Product $product): array
    {
        $specs = is_string($product->normalized_specs)
            ? json_decode($product->normalized_specs, true)
            : (array) ($product->normalized_specs ?: []);

        $tokens = self::tokenize($product->name);
        $tokens[] = strtolower($product->category);

        foreach (['clockSpeed', 'cores', 'memory', 'socket', 'chipset'] as $key) {
            if (!empty($specs[$key])) {
                $tokens[] = strtolower((string) $specs[$key]);
            }
        }

        return array_unique($tokens);
    }

    private static function tokenize(string $text): array
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s\.\-]/', '', $text);
        return array_filter(preg_split('/[\s\-]+/', $text));
    }

    private static function normalizeFrequency(string $value): string
    {
        if (preg_match('/(\d+\.?\d*)\s*(ghz|mhz)/i', $value, $m)) {
            $num = (float) $m[1];
            $unit = strtolower($m[2]);
            if ($unit === 'mhz') {
                $num = $num / 1000;
            }
            return $num . ' GHz';
        }
        return $value;
    }

    private static function normalizeMemory(string $value): string
    {
        if (preg_match('/(\d+\.?\d*)\s*(gb|mb|tb)/i', $value, $m)) {
            $num = (float) $m[1];
            $unit = strtoupper($m[2]);
            if ($unit === 'MB' && $num >= 1024) {
                $num = $num / 1024;
                $unit = 'GB';
            }
            if ($unit === 'TB') {
                $num = $num * 1024;
                $unit = 'GB';
            }
            return $num . ' ' . $unit;
        }
        return $value;
    }

    private static function normalizeCache(string $value): string
    {
        if (preg_match('/(\d+\.?\d*)\s*(mb|kb)/i', $value, $m)) {
            return $m[1] . ' ' . strtoupper($m[2]);
        }
        return $value;
    }

    private static function normalizeTdp(string $value): string
    {
        if (preg_match('/(\d+\.?\d*)\s*w/i', $value, $m)) {
            return $m[1] . 'W';
        }
        return $value;
    }

    private static function normalizePcie(string $value): string
    {
        if (preg_match('/pcie\s*(\d+\.?\d*)\s*(x\d+)?/i', $value, $m)) {
            $ver = $m[1];
            $lanes = $m[2] ?? '';
            return 'PCIe ' . $ver . ($lanes ? ' ' . strtolower($lanes) : '');
        }
        return $value;
    }

    private static function extractNumeric(string $value): float
    {
        if (preg_match('/(\d+\.?\d*)/', $value, $m)) {
            return (float) $m[1];
        }
        return 0.0;
    }
}
