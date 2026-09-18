<?php

declare(strict_types=1);

// Extracted from includes/functions.php during pre-operational P2 owner cleanup.
// Keep behavior-compatible global function names; functions.php remains the public bootstrap aggregator.

function app_timezone(): string
{
    $timezone = trim(setting('app_timezone', 'Asia/Tehran'));
    try {
        new DateTimeZone($timezone);
        return $timezone;
    } catch (Throwable) {
        return 'Asia/Tehran';
    }
}

/** Convert a Gregorian date to Solar Hijri (Jalali). */
function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $gdm = [0,31,59,90,120,151,181,212,243,273,304,334];
    $gy2 = $gm > 2 ? $gy + 1 : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
        + intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function jalali_to_gregorian(int $jy, int $jm, int $jd): array
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd;
    $days += $jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186;
    $gy = 400 * intdiv($days, 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * intdiv(--$days, 36524);
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $leap = (($gy % 4 === 0) && ($gy % 100 !== 0)) || ($gy % 400 === 0);
    $months = [0,31,$leap ? 29 : 28,31,30,31,30,31,31,30,31,30,31];
    $gm = 1;
    while ($gm <= 12 && $gd > $months[$gm]) { $gd -= $months[$gm]; $gm++; }
    return [$gy, $gm, $gd];
}

function parse_jalali_date(string $value): ?string
{
    $value = trim(str_replace(['-', '.'], '/', en_digits($value)));
    if (!preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $value, $match)) return null;
    $jy = (int)$match[1]; $jm = (int)$match[2]; $jd = (int)$match[3];
    if ($jy < 1200 || $jy > 1700 || $jm < 1 || $jm > 12 || $jd < 1 || $jd > ($jm <= 6 ? 31 : 30)) return null;
    [$gy,$gm,$gd] = jalali_to_gregorian($jy,$jm,$jd);
    if (!checkdate($gm,$gd,$gy)) return null;
    if (gregorian_to_jalali($gy,$gm,$gd) !== [$jy,$jm,$jd]) return null;
    return sprintf('%04d-%02d-%02d',$gy,$gm,$gd);
}

function jalali_date_input(DateTimeInterface|string|null $value = null): string
{
    [$jy,$jm,$jd] = jalali_date_parts($value);
    return fa_digits(sprintf('%04d/%02d/%02d',$jy,$jm,$jd));
}


function parse_optional_jalali_day_boundary(string $dateJ, bool $endOfDay = false, string $label = 'تاریخ'): ?string
{
    $dateJ = trim($dateJ);
    if ($dateJ === '') return null;
    $gregorian = parse_jalali_date($dateJ);
    if (!$gregorian) throw new RuntimeException('تاریخ شمسی ' . $label . ' معتبر نیست.');
    $time = $endOfDay ? '23:59:59' : '00:00:00';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $gregorian . ' ' . $time, new DateTimeZone(app_timezone()));
    if (!$date) throw new RuntimeException($label . ' معتبر نیست.');
    return $date->format('Y-m-d H:i:s');
}

function parse_optional_jalali_datetime(string $dateJ, string $time, string $label = 'زمان'): ?string
{
    $dateJ = trim($dateJ);
    $time = trim(en_digits($time));
    if ($dateJ === '' && $time === '') return null;
    if ($dateJ === '' || $time === '' || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
        throw new RuntimeException('تاریخ و ساعت ' . $label . ' را کامل و معتبر وارد کن.');
    }
    $gregorian = parse_jalali_date($dateJ);
    if (!$gregorian) throw new RuntimeException('تاریخ شمسی ' . $label . ' معتبر نیست.');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $gregorian . ' ' . $time, new DateTimeZone(app_timezone()));
    if (!$date) throw new RuntimeException('زمان ' . $label . ' معتبر نیست.');
    return $date->format('Y-m-d H:i:s');
}

function jalali_date_parts(DateTimeInterface|string|null $value = null): array
{
    if ($value instanceof DateTimeInterface) {
        $date = DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone(app_timezone()));
    } else {
        $date = new DateTimeImmutable($value ?: 'now', new DateTimeZone(app_timezone()));
    }
    [$jy, $jm, $jd] = gregorian_to_jalali((int)$date->format('Y'), (int)$date->format('n'), (int)$date->format('j'));
    return [$jy, $jm, $jd, $date];
}

function format_jalali_date(DateTimeInterface|string|null $value = null, bool $withWeekday = true): string
{
    [$jy, $jm, $jd, $date] = jalali_date_parts($value);
    $weekdays = ['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'];
    $months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    $prefix = $withWeekday ? $weekdays[(int)$date->format('w')] . '، ' : '';
    return $prefix . fa_digits($jd) . ' ' . $months[$jm - 1] . ' ' . fa_digits($jy);
}

function format_jalali_datetime(DateTimeInterface|string|null $value = null, bool $withWeekday = true): string
{
    [$jy, $jm, $jd, $date] = jalali_date_parts($value);
    $weekdays = ['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'];
    $months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    $prefix = $withWeekday ? $weekdays[(int)$date->format('w')] . '، ' : '';
    return $prefix . fa_digits($jd) . ' ' . $months[$jm - 1] . ' ' . fa_digits($jy) . ' — ' . fa_digits($date->format('H:i'));
}

function format_jalali_compact(DateTimeInterface|string|null $value = null): string
{
    [$jy, $jm, $jd, $date] = jalali_date_parts($value);
    return fa_digits(sprintf('%04d/%02d/%02d', $jy, $jm, $jd)) . ' — ' . fa_digits($date->format('H:i'));
}

/** Human-first compact timestamp for operational lists; year is shown only when it differs from the current Jalali year. */
function format_jalali_human_datetime(DateTimeInterface|string|null $value = null): string
{
    [$jy, $jm, $jd, $date] = jalali_date_parts($value);
    [$currentJy] = jalali_date_parts('now');
    $months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    $label = fa_digits($jd) . ' ' . $months[$jm - 1];
    if ($jy !== $currentJy) $label .= ' ' . fa_digits($jy);
    return $label . ' · ' . fa_digits($date->format('H:i'));
}

