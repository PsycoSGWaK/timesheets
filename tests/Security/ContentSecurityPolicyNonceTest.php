<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\ContentSecurityPolicyNonce;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContentSecurityPolicyNonceTest extends TestCase
{
    #[Test]
    public function it_generates_a_non_empty_value(): void
    {
        self::assertNotSame('', (new ContentSecurityPolicyNonce())->value());
    }

    #[Test]
    public function the_value_is_stable_across_calls_within_the_same_request(): void
    {
        // Le nonce doit être identique partout dans la même réponse (balises <script>
        // et en-tête CSP) : régénérer à chaque appel casserait la politique.
        $nonce = new ContentSecurityPolicyNonce();

        self::assertSame($nonce->value(), $nonce->value());
    }

    #[Test]
    public function two_instances_produce_different_values(): void
    {
        // Un nonce prévisible ou partagé entre requêtes viderait la protection CSP
        // de son sens (spec OWASP : un nonce par réponse).
        self::assertNotSame((new ContentSecurityPolicyNonce())->value(), (new ContentSecurityPolicyNonce())->value());
    }
}
