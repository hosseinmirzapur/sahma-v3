<?php

if (!function_exists('english_digit_to_persian')) {
    function english_digit_to_persian(mixed $string): array|string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = range(0, 9);

        return str_replace($english, $persian, is_array($string) ? $string : strval($string));
    }
}

if (!function_exists('timestamp_to_persian_datetime')) {
    /**
     * @throws Exception
     */
    function timestamp_to_persian_datetime(
        array|DateTimeInterface|float|int|IntlCalendar|string|null $timestamp,
        bool $includeTime = true
    ): bool|string {
        if (is_null($timestamp)) {
            return '';
        }
        return (new IntlDateFormatter(
            'fa_IR@calendar=persian',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            null,
            IntlDateFormatter::TRADITIONAL,
            ($includeTime) ? 'yyyy/MM/dd HH:mm' : 'yyyy/MM/dd'
        ))->format($timestamp);
    }
}
