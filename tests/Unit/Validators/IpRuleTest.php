<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Ip;
use DrabekDigital\Hydra\Validators\Rules\IpRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class IpRuleTest extends TestCase
{
    public function testValidIpv4Passes(): void
    {
        $rule = new IpRule(new Ip());
        $this->assertEmpty($rule->validate('192.168.1.10', 'ip', 'ip'));
    }

    public function testValidIpv6Passes(): void
    {
        $rule = new IpRule(new Ip());
        $this->assertEmpty($rule->validate('2001:db8::1', 'ip', 'ip'));
    }

    public function testInvalidIpFails(): void
    {
        $rule = new IpRule(new Ip());
        $errors = $rule->validate('999.999.999.999', 'ip', 'network.ip');
        $this->assertCount(1, $errors);
        $this->assertSame('invalid_ip', $errors[0]->code->value);
        $this->assertSame('ip', $errors[0]->key);
        $this->assertSame('network.ip', $errors[0]->fullPath);
    }

    public function testIpv6FailsWhenOnlyIpv4Allowed(): void
    {
        $rule = new IpRule(new Ip(allowV4: true, allowV6: false));
        $this->assertCount(1, $rule->validate('2001:db8::1', 'ip', 'ip'));
    }
}
