<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use DrabekDigital\Hydra\Caching\Psr16CacheAdapter;
use DrabekDigital\Hydra\Dehydrator;
use DrabekDigital\Hydra\Hydrator;
use Psr\SimpleCache\CacheInterface;

final class ProductDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $price,
    ) {}
}

final class CountingCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $items = [];
    public int $hasCalls = 0;
    public int $getCalls = 0;
    public int $setCalls = 0;

    public function has(string $key): bool { $this->hasCalls++; return array_key_exists($key, $this->items); }
    public function get(string $key, mixed $default = null): mixed { $this->getCalls++; return $this->items[$key] ?? $default; }
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool { $this->setCalls++; $this->items[$key] = $value; return true; }
    public function delete(string $key): bool { unset($this->items[$key]); return true; }
    public function clear(): bool { $this->items = []; return true; }
    public function getMultiple(iterable $keys, mixed $default = null): iterable { $r = []; foreach ($keys as $k) { $r[$k] = $this->get($k, $default); } return $r; }
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool { foreach ($values as $k => $v) { $this->set($k, $v, $ttl); } return true; }
    public function deleteMultiple(iterable $keys): bool { foreach ($keys as $k) { $this->delete($k); } return true; }
}

function section(string $title): void { echo "\n=== {$title} ===\n"; }
function stats(CountingCache $c): void { echo "has={$c->hasCalls}, get={$c->getCalls}, set={$c->setCalls}\n"; }
function input_dump(mixed $data, int $depth = 0): void {
    if (!is_array($data)) { return; }
    $pad = str_repeat('  ', $depth + 1);
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            echo "{$pad}{$k}: <array(" . count($v) . ")>\n";
            input_dump($v, $depth + 1);
        } else {
            $type = match(true) { is_string($v) => 'string', is_int($v) => 'int', is_float($v) => 'float', is_bool($v) => 'bool', is_null($v) => 'null', default => gettype($v) };
            $repr = match(true) { is_string($v) => '"' . $v . '"', is_bool($v) => ($v ? 'true' : 'false'), is_null($v) => 'null', default => (string) $v };
            echo "{$pad}{$k}: {$repr} <{$type}>\n";
        }
    }
}

$psr16 = new CountingCache();
$cache = new Psr16CacheAdapter($psr16);

$data = ['id' => 'P-1', 'name' => 'Keyboard', 'price' => 2499];

section('Input');
input_dump($data);
section('First run — generates and caches');
$h1 = new Hydrator($cache);
$d1 = new Dehydrator($cache);
$p1 = $h1->hydrate($data, ProductDTO::class);
$s1 = $d1->dehydrate($p1);
echo "hydrated: {$p1->name} ({$p1->id})\n";
echo 'dehydrated: ' . json_encode($s1) . "\n";
stats($psr16);

section('Second run — reads from cache');
$h2 = new Hydrator($cache);
$d2 = new Dehydrator($cache);
$p2 = $h2->hydrate($data, ProductDTO::class);
$s2 = $d2->dehydrate($p2);
echo "hydrated: {$p2->name} ({$p2->id})\n";
echo 'dehydrated: ' . json_encode($s2) . "\n";
stats($psr16);
