<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Security\ContentSecurityPolicyNonce;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute les en-têtes de défense en profondeur qu'aucun bundle ni la config
 * Symfony par défaut ne pose (constaté lors de la rédaction de la doc technique
 * du 28/07/2026 : seuls le cookie de session et le CSRF étaient couverts).
 *
 * Compromis assumé sur `style-src` : `'unsafe-inline'` plutôt qu'un nonce, pour
 * ne pas devoir modifier les huit templates qui embarquent leur propre
 * `<style>`. Risque jugé faible ici — aucune donnée utilisateur n'est jamais
 * interpolée dans un bloc `<style>` (uniquement du CSS écrit par le
 * développeur), contrairement à `script-src` qui reste strict (nonce, spec
 * OWASP). À revoir si un jour du CSS dynamique dépendant d'une entrée
 * utilisateur apparaît.
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ContentSecurityPolicyNonce $nonce,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        if ($event->getRequest()->isSecure()) {
            // 1 an, sous-domaines inclus : valeur standard OWASP. Omis en clair pour
            // ne pas piéger le développement local sans TLS.
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }

    private function contentSecurityPolicy(): string
    {
        // cdn.jsdelivr.net : uniquement le rechargement à chaud FrankenPHP, actif en
        // dev seulement (voir base.html.twig) — inerte en production, jamais chargé.
        return implode('; ', [
            "default-src 'self'",
            \sprintf("script-src 'self' 'nonce-%s' https://cdn.jsdelivr.net", $this->nonce->value()),
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
        ]);
    }
}
