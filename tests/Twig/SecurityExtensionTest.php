<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Security\ContentSecurityPolicyNonce;
use App\Twig\SecurityExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SecurityExtensionTest extends TestCase
{
    #[Test]
    public function csp_nonce_returns_the_shared_nonces_value(): void
    {
        $nonce = new ContentSecurityPolicyNonce();
        $extension = new SecurityExtension($nonce);

        self::assertSame($nonce->value(), $extension->cspNonce());
    }
}
