<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DrabekDigital\Hydra\Attributes\Validation\URL;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;

final class WebResourceDTO
{
    public function __construct(
        public readonly string $title,
        #[URL]
        public readonly string $homepageUrl,
        #[URL(allowedSchemes: ['https'])]
        public readonly string $apiUrl,
    ) {}
}

$validData = [
    'title'       => 'My Service',
    'homepageUrl' => 'http://example.com',
    'apiUrl'      => 'https://api.example.com/v1',
];

section('Input');
input_dump($validData);
section('Valid — both URLs accepted');
$dto = $hydrator->hydrate($validData, WebResourceDTO::class);
$validator->validate($dto);
echo "homepageUrl: {$dto->homepageUrl}\n";
echo "apiUrl: {$dto->apiUrl}\n";

$invalidData = [
    'title'       => 'Bad Service',
    'homepageUrl' => 'not-a-url',
    'apiUrl'      => 'http://api.example.com',
];

section('Input');
input_dump($invalidData);
section('Invalid — malformed URL + wrong scheme for apiUrl');
$invalid = $hydrator->hydrate($invalidData, WebResourceDTO::class);
try {
    $validator->validate($invalid);
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}

$noSchemeData = [
    'title'       => 'No Scheme',
    'homepageUrl' => 'example.com',
    'apiUrl'      => 'https://api.example.com',
];

section('Input');
input_dump($noSchemeData);
section('Invalid — URL without scheme');
$noScheme = $hydrator->hydrate($noSchemeData, WebResourceDTO::class);
try {
    $validator->validate($noScheme);
} catch (ValidationFailure $e) {
    errors($e->getErrors());
}
