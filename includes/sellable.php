<?php
declare(strict_types=1);

const SOKNA_SELLABLE_MENU_ITEM='menu_item';
const SOKNA_SELLABLE_SERVICE_ITEM='service_item';

function sellable_kinds(): array
{
    return [
        SOKNA_SELLABLE_MENU_ITEM=>'آیتم منو',
        SOKNA_SELLABLE_SERVICE_ITEM=>'خدمت',
    ];
}

function normalize_sellable_kind(mixed $value): string
{
    $kind=trim((string)$value);
    if(!array_key_exists($kind,sellable_kinds()))return SOKNA_SELLABLE_MENU_ITEM;
    return $kind;
}

function sellable_kind_label(mixed $value): string
{
    $kind=normalize_sellable_kind($value);
    return sellable_kinds()[$kind];
}

function sellable_item_is_service(array $item): bool
{
    return normalize_sellable_kind($item['sellable_kind']??null)===SOKNA_SELLABLE_SERVICE_ITEM;
}
