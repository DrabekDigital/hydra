<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Hostname;
use DrabekDigital\Hydra\Validators\Rules\HostnameRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class HostnameRuleTest extends TestCase
{
    public function testValidHostnamePasses(): void
    {
        $rule = new HostnameRule(new Hostname());
        $this->assertEmpty($rule->validate('api.example.com', 'hostname', 'hostname'));
    }

    public function testInvalidHostnameFails(): void
    {
        $rule = new HostnameRule(new Hostname());
        $errors = $rule->validate('-invalid-host', 'hostname', 'server.hostname');
        $this->assertCount(1, $errors);
        $this->assertSame('invalid_hostname', $errors[0]->code->value);
        $this->assertSame('hostname', $errors[0]->key);
        $this->assertSame('server.hostname', $errors[0]->fullPath);
    }

    public function testSingleLabelHostnamePassesWhenTldNotRequired(): void
    {
        $rule = new HostnameRule(new Hostname(requireTld: false));
        $this->assertEmpty($rule->validate('localhost', 'hostname', 'hostname'));
    }
}
