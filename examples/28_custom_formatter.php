<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\CustomFormatter;

// PHP attributes require constant expressions as arguments.
// Arrow functions (fn() =>) are NOT valid, but static function() {} IS.
// First-class callables (ClassName::method(...)) also work.

final class Money
{
    public function __construct(
        #[CustomFormatter(
            hydrate: static function (string $value): float {
                return (float) str_replace(',', '.', str_replace('.', '', $value));
            },
            dehydrate: static function (float $value): string {
                return number_format($value, 2, ',', '.');
            },
        )]
        public readonly float $value,
        public readonly string $currency,
    ) {}
}

final class InvoiceDTO
{
    public function __construct(
        public readonly string $id,
        #[CustomFormatter(
            hydrate: static function (string $v): float {
                return (float) str_replace(',', '.', str_replace('.', '', $v));
            },
            dehydrate: static function (float $v): string {
                return number_format($v, 2, ',', '.');
            },
        )]
        public readonly float $subtotal,
        #[CustomFormatter(
            hydrate: static function (string $v): float {
                return (float) str_replace(',', '.', str_replace('.', '', $v));
            },
            dehydrate: static function (float $v): string {
                return number_format($v, 2, ',', '.');
            },
        )]
        public readonly float $tax,
        // hydrate-only: slugify on the way in, pass through on dehydration
        #[CustomFormatter(
            hydrate: static function (string $v): string {
                return strtolower(preg_replace('/\s+/', '-', trim($v)));
            },
        )]
        public readonly string $slug,
    ) {}
}

$moneyData = ['value' => '1.234,56', 'currency' => 'EUR'];

section('Input');
input_dump($moneyData);
section('Hydrate Money — European decimal string → float');
$money = $hydrator->hydrate($moneyData, Money::class);
echo "value (float): {$money->value}\n";
echo "currency: {$money->currency}\n";

section('Dehydrate Money — float → European decimal string');
dump($dehydrator->dehydrate($money));

$invoiceData = [
    'id'       => 'INV-001',
    'subtotal' => '9.999,00',
    'tax'      => '1.999,80',
    'slug'     => 'My Summer Invoice  2025',
];

section('Input');
input_dump($invoiceData);
section('Hydrate InvoiceDTO — numeric strings + hydrate-only slug formatter');
$invoice = $hydrator->hydrate($invoiceData, InvoiceDTO::class);
echo "subtotal: {$invoice->subtotal}\n";
echo "tax: {$invoice->tax}\n";
echo "total: " . ($invoice->subtotal + $invoice->tax) . "\n";
echo "slug: {$invoice->slug}\n";

section('Dehydrate InvoiceDTO — floats back to comma-decimal, slug passed through');
dump($dehydrator->dehydrate($invoice));
