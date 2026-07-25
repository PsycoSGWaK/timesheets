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
        └── public/                   ← seul dossier exposé
                  ▲
                  │ lien symbolique
~/public_html/timesheets/public ─────┘   ← document root déclaré dans cPanel
```

`~/public_html/timesheets/` est un **vrai dossier**, dont l'unique entrée `public` est un
**lien symbolique** vers le `public/` du dépôt. Montage strictement identique à celui du
portfolio.

```bash
mkdir -p ~/public_html/timesheets
ln -s ~/repositories/timesheets/public ~/public_html/timesheets/public
```

> **Précaution.** Si cPanel a créé `~/public_html/timesheets/public` comme un vrai dossier lors
> de la création du sous-domaine, il doit être retiré avant de poser le lien — après
> vérification de son contenu, jamais à l'aveugle.

Ce montage est celui du portfolio. **Ne pas reprendre celui de l'application `fmxx`**, qui
recopie tout son dépôt à plat dans son document root : ce pattern expose `vendor/` et `.env`, et
n'est pas transposable à une application Symfony.

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
