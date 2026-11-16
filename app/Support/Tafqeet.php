<?php

namespace App\Support;

/**
 * Arabic amount-in-words (تفقيط) for Saudi riyal / halala, as printed on invoices:
 * 22.00 → "فقط اثنان وعشرون ريالاً لا غير"
 * 15.50 → "فقط خمسة عشر ريالاً وخمسون هللة لا غير"
 */
class Tafqeet
{
    private const ONES_M = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة'];

    private const ONES_F = ['', 'واحدة', 'اثنتان', 'ثلاث', 'أربع', 'خمس', 'ست', 'سبع', 'ثماني', 'تسع', 'عشر'];

    private const TENS = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];

    private const HUNDREDS = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    public static function sar(float $amount): string
    {
        $amount = round(max($amount, 0), 2);
        $riyals = (int) floor($amount);
        $halalas = (int) round(($amount - $riyals) * 100);

        $parts = [];
        if ($riyals > 0 || $halalas === 0) {
            $parts[] = self::counted($riyals, false, 'ريال', 'ريالان', 'ريالات', 'ريالاً', 'ريال واحد');
        }
        if ($halalas > 0) {
            $parts[] = self::counted($halalas, true, 'هللة', 'هللتان', 'هللات', 'هللة', 'هللة واحدة');
        }

        return 'فقط '.implode(' و', $parts).' لا غير';
    }

    /** Number in words (0 – 999,999,999). */
    public static function words(int $n, bool $feminine = false): string
    {
        if ($n === 0) {
            return 'صفر';
        }

        $groups = [];
        $millions = intdiv($n, 1_000_000);
        $thousands = intdiv($n % 1_000_000, 1000);
        $rest = $n % 1000;

        if ($millions) {
            $groups[] = self::scale($millions, 'مليون', 'مليونان', 'ملايين', 'مليوناً');
        }
        if ($thousands) {
            $groups[] = self::scale($thousands, 'ألف', 'ألفان', 'آلاف', 'ألفاً');
        }
        if ($rest) {
            $groups[] = self::belowThousand($rest, $feminine);
        }

        return implode(' و', $groups);
    }

    /** Number followed by the counted noun with the right Arabic agreement. */
    private static function counted(int $n, bool $feminine, string $singular, string $dual, string $plural, string $accusative, string $one): string
    {
        if ($n === 0) {
            return 'صفر '.$singular;
        }
        if ($n === 1) {
            return $one;
        }
        if ($n === 2) {
            return $dual;
        }

        $words = self::words($n, $feminine);
        $lastTwo = $n % 100;

        if ($lastTwo === 0) {
            // Construct state before the noun: مائتا ريال، ألفا ريال
            $words = preg_replace(
                ['/مائتان$/u', '/ألفان$/u', '/مليونان$/u', '/ألفاً$/u', '/مليوناً$/u'],
                ['مائتا', 'ألفا', 'مليونا', 'ألف', 'مليون'],
                $words,
            );
        }

        $noun = match (true) {
            $lastTwo >= 3 && $lastTwo <= 10 => $plural,
            $lastTwo >= 11 => $accusative,
            default => $singular,
        };

        return $words.' '.$noun;
    }

    private static function scale(int $count, string $singular, string $dual, string $plural, string $accusative): string
    {
        if ($count === 1) {
            return $singular;
        }
        if ($count === 2) {
            return $dual;
        }
        $lastTwo = $count % 100;
        $noun = match (true) {
            $lastTwo >= 3 && $lastTwo <= 10 => $plural,
            $lastTwo >= 11 => $accusative,
            default => $singular,
        };

        return self::belowThousand($count, false).' '.$noun;
    }

    private static function belowThousand(int $n, bool $feminine): string
    {
        $parts = [];
        $hundreds = intdiv($n, 100);
        $rest = $n % 100;

        if ($hundreds) {
            $parts[] = self::HUNDREDS[$hundreds];
        }
        if ($rest) {
            $parts[] = self::belowHundred($rest, $feminine);
        }

        return implode(' و', $parts);
    }

    private static function belowHundred(int $n, bool $feminine): string
    {
        $ones = $feminine ? self::ONES_F : self::ONES_M;

        if ($n <= 10) {
            return $ones[$n];
        }
        if ($n === 11) {
            return $feminine ? 'إحدى عشرة' : 'أحد عشر';
        }
        if ($n === 12) {
            return $feminine ? 'اثنتا عشرة' : 'اثنا عشر';
        }
        if ($n < 20) {
            return $ones[$n - 10].($feminine ? ' عشرة' : ' عشر');
        }

        $unit = $n % 10;
        $tens = self::TENS[intdiv($n, 10)];
        if (! $unit) {
            return $tens;
        }
        $unitWord = ($unit === 1 && $feminine) ? 'إحدى' : $ones[$unit];

        return $unitWord.' و'.$tens;
    }
}
