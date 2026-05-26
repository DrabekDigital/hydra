<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\MapperAttribute;
use DrabekDigital\Hydra\Mappers\Mapper;

final readonly class Money
{
    public function __construct(
        public int $cents,
        public string $currency = 'USD',
    ) {}

    public function format(): string
    {
        return '$' . number_format($this->cents / 100, 2);
    }
}

class MoneyCentsMapper implements Mapper
{
    public function hydrate(mixed $value, string $key, string $fullPath): mixed
    {
        return new Money((int) $value);
    }

    public function dehydrate(mixed $value, string $key, string $fullPath): mixed
    {
        return $value instanceof Money ? $value->cents : $value;
    }
}

class MoneyStructMapper implements Mapper
{
    public function hydrate(mixed $value, string $key, string $fullPath): mixed
    {
        return new Money((int) $value['cents'], $value['currency'] ?? 'USD');
    }

    public function dehydrate(mixed $value, string $key, string $fullPath): mixed
    {
        return $value instanceof Money ? ['cents' => $value->cents, 'currency' => $value->currency] : $value;
    }
}

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class AsMoneyInCents implements MapperAttribute
{
    public function getMapperClass(): string { return MoneyCentsMapper::class; }
}

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class AsMoney implements MapperAttribute
{
    public function getMapperClass(): string { return MoneyStructMapper::class; }
}

final class ProductDTO
{
    public function __construct(
        public readonly string $name,
        #[AsMoneyInCents]
        public readonly Money $price,
        #[AsMoney]
        public readonly ?Money $salePrice = null,
    ) {}
}

$data = [
    'name' => 'Keyboard',
    'price' => 4999,
    'salePrice' => ['cents' => 3999, 'currency' => 'USD'],
];

section('Input');
input_dump($data);
section('Hydrate');
$dto = $hydrator->hydrate($data, ProductDTO::class);
echo "name: {$dto->name}\n";
echo "price: {$dto->price->format()}\n";
echo "salePrice: {$dto->salePrice->format()}\n";

section('Dehydrate');
dump($dehydrator->dehydrate($dto));
