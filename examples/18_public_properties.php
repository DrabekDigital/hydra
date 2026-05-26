<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\DefaultValue;
use DrabekDigital\Hydra\Attributes\Undefinable;

// DTOs with no constructor parameters use public property assignment instead of
// constructor injection. Hydra detects this automatically.

class ConfigDTO
{
    public string $host;
    public int $port;
    #[DefaultValue('info')]
    public string $logLevel;
}

class MutableUserDTO
{
    public string $name;
    public ?string $email = null;
    #[Undefinable]
    public ?string $phone;
}

$cfgData = ['host' => 'localhost', 'port' => 5432];
section('Input');
input_dump($cfgData);
section('Hydrate public-property DTO');
$cfg = $hydrator->hydrate($cfgData, ConfigDTO::class);
echo "host: {$cfg->host}\n";
echo "port: {$cfg->port}\n";
echo "logLevel: {$cfg->logLevel} (from DefaultValue)\n";

section('Dehydrate');
dump($dehydrator->dehydrate($cfg));

$userData = ['name' => 'Alice', 'email' => 'alice@example.com'];
section('Input');
input_dump($userData);
section('Undefinable on public-property DTO');
$user = $hydrator->hydrate($userData, MutableUserDTO::class);
echo "name: {$user->name}\n";
echo "email: {$user->email}\n";
echo 'phone initialized: ' . (isset($user->phone) ? 'yes' : 'no') . "\n";
