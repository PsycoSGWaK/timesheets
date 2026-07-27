<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('security/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'lastEmail' => $authenticationUtils->getLastUsername(),
        ]);
    }

    /**
     * L'implémentation réelle est interceptée par le firewall (logout.path) :
     * cette méthode n'est jamais exécutée.
     */
    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepté par le firewall avant d\'atteindre ce contrôleur.');
    }
}
