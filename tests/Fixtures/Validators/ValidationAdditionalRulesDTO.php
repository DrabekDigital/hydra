<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Choice;
use DrabekDigital\Hydra\Attributes\Validation\Email;
use DrabekDigital\Hydra\Attributes\Validation\Hostname;
use DrabekDigital\Hydra\Attributes\Validation\Ip;
use DrabekDigital\Hydra\Attributes\Validation\IsNull;
use DrabekDigital\Hydra\Attributes\Validation\NotNull;
use DrabekDigital\Hydra\Attributes\Validation\Uuid;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;

readonly class ValidationAdditionalRulesDTO
{
    public function __construct(
        #[Email]
        public string $email,
        #[NotNull]
        public ?string $requiredValue,
        #[IsNull]
        public ?string $mustBeNull,
        #[Uuid]
        public string $uuid,
        #[Ip]
        public string $ip,
        #[Hostname]
        public string $hostname,
        #[Choice(enumClass: Status::class)]
        public string $status,
    ) {}
}
