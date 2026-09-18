<?php

declare(strict_types=1);

// Extracted from includes/functions.php during pre-operational P2 owner cleanup.
// Keep behavior-compatible global function names; functions.php remains the public bootstrap aggregator.

function event_display_fee_amount(array $event): ?int
{
    if (array_key_exists('fee_amount', $event) && $event['fee_amount'] !== null && $event['fee_amount'] !== '') {
        $parsedFee = parse_toman_amount_text($event['fee_amount']);
        if ($parsedFee !== null) return $parsedFee;
    }
    return parse_toman_amount_text($event['admission_text'] ?? null);
}

function event_display_admission_text(array $event): string
{
    $text = trim((string)($event['admission_text'] ?? ''));
    if ($text === '') return '';
    return parse_toman_amount_text($text) !== null ? '' : $text;
}

function event_registration_label(string $type): string
{
    return [
        'none' => 'بدون ثبت‌نام',
        'phone' => 'هماهنگی تلفنی',
        'link' => 'ثبت‌نام از طریق لینک',
        'whatsapp' => 'ثبت‌نام از طریق واتس‌اپ',
        'in_person' => 'هماهنگی حضوری',
    ][$type] ?? 'بدون ثبت‌نام';
}

function event_effective_end(array $event): DateTimeImmutable
{
    $timezone = new DateTimeZone(app_timezone());
    if (!empty($event['ends_at'])) return new DateTimeImmutable((string)$event['ends_at'], $timezone);
    return (new DateTimeImmutable((string)$event['starts_at'], $timezone))->modify('+6 hours');
}

function event_lifecycle_status(array $event, ?DateTimeInterface $now = null): string
{
    $timezone = new DateTimeZone(app_timezone());
    $clock = $now ? DateTimeImmutable::createFromInterface($now)->setTimezone($timezone) : new DateTimeImmutable('now', $timezone);
    if (!empty($event['cancelled_at'])) return 'cancelled';
    $start = new DateTimeImmutable((string)$event['starts_at'], $timezone);
    $end = event_effective_end($event);
    if ($end < $clock) return 'past';
    if ((int)($event['active'] ?? 0) !== 1) return 'draft';
    if ($start <= $clock) return 'live';
    return 'upcoming';
}

function event_lifecycle_label(string $status): string
{
    return [
        'draft' => 'پیش‌نویس',
        'upcoming' => 'پیش‌رو',
        'live' => 'در حال برگزاری',
        'past' => 'پایان‌یافته',
        'cancelled' => 'لغوشده',
    ][$status] ?? $status;
}
