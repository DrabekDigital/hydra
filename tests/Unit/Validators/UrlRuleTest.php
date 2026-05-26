<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\URL;
use DrabekDigital\Hydra\Validators\Rules\UrlRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class UrlRuleTest extends TestCase
{
    public function testValidHttpsUrlPasses(): void
    {
        $rule = new UrlRule(new URL());
        $this->assertEmpty($rule->validate('https://example.com', 'x', 'x'));
    }

    public function testValidHttpUrlPasses(): void
    {
        $rule = new UrlRule(new URL());
        $this->assertEmpty($rule->validate('http://example.com/path?q=1', 'x', 'x'));
    }

    public function testInvalidUrlFails(): void
    {
        $rule = new UrlRule(new URL());
        $errors = $rule->validate('not a url', 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('invalid_url', $errors[0]->code->value);
    }

    public function testFtpSchemeBlockedByDefault(): void
    {
        $rule = new UrlRule(new URL());
        $errors = $rule->validate('ftp://files.example.com', 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('invalid_url', $errors[0]->code->value);
    }

    public function testFtpSchemeAllowedWhenConfigured(): void
    {
        $rule = new UrlRule(new URL(allowedSchemes: ['http', 'https', 'ftp']));
        $this->assertEmpty($rule->validate('ftp://files.example.com', 'x', 'x'));
    }

    public function testAllSchemesAllowedWhenNull(): void
    {
        $rule = new UrlRule(new URL(allowedSchemes: null));
        $this->assertEmpty($rule->validate('ftp://files.example.com', 'x', 'x'));
        $this->assertEmpty($rule->validate('https://example.com', 'x', 'x'));
    }

    public function testKeyAndPathArePreservedInError(): void
    {
        $rule = new UrlRule(new URL());
        $errors = $rule->validate('bad', 'website', 'user.website');
        $this->assertSame('website', $errors[0]->key);
        $this->assertSame('user.website', $errors[0]->fullPath);
    }

    public function testEmptyStringFails(): void
    {
        $rule = new UrlRule(new URL());
        $this->assertCount(1, $rule->validate('', 'x', 'x'));
    }

    public function testUrlWithQueryStringAndFragmentPasses(): void
    {
        $rule = new UrlRule(new URL());
        $this->assertEmpty($rule->validate('https://example.com/path?q=1&r=2#section', 'x', 'x'));
    }

    public function testUrlWithPortPasses(): void
    {
        $rule = new UrlRule(new URL());
        $this->assertEmpty($rule->validate('https://example.com:8443/api', 'x', 'x'));
    }

    public function testUrlWithSubdomainPasses(): void
    {
        $rule = new UrlRule(new URL());
        $this->assertEmpty($rule->validate('https://api.v2.example.com/resource', 'x', 'x'));
    }

    public function testRelativePathFailsValidation(): void
    {
        $rule = new UrlRule(new URL());
        $this->assertCount(1, $rule->validate('/relative/path', 'x', 'x'));
    }

    public function testSchemeOnlyUrlFails(): void
    {
        $rule = new UrlRule(new URL());
        $this->assertCount(1, $rule->validate('https://', 'x', 'x'));
    }

    public function testDisallowedSchemeProducesExactlyOneError(): void
    {
        // ftp:// is a valid URL format but disallowed scheme
        $rule = new UrlRule(new URL(allowedSchemes: ['https']));
        $errors = $rule->validate('ftp://files.example.com', 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('invalid_url', $errors[0]->code->value);
        $this->assertStringContainsString('ftp', $errors[0]->programmerMessage);
    }

    public function testRequireSchemeFalseWithCustomSchemeAllowed(): void
    {
        // requireScheme=false removes the "must have scheme" check; allowedSchemes still filters
        $rule = new UrlRule(new URL(requireScheme: false, allowedSchemes: ['https', 'ftp']));
        $this->assertEmpty($rule->validate('https://example.com', 'x', 'x'));
    }
}
