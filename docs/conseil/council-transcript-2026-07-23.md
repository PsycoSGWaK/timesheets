# Council transcript — 2026-07-23

## Question d'origine

> Je convoque le conseil pour ce nouveau sujet je veux crée une application pour gérer mes pointages (horaire de badgages) afin de calculer automatiquement mes heures faites. Je te laisse m'indiquer la meilleur stack pour faire ça sachant qu'il y a besoin d'une BDD a prevoir. N'hesite pas à me dire si tu vois des points que je pourrais prendre en compte genre des points d'attentions des features intéressantes etc.

## Question cadrée soumise au conseil

Application de gestion des pointages (badgeages) avec calcul automatique des heures.
Contexte : dev Windows 11, WAMP64 (Apache/PHP/MySQL) installé, projet dans `C:\wamp64\www\timesheets`, dépôt git vierge (README seul), aucune contrainte héritée. Usage personnel mono-utilisateur, extension possible. Contexte français (35h, HS, pauses).
Demandes : stack précise (front/back/BDD/hébergement), modèle de données, points d'attention (légal, pauses, HS, arrondis, fuseaux/changement d'heure, saisie vs import, sauvegarde), features (import CSV pointeuse, rapports mensuels, solde d'heures, export PDF/Excel, PWA, alertes).
Enjeu : données de paie — une erreur de calcul ou une perte de données a des conséquences réelles (litige employeur, heures non payées).

---

## Réponses des conseillers

### The Contrarian (anonymisé en Response B)

Le vrai risque n'est pas technique, il est probatoire — et ta stack l'ignore.

1. **MySQL sur WAMP = point de défaillance unique.** Un PC Windows, un disque, zéro réplication, zéro sauvegarde hors-site. Trois ans de pointages disparaissent avec le SSD. Et l'app ne tourne que quand la machine est allumée : ta "PWA mobile" ne pointera jamais rien. Si tu gardes ça, la seule chose non négociable est un dump chiffré quotidien vers un stockage externe, testé en restauration. Une sauvegarde jamais restaurée n'existe pas.

2. **Une base locale modifiable à volonté a une valeur probatoire faible.** En litige, un relevé personnel n'est un élément sérieux que s'il est difficile à antidater. Donc : journal append-only (jamais d'UPDATE/DELETE sur les pointages bruts, seulement des écritures de correction datées), et un ancrage externe — export PDF mensuel auto-envoyé par mail à toi-même, ou commits signés. Sinon tu construis un outil de confort, pas une preuve.

3. **"Calcul automatique des heures" est le piège.** 35h, HS majorées, contingent, annualisation, accord d'entreprise, pauses rémunérées ou non, forfait, jours fériés : ton code encodera *ta* lecture du droit. Le jour du désaccord, c'est ta formule qui sera attaquée. Calcule la présence brute, pas la paie. Le solde d'heures est une hypothèse à confronter au bulletin, pas une vérité.

4. **La source de vérité reste la pointeuse.** Sans réimport CSV reproductible, ton app est une copie divergente qui donnera raison à personne.

5. **"Extension aux collègues"** : dès le premier horaire d'un tiers, tu es responsable de traitement RGPD sur des données de temps de travail. Ne le fais pas.

Question évitée : un tableur versionné + sauvegarde cloud couvre 90% du besoin. Qu'est-ce que ton app apporte que ça n'apporte pas ?

### The First Principles Thinker (anonymisé en Response E)

**Vous posez la mauvaise question.** « Quelle stack ? » suppose que le problème est logiciel. Il ne l'est pas.

Reformulons depuis zéro : **quel est le job à faire ?** Ce n'est pas « calculer mes heures ». C'est « pouvoir prouver mes heures contre celles de mon employeur ». Vous le dites vous-même dans l'enjeu : litige, heures non payées. Tout le reste découle de ça.

1. **Le calcul n'est pas la valeur — la capture l'est.** Si vous n'avez pas les badgeages bruts, aucune stack ne vous sauve. La brique critique est : d'où viennent les données ? Export de la pointeuse (à quelle fréquence, quel format, l'aurez-vous encore dans 2 ans ?) ou double saisie manuelle ? Répondez à ça avant d'écrire une ligne de code. Si l'export est mensuel et fragile, votre vrai projet est un rituel de sauvegarde, pas une application.

2. **Ce que vous construisez est un détecteur de désaccord.** Le chiffre utile n'est pas « j'ai fait 152h ». C'est « le SIRH dit 149h, moi 152h, voici les 3 jours qui divergent ». Cela change le modèle de données : vous stockez deux sources, pas une, plus l'écart. Une app qui recalcule dans son coin ne prouve rien.

3. **Le stockage est immuable, pas éditable.** Un pointage est un fait daté, jamais modifié. Toute correction est un événement supplémentaire, horodaté et motivé. Un enregistrement qu'on peut éditer sans trace n'a aucune valeur probante — y compris contre vous.

Corollaire brutal : le format de stockage prime sur la stack. Des fichiers texte versionnés dans git, lisibles dans 10 ans sans WAMP, valent mieux qu'un MySQL local que vous perdrez au prochain reformatage. L'app n'est qu'une vue.

### The Expansionist (anonymisé en Response D)

**Vous sous-estimez ce que vous êtes en train de construire.**

Ne modélisez pas des « heures travaillées ». Modélisez un journal d'événements immuable : chaque badgeage est un fait horodaté en UTC (`punch_events` : id, user_id, occurred_at UTC, direction, source [pointeuse/manuel/import], raw_payload, created_at). Les heures calculées ne sont jamais stockées comme vérité — elles sont dérivées, versionnées, recalculables. Pourquoi c'est le gros levier : quand vous découvrirez dans 6 mois que votre règle de pause était fausse, vous rejouez 3 ans d'historique en une commande. Et vous avez une preuve horodatée opposable en cas de litige.

Deuxième levier : **externalisez les règles.** Un `rule_set` versionné (35h, seuils 25%/50%, pause 20 min après 6h, jours fériés) en JSON avec date d'effet. Vous venez d'écrire un moteur de calcul du temps de travail français. C'est exactement ce qui manque à des milliers de TPE qui bricolent sous Excel. Le passage mono-user → multi-tenant coûte une colonne `organization_id` si vous la mettez maintenant.

Stack : Laravel 11 + MySQL 8 (déjà là sous WAMP) + Filament v3 — back-office complet, exports Excel/PDF, tableaux, en quelques jours au lieu de mois. Front PWA en Livewire ou une petite app Vue pour le badgeage mobile hors-ligne (queue locale IndexedDB → sync : indispensable, la pointeuse tombe toujours en panne).

Trois opportunités adjacentes gratuites : import CSV générique multi-formats (chaque marque de pointeuse = un adaptateur, votre moat), un compteur de récup qui devient un vrai module de congés/RTT, et une API + webhook qui rend l'outil intégrable (Slack, calendrier, paie).

Hébergez en local et poussez un dump chiffré quotidien hors-site.

### The Outsider (anonymisé en Response C)

**Ce qui va vraiment décider du succès : le geste quotidien, pas la stack.**

Vous êtes sur le point de construire un truc dont le cas d'usage réel est : « il est 18h12, je viens de badger, je saisis ça où ? ». Si la réponse est « je rentre chez moi, j'allume mon PC, je lance WAMP, j'ouvre localhost », l'app est morte dans trois semaines. Un serveur local sur un poste Windows éteint n'existe pas pour l'utilisateur. Payez les 5 €/mois d'un petit hébergement accessible depuis le téléphone, ou assumez que l'app ne sert qu'une fois par mois pour importer un CSV — les deux sont des choix valides, mais choisissez-en un consciemment, parce qu'ils donnent deux produits différents.

1. **Ne jamais afficher un total sans son calcul.** Si l'app dit « +3h27 ce mois », je dois pouvoir cliquer et voir les lignes, les pauses déduites, les arrondis appliqués. Un chiffre non vérifiable, personne ne l'opposera à son employeur. Le détail du calcul *est* la fonctionnalité, pas le total.

2. **La saisie sera fausse, tout le temps.** J'ai oublié de badger, j'ai badgé deux fois, j'étais en congé. Prévoyez dès le jour 1 : journée incomplète tolérée (pas de plantage), motifs (congé, RTT, télétravail, maladie), et correction rétroactive facile. Une app qui refuse une journée bancale sera contournée dans Excel.

3. **La vérité doit survivre à l'app.** Un export CSV/JSON automatique et lisible à l'œil nu, hors de la base MySQL. Le jour où WAMP casse, où vous refactorez, où le disque lâche, ce fichier est votre seule preuve. Testez la restauration une fois.

Et un écran d'accueil qui répond en une seconde : « où j'en suis ce mois-ci ? ».

### The Executor (anonymisé en Response A)

**Ce que tu fais lundi matin, dans l'ordre.**

Stack : n'invente rien, utilise ce qui tourne déjà. PHP 8.2 + Laravel 11 + MySQL (WAMP), Livewire 3 pour l'UI, Blade. Pas de SPA React, pas d'API séparée : tu ajoutes 3 semaines pour zéro valeur sur un mono-utilisateur.

```
cd C:\wamp64\www\timesheets
composer create-project laravel/laravel .
composer require livewire/livewire barryvdh/laravel-dompdf maatwebsite/excel
php artisan make:migration create_punches_table
```
Virtual host `timesheets.test` dans `httpd-vhosts.conf` (WAMP), pas `php artisan serve`.

**Schéma minimal (4 tables) :**
- `punches` : id, user_id, `punched_at DATETIME` (heure locale Europe/Paris), `direction ENUM('in','out')`, `source ENUM('csv','manual')`, `raw_line TEXT`, `note`. Tu ne modifies JAMAIS une ligne importée : correction = nouvelle ligne + `superseded_by`.
- `workdays` : user_id, `work_date DATE`, `worked_minutes INT`, `break_minutes INT`, `overtime_minutes INT`, `locked BOOL`. Table dérivée, recalculable par `php artisan timesheet:recompute --from=2026-01-01`.
- `balance_ledger` : work_date, `delta_minutes INT`, reason ('récup', 'régul manuelle'). Le solde est une somme, jamais un champ mutable.
- `settings` : hebdo_minutes (2100 = 35h), pause_min, arrondi.

**Trois règles non négociables :**
1. Tout en INT minutes. Un FLOAT sur des heures et tu perds 3 minutes par mois en arrondi. Litige garanti.
2. Pas d'UTC. Le 25 octobre à 2h, une journée fait 25h. Tu stockes l'heure locale affichée par la pointeuse + tu calcules la durée en minutes explicitement, jamais par `diffInHours`.
3. Backup avant le code. Aujourd'hui : tâche planifiée Windows, `mysqldump`, dossier dans OneDrive/Google Drive. 15 minutes de travail, c'est ton assurance-litige.

Semaine 1 = import CSV + calcul + tableau mensuel HTML. Rien d'autre. PDF, PWA, alertes : semaine 4, quand tes chiffres sont prouvés justes contre 3 bulletins de paie réels.

---

## Peer reviews (mapping révélé)

Mapping : A = Executor, B = Contrarian, C = Outsider, D = Expansionist, E = First Principles.

### Review 1
1. **La plus forte : A.** Seule à livrer un plan exécutable sans dette : INT minutes, table dérivée recalculable + ledger de solde, backup planifié *avant* le code, validation contre 3 bulletins réels. Traite correctement le 25 octobre là où D recommande UTC sans dire comment gérer la journée de 25h.
2. **Angle mort : D.** Transforme un besoin perso en produit multi-tenant. C'est le projet qui n'atteindra jamais le premier rapport mensuel juste. Ignore le RGPD que B est seule à voir.
3. **Manqué par tous : le droit français inverse la charge.** Art. L.3171-4 : c'est l'employeur qui doit produire ses relevés ; le salarié n'a qu'à fournir des éléments *suffisamment précis*. Donc (a) un tableau daté propre suffit, l'immuabilité cryptographique est sur-dimensionnée ; (b) le levier le plus rentable est une demande d'accès RGPD (art. 15) aux badgeages détenus par l'employeur ; (c) prescription 3 ans (L.3245-1) = horizon de rétention.

### Review 2
1. **La plus forte : A.** Seule réponse exécutable : schéma nommé, INT minutes, ledger en somme d'événements, correction par `superseded_by`, recalcul par commande, validation contre 3 bulletins réels avant toute feature. Le refus explicite de l'UTC pour cause de DST est la bonne décision. D sur-conçoit avant qu'une seule heure ne soit juste.
2. **Angle mort : B.** Pose la valeur probatoire comme obstacle sans connaître le régime : L.3171-4, la charge pèse sur l'employeur. Une base locale non notariée suffit largement. B décourage le projet sur une prémisse fausse. (C ignore purement la question du modèle de données.)
3. **Manqué par tous :** tests unitaires sur cas limites (25/26 oct., badgeage manquant, minuit, férié) ; **idempotence de l'import** (aucune clé naturelle de déduplication — réimporter double les heures) ; config MySQL (`TIMESTAMP` convertit selon la TZ session = bug de paie silencieux, imposer `DATETIME`, InnoDB, utf8mb4 ; CSV pointeuse souvent CP1252) ; prescription 3 ans = rétention.

### Review 3
1. **La plus solide : A.** Seule réponse exécutable et datée. Son refus de l'UTC est le bon arbitrage : l'heure locale de la pointeuse est la donnée probante. B et E ont le meilleur cadrage du risque mais ne laissent rien à construire.
2. **Angle mort : D.** Transforme un besoin mono-utilisateur en produit SaaS. Son `rule_set` JSON versionné encode une interprétation juridique que personne n'a validée — exactement le piège que B nomme.
3. **Manqué par tous :** la prescription (3 ans) et l'ordre de preuve fixent la rétention, le format (lisible par un tiers, pas un dump MySQL) et la granularité. Personne ne mentionne le coût de sortie : que devient l'app le jour d'un changement d'employeur ou de pointeuse ?

### Review 4
1. **La plus forte : E.** Seule à reformuler correctement le job : détecter et documenter l'écart avec le décompte employeur. A reste le meilleur binôme d'exécution — E donne le cap, A le plan.
2. **Angle mort : D.** Moteur de droit du travail multi-tenant pour un besoin mono-utilisateur. 6 mois de maintenance pour un usage qui mourra avant. Zéro mot sur la responsabilité si des TPE utilisent ce calcul.
3. **Manqué par tous :** L.3171-4 (charge partagée, un relevé simple et cohérent suffit) ; accès aux données via RGPD art. 15, qui conditionne tout le projet ; prescription 3 ans ; tests de non-régression absents partout.

### Review 5
1. **La plus forte : A.** La seule que je pourrais suivre lundi matin sans rien deviner. D est séduisante mais vend un moteur multi-tenant pour un usage perso.
2. **Angle mort : B.** Démolit le projet puis conclut « un tableur suffit ». Quelqu'un qui a déjà créé son dépôt git ne veut pas d'un tableur. Aucune alternative exécutable. C et E ont le même défaut en moins brutal : d'excellentes questions, zéro code.
3. **Manqué par tous :** personne ne dit à quoi ressemble concrètement le CSV d'une pointeuse, alors que tout le projet démarre par cet import. Ni la durée de conservation utile (3 ans), ni un test de restauration daté, ni une estimation du temps de dev.

---

## Verdict du président

Voir `council-report-2026-07-23.html`.
