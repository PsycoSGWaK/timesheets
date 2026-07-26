# Déploiement automatique via GitHub Actions

Mise en place et exploitation du déploiement continu vers o2switch.

À chaque `push` sur `main`, l'application est construite sur un runner GitHub puis
déployée en production. Aucune action manuelle n'est nécessaire.

Ce dispositif est **repris du portfolio**, où il tourne déjà : le workflow reprend ses
corrections éprouvées. Voir `../portfolio/docs/deploiement-github-actions.md` pour
l'historique des écueils. Les particularités de cette application sont signalées.

---

## Principe

**Tout ce qui est coûteux ou fragile s'exécute sur le runner GitHub, jamais sur
l'hébergement mutualisé.** Les extensions PHP d'o2switch se réinitialisent lors des
mises à jour de build et cassaient régulièrement Composer ; en construisant ailleurs,
le problème disparaît.

**o2switch filtre le SSH par adresse IP** et les runners GitHub ont des IP
imprévisibles. La solution est l'API cPanel `SshWhitelist`, qui autorise une IP à la
volée pour la durée du job. La voie webhook HTTPS a été essayée sur le portfolio et
échoue : le pare-feu bloque le trafic entrant venant d'IP de datacenter.

**Répartition des rôles :** le code source arrive par git, seuls les artefacts non
versionnés (`vendor/`, assets compilés) passent par rsync. Il n'y a donc **aucun
`--delete` à la racine du projet** : le `.env.local` ne peut pas être détruit par une
erreur de configuration du transfert.

---

## Mise en place (une seule fois)

> Cette application n'a **jamais été déployée** : contrairement au portfolio, il faut
> d'abord installer le dépôt et la base sur le serveur. En revanche, il n'y a
> **aucun cron de déploiement à désactiver** — cette étape du portfolio ne s'applique pas.

### 1. Préparer le serveur

Le montage cible est décrit dans [deploiement.md](deploiement.md) : dépôt hors de
portée du web, seul `public/` exposé par lien symbolique.

```bash
ssh nayo1552@cornufer.o2switch.net
git clone git@github.com:PsycoSGWaK/timesheets.git ~/repositories/timesheets
mkdir -p ~/public_html/timesheets
ln -s ~/repositories/timesheets/public ~/public_html/timesheets/public
```

> ⚠️ Si cPanel a créé `~/public_html/timesheets/public` comme un **vrai dossier** lors de
> la création du sous-domaine, il doit être retiré avant de poser le lien — après
> vérification de son contenu, jamais à l'aveugle.

Le serveur doit pouvoir faire `git fetch` depuis GitHub (clé de déploiement ou dépôt public).

### 2. Créer la base et l'utilisateur MySQL

Un utilisateur **dédié** à cette application, jamais partagé — le compte héberge aussi
`fmxx` et le portfolio, et réinitialiser un mot de passe partagé les casserait :

- base : `nayo1552_timesheets`
- utilisateur : `nayo1552_timesheets_app`, privilèges limités à cette seule base

### 3. Renseigner le `.env.local` de production

Sur le serveur, dans `~/repositories/timesheets/.env.local`, en permissions `600` :

```dotenv
APP_ENV=prod
APP_SECRET=<genere sur le serveur>
DATABASE_URL="mysql://nayo1552_timesheets_app:<mdp>@127.0.0.1:3306/nayo1552_timesheets?serverVersion=11.4.12-MariaDB&charset=utf8mb4"
```

Ce fichier n'est jamais versionné, et le workflow le sauvegarde avant chaque déploiement
dans `~/.env.local.timesheets.sauvegarde`.

### 4. Créer un token API cPanel

cPanel → **Sécurité** → **Jetons d'API** → *Créer*, nommé par exemple
`github-actions-timesheets`. **Copiez-le immédiatement : il n'est affiché qu'une fois.**

### 5. Générer une clé SSH dédiée

Distincte de la clé personnelle, pour pouvoir la révoquer sans perdre son propre accès :

```bash
ssh-keygen -t ed25519 -f ~/.ssh/id_ed25519_timesheets_deploy -N "" -C "github-actions-timesheets"
```

Autoriser la clé publique sur le serveur, puis vérifier :

```bash
ssh nayo1552@cornufer.o2switch.net "cat >> ~/.ssh/authorized_keys" < ~/.ssh/id_ed25519_timesheets_deploy.pub
ssh -i ~/.ssh/id_ed25519_timesheets_deploy -o IdentitiesOnly=yes nayo1552@cornufer.o2switch.net "whoami"
```

### 6. Enregistrer les secrets GitHub

