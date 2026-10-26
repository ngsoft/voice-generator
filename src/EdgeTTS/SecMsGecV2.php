<?php

declare(strict_types=1);

namespace EdgeTTS;

/**
 * Sec-MS-GEC v2 token (Microsoft Edge Read Aloud, Oct 2026+).
 *
 * Port of MIT-licensed edge-tts 7.3.1 drm.py (rany2/edge-tts).
 * Format: "<16 hex CS64>-<64 hex adjusted SHA-256>".
 *
 * @see https://github.com/rany2/edge-tts/blob/7.3.1/src/edge_tts/drm.py
 */
final class SecMsGecV2
{
    private const TRUSTED_CLIENT_TOKEN     = '6A5AA1D4EAFF4E9FB37E23D68491D6F4';

    private const WIN_EPOCH                = 11644473600;

    private const U32                      = 0xFFFFFFFF;

    /** @var list<int> */
    private const SHA256_INITIAL           = [
        0x6A09E667, 0xBB67AE85, 0x3C6EF372, 0xA54FF53A,
        0x510E527F, 0x9B05688C, 0x1F83D9AB, 0x5BE0CD19,
    ];

    /** @var list<int> */
    private const SHA256_STATE             = [
        0xFD9FBEA2, 0x91243CE7, 0xBE499094, 0xCD2E1E0A,
        0x6C006F49, 0x04BD35AB, 0xFEB0A7E4, 0x03EFFBD2,
    ];

    /** @var list<int> */
    private const SHA256_SCHEDULE          = [
        0xEB24BF9E, 0x1558691F, 0xA0C09EE2, 0x0CFA32BA,
        0xA622EE8C, 0xD1DD9491, 0xC0DD25A0, 0xF9E70AB6,
        0xF0C31573, 0x7058CE4E, 0x7188E606, 0x00E5AB33,
        0xD258B060, 0x72766564,
    ];

    /** @var list<int> */
    private const SHA256_ROUND_CONSTANTS   = [
        0x428A2F98, 0x71374491, 0xB5C0FBCF, 0xE9B5DBA5,
        0x3956C25B, 0x59F111F1, 0x923F82A4, 0xAB1C5ED5,
        0xD807AA98, 0x12835B01, 0x243185BE, 0x550C7DC3,
        0x72BE5D74, 0x80DEB1FE, 0x9BDC06A7, 0xC19BF174,
        0xE49B69C1, 0xEFBE4786, 0x0FC19DC6, 0x240CA1CC,
        0x2DE92C6F, 0x4A7484AA, 0x5CB0A9DC, 0x76F988DA,
        0x983E5152, 0xA831C66D, 0xB00327C8, 0xBF597FC7,
        0xC6E00BF3, 0xD5A79147, 0x06CA6351, 0x14292967,
        0x27B70A85, 0x2E1B2138, 0x4D2C6DFC, 0x53380D13,
        0x650A7354, 0x766A0ABB, 0x81C2C92E, 0x92722C85,
        0xA2BFE8A1, 0xA81A664B, 0xC24B8B70, 0xC76C51A3,
        0xD192E819, 0xD6990624, 0xF40E3585, 0x106AA070,
        0x19A4C116, 0x1E376C08, 0x2748774C, 0x34B0BCB5,
        0x391C0CB3, 0x4ED8AA4A, 0x5B9CCA4F, 0x682E6FF3,
        0x748F82EE, 0x78A5636F, 0x84C87814, 0x8CC70208,
        0x90BEFFFA, 0xA4506CEB, 0xBEF9A3F7, 0xC67178F2,
    ];

    private const SHA256_ADJUSTMENT        = 0xC9DC06A7;
    private const SHA256_SIGMA0_ADJUSTMENT = 0x059C0B80;
    private const SHA256_SIGMA1_ADJUSTMENT = 0x000B92C0;

