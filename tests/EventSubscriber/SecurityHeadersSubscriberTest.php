<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\SecurityHeadersSubscriber;
use App\Security\ContentSecurityPolicyNonce;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersSubscriberTest extends TestCase
{
    #[Test]
    public function it_sets_the_defensive_headers_on_every_response(): void
    {
        $response = $this->respond(secure: true);

        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        self::assertNotNull($response->headers->get('Permissions-Policy'));
    }

    #[Test]
    public function the_csp_embeds_the_current_request_nonce_and_locks_down_framing(): void
    {
        $nonce = new ContentSecurityPolicyNonce();
        $response = $this->respond(secure: true, nonce: $nonce);

        $csp = (string) $response->headers->get('Content-Security-Policy');

        self::assertStringContainsString("default-src 'self'", $csp);
        self::assertStringContainsString(\sprintf("'nonce-%s'", $nonce->value()), $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
        self::assertStringContainsString("object-src 'none'", $csp);
    }

    #[Test]
    public function hsts_is_sent_over_https(): void
    {
        $response = $this->respond(secure: true);

        self::assertStringContainsString('max-age=31536000', (string) $response->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function hsts_is_omitted_over_plain_http(): void
    {
        // Envoyer HSTS en clair (dev local sans TLS) inciterait le navigateur à
        // exiger https sur un hôte qui ne le sert pas forcément encore.
        $response = $this->respond(secure: false);

        self::assertNull($response->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function it_subscribes_to_the_kernel_response_event(): void
    {
        self::assertArrayHasKey('kernel.response', SecurityHeadersSubscriber::getSubscribedEvents());
    }

    private function respond(bool $secure, ?ContentSecurityPolicyNonce $nonce = null): Response
    {
        $subscriber = new SecurityHeadersSubscriber($nonce ?? new ContentSecurityPolicyNonce());
        $response = new Response();
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create($secure ? 'https://timesheets.test/semaine' : 'http://timesheets.test/semaine');

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        return $response;
    }
}
