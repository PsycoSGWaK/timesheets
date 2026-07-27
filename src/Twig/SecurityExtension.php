<?php

declare(strict_types=1);

namespace App\Twig;

use App\Security\ContentSecurityPolicyNonce;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose le nonce CSP de la requête aux templates (`csp_nonce()`), pour que les
 * balises `<script>` inline restent autorisées par la politique posée dans
 * {@see \App\EventSubscriber\SecurityHeadersSubscriber} — le même service
 * partagé garantit qu'ils portent toujours la même valeur.
 */
final class SecurityExtension extends AbstractExtension
{
    public function __construct(
        private readonly ContentSecurityPolicyNonce $nonce,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('csp_nonce', $this->cspNonce(...)),
        ];
    }

    public function cspNonce(): string
    {
        return $this->nonce->value();
    }
}
