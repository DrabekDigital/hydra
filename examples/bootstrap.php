<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use DrabekDigital\Hydra\Caching\FileCache;
use DrabekDigital\Hydra\Dehydrator;
use DrabekDigital\Hydra\Hydrator;
use DrabekDigital\Hydra\Validator;

$cache = new FileCache(__DIR__ . '/../cache/examples');
$hydrator = new Hydrator($cache);
$dehydrator = new Dehydrator($cache);
$validator = new Validator();

function section(string $title): void
{
    echo "\n=== {$title} ===\n";
}

function dump(mixed $value): void
{
    echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

function errors(iterable $errs): void
{
    foreach ($errs as $e) {
        echo "  [{$e->code->value}] {$e->fullPath}: {$e->programmerMessage}\n";
    }
}

function input_dump(mixed $data, int $depth = 0): void
{
    if (!is_array($data)) {
        return;
    }
    $pad = str_repeat('  ', $depth + 1);
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            echo "{$pad}{$k}: <array(" . count($v) . ")>\n";
            input_dump($v, $depth + 1);
        } else {
            $type = match (true) {
                is_string($v) => 'string',
                is_int($v)    => 'int',
                is_float($v)  => 'float',
                is_bool($v)   => 'bool',
                is_null($v)   => 'null',
                default       => gettype($v),
            };
            $repr = match (true) {
                is_string($v) => '"' . $v . '"',
                is_bool($v)   => ($v ? 'true' : 'false'),
                is_null($v)   => 'null',
                default       => (string) $v,
            };
            echo "{$pad}{$k}: {$repr} <{$type}>\n";
        }
    }
}
