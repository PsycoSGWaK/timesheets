# Déploiement — o2switch

## Cible

| | |
|---|---|
| URL | `https://timesheets.idevnormandie.fr` |
| Hébergeur | o2switch, mutualisé cPanel / CloudLinux |
| Document root | `~/public_html/timesheets/public` |
| Dépôt sur le serveur | `~/repositories/timesheets` |
| PHP | 8.4 (sélecteur CloudLinux, configuré au niveau **utilisateur**) |
| Base | MariaDB 11.4.12 |

## Architecture des fichiers

```
~/repositories/timesheets/            dépôt git complet, hors de portée du web
        ├── src/  config/  vendor/  .env.local
        └── public/                   ← seul dossier exposé, en Document Root direct
```

**Document Root du sous-domaine = `/home/nayo1552/repositories/timesheets/public`**,
configuré directement dans cPanel → Domaines. Pas de lien symbolique.

> **Écart avec le montage du portfolio (lien symbolique `public_html/.../public` →
> dépôt), découvert en déployant timesheets le 27/07/2026.** Le sous-domaine
> `timesheets.idevnormandie.fr` a été créé avec un vhost Apache qui **refuse de suivre
> les liens symboliques** (`AH00037: Symbolic link not allowed`), contrairement à celui
> du portfolio (`guillaumehurard.fr`, un domaine addon — probablement un template cPanel
> différent). Aucun `.htaccess` ne peut lever cette restriction : la sous-option
> `FollowSymLinks` d'`Options` n'est pas overridable sur ce compte, même quand d'autres
> sous-options (`Indexes`) le sont. Contournement retenu : pointer le Document Root
> **directement** sur `repositories/<projet>/public`, sans lien.
>
> **Autre piège rencontré à ce point :** une fois le Document Root changé, Apache a
> renvoyé `AH00529` (« unable to check htaccess file ») sur `repositories/timesheets/`.
> Ce dossier, créé par l'outil Git de cPanel, n'avait pas la permission `x` pour "Autres"
> — nécessaire pour qu'Apache le traverse. Correction : `chmod 755` sur ce dossier
> (`~/repositories` lui-même avait déjà les bonnes permissions par défaut).
>
> **Pour un futur sous-domaine sur ce compte : essayer d'abord le Document Root direct**,
> décrit ci-dessus, plutôt que le lien symbolique — plus simple et déjà validé une fois
> qu'on sait que `FollowSymLinks` peut être bloqué sans recours.

**Ne pas reprendre le montage de l'application `fmxx`**, qui recopie tout son dépôt à plat
dans son document root : ce pattern expose `vendor/` et `.env`, et n'est pas transposable à
une application Symfony.

## Base de données

Un **utilisateur MySQL dédié** à cette application, jamais partagé :

- base : `nayo1552_timesheets`
- utilisateur : `nayo1552_timesheets_app`, privilèges limités à cette seule base

Le compte héberge déjà `fmxx` et le portfolio. Réinitialiser le mot de passe d'un utilisateur
partagé casserait les autres applications.

## Configuration serveur

`~/repositories/timesheets/.env.local`, jamais versionné :

```dotenv
APP_ENV=prod
APP_SECRET=<genere sur le serveur>
DATABASE_URL="mysql://nayo1552_timesheets_app:<mdp>@127.0.0.1:3306/nayo1552_timesheets?serverVersion=11.4.12-MariaDB&charset=utf8mb4"
```

## Extensions PHP — danger connu

Le compte n'a **pas de MultiPHP Manager** ; les extensions passent par `cloudlinux-selector`.

> **`--extensions` REMPLACE l'ensemble de la liste, il ne fusionne pas.** Passer une seule
> extension efface toutes les autres — incident déjà survenu sur ce compte, qui a cassé la
> connectivité base du portfolio.

Les extensions requises par cette application sont **exactement celles déjà configurées** pour le
portfolio (`dom`, `pdo`, `pdo_mysql`, `mysqli`, `mbstring`, `intl`, `zip`, `opcache`, `phar`).
Aucune intervention n'est donc nécessaire.

Si un besoin apparaît un jour (par exemple `gd` pour un export PDF) :

1. relever la liste complète en cours avec `php -m` ;
2. repasser cette liste **entière**, augmentée de la nouvelle extension, en un seul appel ;
3. scoper en `--domain` plutôt qu'en `--user` si le CLI l'accepte ;
4. vérifier immédiatement que le portfolio répond toujours (`curl -o /dev/null -w "%{http_code}"`).

Noter aussi que PhpSpreadsheet est en `require-dev` : la production tournant en
`composer install --no-dev`, il n'impose aucune extension.

## Déploiement automatique

À calquer sur `~/deploy_guillaumehurard.sh`, avec ses corrections déjà éprouvées :

- comparer `git rev-parse HEAD` à `origin/main` et sortir immédiatement si rien n'a changé ;
- `git reset --hard origin/main`, puis **`rm -rf var/cache/*`** (le cache de l'asset mapper ne
  s'invalide pas correctement sur cet hôte après suppression d'un fichier) ;
- `composer install --no-dev --optimize-autoloader --no-interaction` ;
- `doctrine:migrations:migrate --no-interaction` ;
- `asset-map:compile` ;
- `trap on_error ERR` envoyant un courriel d'alerte — **corps du message en ASCII pur**, mailx
  bascule en `application/octet-stream` dès qu'un accent apparaît.

> **Mode de défaillance à connaître.** `git reset --hard` s'exécute **avant** `composer install`.
> Si une étape ultérieure échoue, HEAD a déjà bougé : le site sert du code neuf avec un `vendor/`
> et un schéma périmés, et comme le cron ne se déclenche que lorsque `HEAD != origin/main`, il ne
> réessaiera **jamais**. Reprise manuelle en SSH, étape par étape.

**Avant toute modification du crontab** : lire `crontab -l` en entier et le réinjecter complet.
Une édition a déjà effacé la ligne de déploiement de `fmxx` sur ce compte.

## Accès

- SSH : `nayo1552@cornufer.o2switch.net`, clé `~/.ssh/id_ed25519_o2switch`
- L'IP appelante doit être autorisée au préalable dans cPanel → Sécurité → Autorisation SSH
- cPanel : `https://cornufer.o2switch.net:2083`

## Reste à décider

1. **L'hébergement du dépôt distant.** Le déploiement automatique tire depuis `origin/main` :
   il faut donc un remote (GitHub ou autre). Aucun n'est configuré à ce jour.
2. Le déclenchement : cron toutes les 10 minutes comme les deux autres applications, ou
   déploiement manuel tant que le projet est jeune.
