<?php
declare(strict_types=1);

namespace app\service;

/**
 * On-prem dictionary-based parser for hardware catalog entries.
 *
 * The prompt requires "local regex rules plus a lightweight on-prem
 * dictionary-based parser" — this class is the dictionary half. It holds the
 * canonical name map (vendor synonyms, socket aliases, chipset families, form
 * factors, memory types) and a small "attribute synonyms" map so that free-form
 * spec strings can be normalized onto a canonical vocabulary before the regex
 * unit-normalization runs.
 *
 * The dictionary is intentionally hard-coded in PHP (not a JSON blob) so there
 * is no network or filesystem dependency at runtime — everything ships inside
 * the container image.
 */
class CatalogDictionary
{
    /** Vendor synonym → canonical name. Lowercased keys for case-insensitive lookup. */
    private const VENDOR_ALIASES = [
        'intel corp'      => 'Intel',
        'intel'           => 'Intel',
        'advanced micro devices' => 'AMD',
        'amd'             => 'AMD',
        'nvidia corp'     => 'NVIDIA',
        'nvidia'          => 'NVIDIA',
        'asustek'         => 'ASUS',
        'asus'            => 'ASUS',
        'gigabyte technology' => 'Gigabyte',
        'gigabyte'        => 'Gigabyte',
        'msi'             => 'MSI',
        'asrock'          => 'ASRock',
    ];

    /** Socket aliases. Lowercased, spaces/dashes collapsed. */
    private const SOCKET_ALIASES = [
        'lga1700'  => 'LGA 1700',
        'lga 1700' => 'LGA 1700',
        'socket 1700' => 'LGA 1700',
        'lga1200'  => 'LGA 1200',
        'lga 1200' => 'LGA 1200',
        'am5'      => 'AM5',
        'am4'      => 'AM4',
        'sp3'      => 'SP3',
    ];

    /** Chipset → family. */
    private const CHIPSET_FAMILIES = [
        'z790' => 'Intel 700 Series',
        'z690' => 'Intel 600 Series',
        'b760' => 'Intel 700 Series',
        'b660' => 'Intel 600 Series',
        'x670' => 'AMD 600 Series',
        'b650' => 'AMD 600 Series',
        'x570' => 'AMD 500 Series',
        'b550' => 'AMD 500 Series',
    ];

    /** Form-factor canonicalization. */
    private const FORM_FACTOR_ALIASES = [
        'atx'      => 'ATX',
        'e-atx'    => 'E-ATX',
        'eatx'     => 'E-ATX',
        'micro atx'=> 'Micro-ATX',
        'matx'     => 'Micro-ATX',
        'micro-atx'=> 'Micro-ATX',
        'mini itx' => 'Mini-ITX',
        'mitx'     => 'Mini-ITX',
        'mini-itx' => 'Mini-ITX',
    ];

    /** Memory-type canonicalization. */
    private const MEMORY_TYPE_ALIASES = [
        'ddr3'  => 'DDR3',
        'ddr4'  => 'DDR4',
        'ddr5'  => 'DDR5',
        'gddr5' => 'GDDR5',
        'gddr6' => 'GDDR6',
        'gddr6x'=> 'GDDR6X',
        'hbm2'  => 'HBM2',
    ];

    /** Free-form attribute synonyms → canonical spec key. */
    private const ATTRIBUTE_SYNONYMS = [
        'clock speed' => 'clockSpeed',
        'cpu speed'   => 'clockSpeed',
        'frequency'   => 'clockSpeed',
        'base clock'  => 'clockSpeed',
        'core count'  => 'cores',
        'num cores'   => 'cores',
        'thread count'=> 'threads',
        'num threads' => 'threads',
        'cpu socket'  => 'socket',
        'cpu cache'   => 'cache',
        'l3 cache'    => 'cache',
        'tdp watts'   => 'tdp',
        'power'       => 'tdp',
        'vram'        => 'memory',
        'video memory'=> 'memory',
    ];

    public static function canonicalVendor(string $raw): string
    {
        $k = strtolower(trim($raw));
        return self::VENDOR_ALIASES[$k] ?? $raw;
    }

    public static function canonicalSocket(string $raw): string
    {
        $k = strtolower(trim($raw));
        return self::SOCKET_ALIASES[$k] ?? $raw;
    }

    public static function chipsetFamily(string $raw): ?string
    {
        return self::CHIPSET_FAMILIES[strtolower(trim($raw))] ?? null;
    }

    public static function canonicalFormFactor(string $raw): string
    {
        $k = strtolower(trim($raw));
        return self::FORM_FACTOR_ALIASES[$k] ?? $raw;
    }

    public static function canonicalMemoryType(string $raw): string
    {
        $k = strtolower(trim($raw));
        return self::MEMORY_TYPE_ALIASES[$k] ?? $raw;
    }

    /**
     * Rename free-form attribute keys in `$specs` onto their canonical form
     * (e.g. "CPU Socket" → "socket"). Non-string values are preserved.
     * Returns a new array; the input is not mutated.
     */
    public static function canonicalizeKeys(array $specs): array
    {
        $out = [];
        foreach ($specs as $k => $v) {
            $lookup = strtolower(trim((string) $k));
            $canonical = self::ATTRIBUTE_SYNONYMS[$lookup] ?? $k;
            $out[$canonical] = $v;
        }
        return $out;
    }

    /**
     * Apply vocabulary normalization to values for known categorical fields.
     * The caller should invoke this AFTER `canonicalizeKeys` so it sees the
     * canonical spec-key names.
     */
    public static function normalizeValues(array $specs): array
    {
        if (isset($specs['vendor'])) {
            $specs['vendor'] = self::canonicalVendor((string) $specs['vendor']);
        }
        if (isset($specs['socket'])) {
            $specs['socket'] = self::canonicalSocket((string) $specs['socket']);
        }
        if (isset($specs['formFactor'])) {
            $specs['formFactor'] = self::canonicalFormFactor((string) $specs['formFactor']);
        }
        if (isset($specs['memoryType'])) {
            $specs['memoryType'] = self::canonicalMemoryType((string) $specs['memoryType']);
        }
        if (isset($specs['chipset'])) {
            $family = self::chipsetFamily((string) $specs['chipset']);
            if ($family) {
                $specs['chipsetFamily'] = $family;
            }
        }
        return $specs;
    }
}