Dépôt → **Settings** → **Secrets and variables** → **Actions** → *New repository secret* :

| Nom | Valeur |
|---|---|
| `CPANEL_USERNAME` | `nayo1552` |
| `CPANEL_SERVER` | `cornufer.o2switch.net` |
| `CPANEL_API_TOKEN` | Le token de l'étape 4 |
| `O2SWITCH_SSH_KEY` | Le contenu de la clé **privée** `~/.ssh/id_ed25519_timesheets_deploy`, lignes `BEGIN`/`END` comprises |

### 7. Lancer un premier déploiement

Onglet **Actions** → workflow **Deploiement o2switch** → *Run workflow*.

Le premier passage applique la migration initiale et crée les trois tables.

---

## Au quotidien

**Tout `push` sur `main` déclenche un déploiement.** Le workflow peut aussi être lancé à
la main depuis l'onglet Actions.

Ce qu'il enchaîne :

1. Build sur le runner — `composer install --no-dev` puis `asset-map:compile`
2. Vidage de la liste blanche SSH, puis ajout de l'IP du runner
3. Sauvegarde du `.env.local` de production
4. `git fetch` + `reset --hard origin/main`, et `rm -rf var/cache/*`
5. Rsync de `vendor/`, `public/assets/` et `assets/vendor/` uniquement
6. Migrations Doctrine et reconstruction du cache
7. Health check : `/import` et `/semaine` doivent renvoyer 200, sinon le job échoue
8. Fermeture de l'accès SSH et effacement de la clé, quoi qu'il arrive

> Le health check n'interroge pas `/` : la racine **redirige** (302) vers l'écran de
> collage. Ce sont `/import` et `/semaine` qui doivent répondre 200.

---

## Points d'attention

### Votre IP est effacée à chaque déploiement

La liste blanche SSH est plafonnée à **5 entrées** et l'API ne permet pas de distinguer
l'IP d'un runner de celle d'un poste de travail. Le workflow commence donc par un
`remove_all`.

**Conséquence : après chaque déploiement, l'accès SSH manuel ne fonctionne plus.** Il
faut ré-autoriser son IP dans cPanel → **Sécurité** → **Autorisation SSH**.

Vérifier son IP publique, qui change régulièrement sur une connexion résidentielle :

```bash
curl -s https://api.ipify.org
```

**Symptôme d'une IP non autorisée :** `Connection timed out` ou `Connection reset by
peer` **pendant l'échange de bannière**. Réflexe : vérifier son IP avant de chercher
ailleurs.

### Délai de propagation

Une suppression met environ **5 minutes** à devenir effective. Il est normal que le SSH
réponde encore juste après un déploiement, puis se coupe quelques minutes plus tard.

### Extensions PHP — danger connu

`--extensions` de `cloudlinux-selector` **remplace** la liste entière, il ne fusionne
pas. Un incident sur ce compte a déjà cassé la connectivité base du portfolio. Les
extensions requises ici sont exactement celles déjà configurées ; aucune intervention
n'est nécessaire. Voir [deploiement.md](deploiement.md).

---

## Dépannage

### Le job échoue sur « Ouvre l'acces SSH »

Message `Vous avez atteint la limite d'exceptions autorisées` : la liste blanche est
pleine. Le workflow fait normalement un `remove_all` au préalable — si l'erreur
persiste, vider la liste à la main depuis cPanel.

### Le job échoue sur le health check

Consulter les logs de production :

```bash
tail -50 ~/repositories/timesheets/var/log/prod.log
```

> Les logs de production sont écrits **dans un fichier** et non sur `php://stderr`, qui
> n'atterrit nulle part de consultable sur ce compte (`config/packages/monolog.yaml`).
> Ne pas défaire cette configuration, sans quoi tout diagnostic devient impossible.

### Le déploiement s'est arrêté en cours de route

Le `git reset --hard` s'exécute **avant** le transfert de `vendor/`. Si une étape
ultérieure échoue, le serveur porte du code neuf avec des dépendances périmées.
Relancer le workflow depuis l'onglet Actions corrige la situation ; contrairement à
l'ancien mécanisme par cron, il n'y a pas de condition `HEAD != origin/main` qui
empêcherait de réessayer.

---

## Fichiers concernés

| Fichier | Rôle |
|---|---|
| `.github/workflows/deploiement.yml` | Le workflow |
| `config/packages/monolog.yaml` | Logs de production en fichier (indispensable au diagnostic) |
| `~/.env.local.timesheets.sauvegarde` | Sauvegarde rafraîchie à chaque déploiement |