    /** @var list<array{0: int, 1: int}> */
    private const CS64_COEFFICIENTS        = [
        [0xE82E91B1, 0xB32700D0],
        [0x44DE24C6, 0x88DFF51D],
        [0x3BEB65BC, 0x29514038],
        [0xC89C5933, 0xB328CBDF],
        [0x8D1EC211, 0xD405B4D5],
        [0x314EF5D5, 0x26E55F3C],
        [0xE92491B1, 0x607801C6],
        [0xF0A5B9D5, 0x82B2C1FB],
        [0x06064CF3, 0xFA51A3DA],
        [0xDA79FA60, 0x207CC464],
        [0xBED20560, 0x3C3C74F3],
        [0x89F9C9F8, 0xD2D14B61],
        [0x35FA38C6, 0xBB04A2C1],
        [0xCB49E3C6, 0xC5626167],
    ];

    private const CS64_FACTOR              = 0x82893933;
    private const CS64_ADJUSTMENT0         = 0xF2F90B1A;
    private const CS64_ADJUSTMENT1         = 0x0B1A0000;

    /** @var null|array{string, string} */
    private static ?array $hashCache       = null;

    private static ?string $hashCacheKey   = null;

    /**
     * @param null|int $unixTimestamp seconds; null uses current time
     */
    public static function generate(?int $unixTimestamp = null): string
    {
        $ticks           = ($unixTimestamp ?? time()) + self::WIN_EPOCH;
        $ticks -= $ticks % 300;
        // Same salt as v1 / Python f"{ticks*1e7:.0f}{TOKEN}" without float rounding.
        $data            = $ticks . '0000000' . self::TRUSTED_CLIENT_TOKEN;
        [$cs64, $sha256] = self::tokenHash($data);

        return $cs64 . '-' . $sha256;
    }

    /**
     * @return array{0: string, 1: string} CS64 hex (16), SHA-256 hex (64)
     */
    public static function tokenHash(string $data): array
    {
        if (self::$hashCacheKey === $data && null !== self::$hashCache)
        {
            return self::$hashCache;
        }

        $digest             = self::sha256Digest($data);
        $cs64               = self::cs64Digest($data, $digest);
        $out                = [
            strtoupper(bin2hex(pack('V2', $cs64[0], $cs64[1]))),
            strtoupper(bin2hex($digest)),
        ];

        self::$hashCacheKey = $data;
        self::$hashCache    = $out;

        return $out;
    }

    private static function u32(int $value): int
    {
        return $value & self::U32;
    }

    /** 32×32 → low 32 bits (avoids PHP signed-int / float overflow). */
    private static function mul32(int $a, int $b): int
    {
        $a  = self::u32($a);
        $b  = self::u32($b);
        $a0 = $a & 0xFFFF;
        $a1 = $a >> 16;
        $b0 = $b & 0xFFFF;
        $b1 = $b >> 16;

        return self::u32(($a0 * $b0) + ((($a1 * $b0 + $a0 * $b1) & 0xFFFF) << 16));
    }

    private static function rol16(int $value): int
    {
        $value = self::u32($value);

        return self::u32(($value << 16) | ($value >> 16));
    }

    private static function rotr(int $value, int $n): int
    {
        $value = self::u32($value);

        return self::u32(($value >> $n) | ($value << (32 - $n)));
    }

    private static function sigma0(int $value): int
    {
        return self::u32(($value >> 7) ^ ($value << 25) ^ ($value >> 18) ^ ($value << 14) ^ ($value >> 3));
    }

    private static function sigma1(int $value): int
    {
        return self::u32(($value >> 17) ^ ($value << 15) ^ ($value >> 19) ^ ($value << 13) ^ ($value >> 10));
    }

