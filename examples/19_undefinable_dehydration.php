<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\Undefinable;
use DrabekDigital\Hydra\Attributes\UndefinedDehydration;

// #[Undefinable] controls whether the property is required in the input
// and what appears in the dehydrated output when the property was never initialized.
//
//   #[Undefinable]                         → uninitialized: key OMITTED from output
//   #[Undefinable(onUndefinedDehydration: UndefinedDehydration::AS_VALUE, undefinedAs: 'x')]
//     → uninitialized: key present with value 'x'
//   #[Undefinable(onUndefinedDehydration: UndefinedDehydration::AS_NULL)]
//     → uninitialized: key present with null

final class PreferenceDTO
{
    public function __construct(
        public readonly string $userId,
        #[Undefinable]
        public readonly ?string $theme,
        #[Undefinable(onUndefinedDehydration: UndefinedDehydration::AS_VALUE, undefinedAs: false)]
        public readonly ?string $language,
    ) {}
}

$fullData = ['userId' => 'u1', 'theme' => 'dark', 'language' => 'cs'];
section('Input');
input_dump($fullData);
section('All properties initialized');
$full = $hydrator->hydrate($fullData, PreferenceDTO::class);
dump($dehydrator->dehydrate($full));

$partialData = ['userId' => 'u2'];
section('Input');
input_dump($partialData);
section('Undefinable properties missing from input');
$partial = $hydrator->hydrate($partialData, PreferenceDTO::class);
echo 'theme initialized: ' . (isset($partial->theme) ? 'yes' : 'no') . "\n";
echo 'language initialized: ' . (isset($partial->language) ? 'yes' : 'no') . "\n";

$out = $dehydrator->dehydrate($partial);
echo 'theme in output: ' . (array_key_exists('theme', $out) ? 'yes → ' . var_export($out['theme'], true) : 'no (key omitted)') . "\n";
echo 'language in output: ' . (array_key_exists('language', $out) ? 'yes → ' . var_export($out['language'], true) : 'no') . "\n";
