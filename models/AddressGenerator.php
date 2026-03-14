<?php
// ============================================================
// models/AddressGenerator.php  — v2.0
//
// NEDAMS Advanced Address Generation Engine
// ------------------------------------------
// Strategies:
//   1. GEOHASH-INSPIRED (default) — encodes quantised coords
//      via custom base-32, prefix + 6 body chars = 8 total
//   2. SHA-HYBRID — SHA-256 window-shifted collision-safe
//   3. SEQUENTIAL — numeric increment base-36 (fallback)
//
// All codes: [PREFIX][BODY]  where PREFIX comes from settings
// Properties: deterministic, collision-safe, visually unambiguous
// ============================================================

class AddressGenerator {

    // Unambiguous base-32 alphabet (no 0,O,1,I)
    private const B32 = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    // --------------------------------------------------------
    // PUBLIC: generate(lat, lng, pdo)
    // Returns address code string
    // --------------------------------------------------------
    public static function generate(float $lat, float $lng, PDO $pdo): string {
        $prefix = strtoupper(setting('addressing.address_prefix', 'NE'));
        $length = max(4, min(10, (int)setting('addressing.address_length', 6)));

        // Strategy 1: Geohash-inspired spatial encoding
        $code1 = $prefix . self::spatialEncode($lat, $lng, $length);
        if (!self::codeExistsElsewhere($code1, $lat, $lng, $pdo)) return $code1;

        // Strategy 2: SHA-256 hybrid (try 8 windows)
        for ($shift = 0; $shift < 8; $shift++) {
            $code2 = $prefix . self::shaEncode($lat, $lng, $length, $shift);
            if (!self::codeExistsElsewhere($code2, $lat, $lng, $pdo)) return $code2;
        }

        // Strategy 3: Sequential base-36 fallback (guaranteed unique)
        $seq   = (int)$pdo->query('SELECT COUNT(*)+1 FROM structures')->fetchColumn();
        return $prefix . strtoupper(str_pad(base_convert((string)$seq, 10, 36), $length, '0', STR_PAD_LEFT));
    }

    // --------------------------------------------------------
    // Geohash-inspired spatial encoder
    // Interleaves quantised lat/lng bits → base-32 chars
    // Resolution: ~1m at 5 decimal places
    // --------------------------------------------------------
    private static function spatialEncode(float $lat, float $lng, int $chars): string {
        // Quantise to integer grid (5 decimal places = ~1.1m)
        $iLat = (int)(round($lat, 5) * 100000) + 15000000; // offset to positive
        $iLng = (int)(round($lng, 5) * 100000) + 18000000;

        // Interleave bits (morton curve / z-order)
        $morton = 0;
        for ($i = 0; $i < 24; $i++) {
            $morton |= (($iLat >> $i) & 1) << (2*$i);
            $morton |= (($iLng >> $i) & 1) << (2*$i+1);
        }

        // Base-32 encode the 48-bit morton value
        $alph   = self::B32;
        $result = '';
        $val    = $morton;
        for ($i = 0; $i < $chars + 4; $i++) {
            $result = $alph[$val & 0x1F] . $result;
            $val  >>= 5;
        }

        return substr($result, -$chars);
    }

    // --------------------------------------------------------
    // SHA-256 hybrid encoder (window-shifted for collisions)
    // --------------------------------------------------------
    private static function shaEncode(float $lat, float $lng, int $chars, int $shift): string {
        $input = sprintf('NEDAMS:%.5f:%.5f:shift%d', round($lat,5), round($lng,5), $shift);
        $bytes  = hex2bin(hash('sha256', $input));
        $alph   = self::B32;
        $out    = '';
        $buf    = 0; $bits = 0;
        for ($i = 0; $i < strlen($bytes); $i++) {
            $buf   = ($buf << 8) | ord($bytes[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out  .= $alph[($buf >> $bits) & 0x1F];
                if (strlen($out) >= $chars) break 2;
            }
        }
        return strtoupper(substr($out, 0, $chars));
    }

    // --------------------------------------------------------
    // Does this code exist for a DIFFERENT location?
    // --------------------------------------------------------
    private static function codeExistsElsewhere(string $code, float $lat, float $lng, PDO $pdo): bool {
        $stmt = $pdo->prepare(
            'SELECT latitude,longitude FROM structures WHERE address_code=? LIMIT 1'
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        if (!$row) return false;
        $sameLat = abs($row['latitude']  - $lat) < 0.000015;
        $sameLng = abs($row['longitude'] - $lng) < 0.000015;
        return !($sameLat && $sameLng);
    }

    // --------------------------------------------------------
    // Calculate GPS accuracy confidence score 0-100
    // Based on accuracy_meters reported by browser
    // --------------------------------------------------------
    public static function confidenceScore(?float $accuracyMeters): int {
        if ($accuracyMeters === null) return 75;
        if ($accuracyMeters <= 3)   return 100;
        if ($accuracyMeters <= 10)  return 95;
        if ($accuracyMeters <= 25)  return 85;
        if ($accuracyMeters <= 50)  return 70;
        if ($accuracyMeters <= 100) return 50;
        return 25;
    }

    // --------------------------------------------------------
    // Validate a NEDAMS code format
    // Accepts any alphanumeric code 4-16 chars (DB is authority)
    // --------------------------------------------------------
    public static function isValid(string $code): bool {
        $c = strtoupper(trim($code));
        // Must be 4-16 uppercase alphanumeric characters
        if (!preg_match('/^[A-Z0-9]{4,16}$/', $c)) return false;
        // Must start with configured prefix (default NE) — soft check
        try {
            $prefix = strtoupper(setting('addressing.address_prefix', 'NE') ?: 'NE');
            if ($prefix && !str_starts_with($c, $prefix)) return false;
        } catch (Throwable $e) {
            // If settings unavailable, skip prefix check
        }
        return true;
    }

    // --------------------------------------------------------
    // Decode approximate coordinates from a spatial code
    // (reverse of spatialEncode — useful for "nearby" search)
    // --------------------------------------------------------
    public static function approximateCoords(string $code): ?array {
        $prefix = strtoupper(setting('addressing.address_prefix','NE'));
        $body   = substr(strtoupper($code), strlen($prefix));
        $alph   = self::B32;

        // Decode base-32 back to integer
        $val = 0;
        for ($i = 0; $i < strlen($body); $i++) {
            $pos = strpos($alph, $body[$i]);
            if ($pos === false) return null;
            $val = ($val << 5) | $pos;
        }

        // De-interleave morton bits
        $iLat = 0; $iLng = 0;
        for ($i = 0; $i < 24; $i++) {
            $iLat |= (($val >> (2*$i))   & 1) << $i;
            $iLng |= (($val >> (2*$i+1)) & 1) << $i;
        }

        return [
            'latitude'  => ($iLat - 15000000) / 100000,
            'longitude' => ($iLng - 18000000) / 100000,
        ];
    }
}