    /**
     * Standard SHA-256 rounds (bitwise-identical to drm.py's role-rotated form).
     *
     * @param list<int> $state
     * @param list<int> $words
     *
     * @return list<int>
     */
    private static function sha256Rounds(array $state, array $words, int $firstRound = 0): array
    {
        [$a, $b, $c, $d, $e, $f, $g, $h] = $state;

        $constants                       = self::SHA256_ROUND_CONSTANTS;

        if (14 === $firstRound)
        {
            $constants[14] = self::SHA256_ADJUSTMENT;
        }

        for ($index = $firstRound; $index < 64; ++$index)
        {
            $s1  = self::u32(self::rotr($e, 6) ^ self::rotr($e, 11) ^ self::rotr($e, 25));
            $ch  = self::u32($g ^ ($e & ($f ^ $g)));
            $t1  = self::u32($h + $s1 + $ch + $constants[$index] + $words[$index]);
            $s0  = self::u32(self::rotr($a, 2) ^ self::rotr($a, 13) ^ self::rotr($a, 22));
            $maj = self::u32(($a & $b) | ($c & ($a | $b)));
            $t2  = self::u32($s0 + $maj);
            $h   = $g;
            $g   = $f;
            $f   = $e;
            $e   = self::u32($d + $t1);
            $d   = $c;
            $c   = $b;
            $b   = $a;
            $a   = self::u32($t1 + $t2);
        }

        return [$a, $b, $c, $d, $e, $f, $g, $h];
    }

    /**
     * @param list<int> $words
     */
    private static function expandSha256Schedule(array &$words, int $firstWord = 16): void
    {
        for ($index = $firstWord; $index < 64; ++$index)
        {
            $x       = $words[$index - 2];
            $y       = $words[$index - 15];
            $sigma1  = ($x >> 17) ^ ($x << 15) ^ ($x >> 19) ^ ($x << 13) ^ ($x >> 10);
            $sigma0  = ($y >> 7)  ^ ($y << 25) ^ ($y >> 18) ^ ($y << 14) ^ ($y >> 3);
            $words[] = self::u32($sigma1 + $words[$index - 7] + $sigma0 + $words[$index - 16]);
        }
    }

    /**
     * @return list<int>
     */
    private static function sha256Init(string $data): array
    {
        if (strlen($data) < 7)
        {
            throw new \InvalidArgumentException('Token salt must contain at least seven bytes');
        }

        $words   = array_fill(0, 14, 0);
        $words[] = unpack('N', "\0" . substr($data, 0, 3))[1];
        $words[] = unpack('N', substr($data, 3, 4))[1];

        for ($index = 16; $index < 30; ++$index)
        {
            $value   = self::SHA256_SCHEDULE[$index - 16];

            if ($index - 2 >= 14)
            {
                $s1 = self::sigma1($words[$index - 2]);
                $value += (16 === $index) ? ($s1 ^ self::SHA256_SIGMA1_ADJUSTMENT) : $s1;
            }

            if ($index - 7 >= 14)
            {
                $value += $words[$index - 7];
            }

            if ($index - 15 >= 14)
            {
                $s0 = self::sigma0($words[$index - 15]);
                $value += (29 === $index) ? ($s0 ^ self::SHA256_SIGMA0_ADJUSTMENT) : $s0;
            }
            $words[] = self::u32($value);
        }

        $words[] = self::u32(
            self::sigma1($words[28])
            + $words[23]
            + self::sigma0($words[15])
            + $words[14]
            + self::SHA256_ADJUSTMENT
            - self::SHA256_ROUND_CONSTANTS[14]
        );

        self::expandSha256Schedule($words, 31);
        $working = self::sha256Rounds(self::SHA256_STATE, $words, 14);

        $out     = [];

        foreach (self::SHA256_INITIAL as $i => $x)
        {
            $out[] = self::u32($x + $working[$i]);
        }

        return $out;
    }

