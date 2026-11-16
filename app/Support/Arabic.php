<?php

namespace App\Support;

use DateTimeInterface;
use IntlDateFormatter;

class Arabic
{
    /** Arabic-Indic (٠-٩) and Persian (۰-۹) digits plus Arabic separators → ASCII. */
    public static function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٫' => '.', '٬' => '',
        ]);
    }

    /**
     * Normalise text for searching: hamza/alef forms, taa marbuta, alef maqsura,
     * diacritics (tashkeel) and tatweel are folded so "احمد" matches "أَحمد".
     */
    public static function normalizeSearch(?string $value): string
    {
        $value = self::normalizeDigits((string) $value);
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $value) ?? $value;
        $value = strtr($value, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
        ]);

        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($value)) ?? $value);
    }

    /** Hijri (Umm al-Qura) date, e.g. "6 جمادى الآخرة 1448 هـ" / "6 Jumada II 1448 AH". */
    public static function hijri(DateTimeInterface $date, ?string $locale = null, bool $withWeekday = false): string
    {
        $locale ??= app()->getLocale();
        $isArabic = $locale === 'ar';

        $formatter = new IntlDateFormatter(
            ($isArabic ? 'ar_SA' : 'en').'@calendar=islamic-umalqura;numbers=latn',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            config('app.timezone'),
            IntlDateFormatter::TRADITIONAL,
            ($withWeekday ? 'EEEE ' : '').'d MMMM y',
        );

        return $formatter->format($date).($isArabic ? ' هـ' : ' AH');
    }
}
