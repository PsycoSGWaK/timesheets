<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Le nonce CSP de la requête en cours (spec OWASP : un nonce imprévisible par
 * réponse, jamais réutilisé). Un seul et même service, injecté à la fois dans
 * {@see \App\Twig\SecurityExtension} (pour l'attribut `nonce` des balises
 * `<script>`) et {@see \App\EventSubscriber\SecurityHeadersSubscriber} (pour
 * l'en-tête `Content-Security-Policy`), garantit qu'ils restent synchronisés
 * sans état partagé ailleurs — la valeur est générée une fois, à la demande.
 */
final class ContentSecurityPolicyNonce
{
    private ?string $value = null;

    public function value(): string
    {
        return $this->value ??= bin2hex(random_bytes(16));
    }
}