    private static function sha256Digest(string $data): string
    {
        if (strlen($data) < 7)
        {
            throw new \InvalidArgumentException('Token salt must contain at least seven bytes');
        }

        $length = 57 + strlen($data);
        $padded = substr($data, 7) . "\x80";
        $padLen = (55 - $length) % 64;

        if ($padLen < 0)
        {
            $padLen += 64;
        }
        $padded .= str_repeat("\0", $padLen);
        $padded .= pack('J', $length * 8);

        // pack('J') is big-endian 64-bit on PHP; Python uses >Q. Good.
        // But on Windows 32-bit... we're on 64-bit PHP 8.2+.

        $state  = self::sha256Init($data);

        for ($offset = 0, $len = strlen($padded); $offset < $len; $offset += 64)
        {
            $block   = substr($padded, $offset, 64);
            $words   = array_values(unpack('N16', $block));
            self::expandSha256Schedule($words);
            $working = self::sha256Rounds($state, $words);

            foreach ($state as $i => $x)
            {
                $state[$i] = self::u32($x + $working[$i]);
            }
        }

        return pack('N8', ...$state);
    }

    /**
     * @return list<int>
     */
    private static function wordsLe4Even(string $data): array
    {
        $pad    = (4 - (strlen($data) % 4)) % 4;
        $padded = $data . str_repeat("\0", $pad);

        if ('' === $padded)
        {
            return [];
        }
        $words  = array_values(unpack('V*', $padded));

        if (1 === count($words) % 2)
        {
            $words[] = 0;
        }

        return $words;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function cs64Digest(string $data, string $digest): array
    {
        [$keyA, $keyB] = array_values(unpack('V2', substr($digest, 0, 8)));
        $keyA |= 1;
        $keyB |= 1;
        $factors       = [
            self::mul32($keyA, self::CS64_FACTOR),
            self::mul32($keyB, self::CS64_FACTOR),
        ];

        $terms         = [];
        $offsets       = [];

        foreach (self::CS64_COEFFICIENTS as $i => [$coefficient, $offset])
        {
            $terms[]   = self::mul32($coefficient, $factors[$i % 2]);
            $offsets[] = $offset;
        }

        foreach (self::wordsLe4Even("\0" . $data) as $index => $word)
        {
            $term      = self::mul32($word, (0 === $index % 2) ? $keyA : $keyB);
            $offset    = self::mul32(self::rol16($word), (0 === $index % 2) ? 0xEF0569FB : 0xC31713DB);

            if (0 === $index)
            {
                $term   = self::u32($term + self::mul32(self::CS64_ADJUSTMENT0, $factors[0]));
                $offset = self::u32($offset + self::CS64_ADJUSTMENT1);
            }
            $terms[]   = self::u32($term);
            $offsets[] = self::u32($offset);
        }

        $swapAcc       = $swapLast = $state = $acc = 0;
        $n             = count($terms);

        for ($index = 0; $index < $n; $index += 2)
        {
            $x        = $terms[$index];
            $y        = $terms[$index + 1];

            $t        = self::rol16(self::u32($offsets[$index] + $x));
            $q        = self::mul32($t, 0x76F50F34);
            $swapAcc  = self::u32($swapAcc + self::u32(self::mul32(self::rol16($q), 0xC3EFEA97) + $q));

            $t        = self::rol16(self::u32($offsets[$index + 1] + $y));
            $q        = self::mul32($t, 0x1881CE3C);
            $swapLast = self::u32(self::mul32(self::rol16($q), 0x35BD1EC9) + $q);
            $swapAcc  = self::u32($swapAcc + $swapLast);

            $state    = self::u32(self::mul32($state, $keyA) + $x);
            $state    = self::mul32(self::rol16($state), 0xCF98B111);
            $state    = self::mul32(self::rol16($state), 0x87085B9F);
            $state    = self::mul32(self::rol16($state), 0x12CEB96D);
            $state    = self::mul32(self::rol16($state), 0x257E1D83);
            $acc      = self::u32($acc + $state);

            $t        = self::u32(self::mul32($state, $keyB) + $y);
            $t        = self::mul32(self::rol16($t), 0xA27416F5);
            $t        = self::mul32(self::rol16($t), 0xD38396FF);
            $state    = self::mul32(self::rol16(self::mul32(self::rol16($t), 0x7C932B89)), 0xBFA49F69);
            $acc      = self::u32($acc + $state);
        }

        return [$swapLast ^ $state, $swapAcc ^ $acc];
    }
}
