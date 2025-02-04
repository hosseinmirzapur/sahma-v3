<?php

namespace App\Helper;

use Carbon\Carbon;
use Exception;

class TimeHelper
{
    /**
     * @param Carbon|null $datetime
     * @return Carbon
     */
    public static function getNext3DivisibleCarbonDateTime(?Carbon $datetime = null): Carbon
    {
        if ($datetime == null) {
            $datetime = now();
        }
        $addValue = ($datetime->hour % 3) * -1 + 3;
        $datetime->addHours($addValue);
        $datetime->setMinute(0);
        $datetime->setSecond(0);
        $datetime->setMicrosecond(0);
        return $datetime;
    }

    public static function getLastNMinDivisibleCarbonDateTime(int $n, ?Carbon $date = null, int $last = 1): Carbon
    {
        if ($date == null) {
            $date = now();
        }
        $seconds = $n * 60;
        $offsetSeconds = $date->offsetMinutes * 60;
        return Carbon::createFromTimestamp(
            (intdiv(intval($date->timestamp) + $offsetSeconds, $seconds) - ($last - 1)) * $seconds - $offsetSeconds
        );
    }

    /**
     * @param int $last
     * @return float|int
     */
    public static function getLast15MinCarbonDateTime(int $last = 1): float|int
    {
        return ((int)(intval(now()->timestamp) / 900 - ($last - 1)) * 900);
    }

    public static function getCarbonDiffInPersianHumanReadableFormat(Carbon $start, Carbon $end = null): string
    {
        if ($end == null) {
            $end = now();
        }
        $interval = $start->diff($end);

        $years = $interval->y;
        $months = $interval->m;
        $days = $interval->d;
        $hours = $interval->h;
        $minutes = $interval->i;
        $seconds = $interval->s;

        $diff = null;

        if ($years != 0) {
            $diff = $years . ' سال';
            if ($months != 0) {
                $diff = $diff . ' و ' . $months . ' ماه ';
            }
        } elseif ($months != 0) {
            $diff = $months . ' ماه';
            if ($days != 0) {
                $diff = $diff . ' و ' . $days . ' روز ';
            }
        } elseif ($days != 0) {
            $diff = $days . ' روز';
            if ($hours != 0) {
                $diff = $diff . ' و ' . $hours . ' ساعت ';
            }
        } elseif ($hours != 0) {
            $diff = $hours . ' ساعت';
            if ($minutes != 0) {
                $diff = $diff . ' و ' . $hours . ' دقیقه ';
            }
        } elseif ($minutes != 0) {
            $diff = $minutes . ' دقیقه';
            if ($seconds != 0) {
                $diff = $diff . ' و ' . $seconds . ' ثانیه ';
            }
        } elseif ($seconds != 0) {
            $diff = $seconds . ' ثانیه';
        }

        return strval(english_digit_to_persian($diff));
    }
}
