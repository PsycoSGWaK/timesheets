# Source des données — ADP « Fiche de présence »

La pointeuse est **MonADP** (module *Temps* → onglet *Fiche de présence*), vue hebdomadaire.
Aucun export CSV ou Excel n'est proposé par l'interface : la seule extraction possible est une
**sélection de texte copiée dans le presse-papier**.

> Les horaires figurant dans ce document sont **fictifs**. Ils reproduisent fidèlement la
> structure et les cas limites réels sans exposer de données personnelles, le dépôt ayant
> vocation à être public.

---

## 1. Ce que fournit l'écran

Pour chacun des sept jours de la semaine :

| Élément | Contenu |
|---|---|
| Date | `JJ/MM` — **sans année** |
| Total ADP | `H:MMh`, le temps de travail **calculé par l'employeur** |
| Pointages | 0 à N horodatages `HH:MM`, dans l'ordre chronologique |
| Attendu | l'horaire théorique (`08:30 - 12:12` / `13:00 - 16:42`), ou `Repos` |
| Événement | libellé + statut, ex. `Télétravail - En attente` / `Journée complète` |

Un encart *Résumé* donne le total de la semaine, égal à la somme des totaux quotidiens.

---

## 2. Grammaire du texte collé

Le presse-papier restitue une seule colonne de lignes, dans cet ordre :

```
Lun … Dim                  ← 7 lignes d'en-tête, à ignorer

JJ/MM                      ← début d'un bloc jour
(ligne vide ou espace)
H:MMh                      ← total ADP        (absent si repos)
[ libellé événement ]      ← ex. « Télétravail - En attente »
[ portée événement ]       ← ex. « Journée complète »
Afficher                   ← bruit d'interface
Pointage                   ← étiquette
HH:MM                      ← horodatage       ⎫
Afficher                                      ⎬ répété par pointage
Pointage                                      ⎪
HH:MM                                         ⎭
Attendu
HH:MM - HH:MM              ⎫ horaire théorique
HH:MM - HH:MM              ⎭   ou « Repos »
```

### Règles de découpage

1. Un nouveau bloc jour commence à chaque ligne conforme à `\d{2}/\d{2}`.
2. Les lignes `Afficher` et `Pointage` sont du bruit : à supprimer avant analyse.
3. Les lignes vides ou réduites à une espace insécable sont à supprimer.
4. Les horodatages retenus sont les `HH:MM` situés **avant** le mot-clé `Attendu`. Ceux qui
   suivent appartiennent à l'horaire théorique et ne sont pas des pointages.
5. Les pointages s'apparient dans l'ordre : entrée, sortie, entrée, sortie. Un nombre **impair**
   signale un badgeage manquant.
6. Un bloc sans aucun pointage est soit un repos, soit une journée d'événement.

### Points de vigilance

- **Le format horaire est `HH:MM`** (deux-points). Le proto Excel utilisait `08h55` : c'était
  une convention de ressaisie manuelle, pas le format de la source.
- **L'année est absente du texte collé.** Elle figure uniquement dans l'en-tête de période de
  l'écran (« 20 juil. 2026 - 26 juil. 2026 »), qui n'est pas toujours sélectionné. L'import doit
  donc **demander ou déduire l'année**, puis vérifier la cohérence : les jours de la semaine
  reconstitués doivent correspondre aux en-têtes `Lun`…`Dim`. C'est un garde-fou gratuit contre
  une erreur d'année.
- **Les événements portent un statut** (`En attente`, et vraisemblablement `Approuvé`,
  `Refusé`). Une journée peut donc être comptée alors que la demande n'est pas validée.

---

## 3. Ce que la source apporte au modèle

### 3.1 Le total ADP est le décompte de l'employeur

Chaque bloc jour porte le total calculé **par ADP**. C'est exactement la colonne
`employer_minutes` prévue au modèle : l'application n'a plus à deviner le décompte adverse, elle
le lit. Le détecteur d'écart devient trivial et fiable.

### 3.2 L'horaire théorique donne la journée de référence

`08:30 - 12:12` puis `13:00 - 16:42` totalise **7 h 24 par jour**, soit **37 h par semaine**.

Cela confirme la règle métier : le contrat est à 35 h, l'horaire réel à 37 h, et les 2 h
hebdomadaires d'écart alimentent le compteur RTT. La journée de référence de l'application est
donc **7 h 24, pas 7 h** — le proto Excel utilisait les deux valeurs selon les colonnes, ce qui
faisait partie de ses incohérences.

### 3.3 La règle de pause minimale est confirmée par ADP

Le recoupement des totaux ADP avec les pointages bruts valide la règle des 30 minutes :

| Cas | Présence brute | Pause | Total ADP | Lecture |
|---|---|---|---|---|
| Pause suffisante | 7 h 26 | 31 min | 7 h 26 | aucun décompte |
| Pause trop courte | 7 h 08 | 26 min | 7 h 04 | **−4 min**, soit exactement `30 − 26` |

La règle « si la pause est inférieure à 30 minutes, la différence est retranchée » est donc bien
celle qu'applique l'employeur, et non une invention du proto. C'est la seule règle du classeur
Excel qui survit à la vérification.

---

## 4. Anomalies à détecter

| Anomalie | Détection |
|---|---|
| Nombre impair de pointages | badgeage manquant |
| Pause déjeuner hors de la fenêtre 11h30 – 14h00 | défaut de pointage côté employeur |
| Pause inférieure à 30 minutes | décompte appliqué — informer l'utilisateur |
| **Total ADP à `0:00` alors que des pointages existent** | écart majeur, voir ci-dessous |
| Total ADP ≠ total recalculé | écart à investiguer, cœur de l'outil |
| Événement au statut `En attente` | valorisation provisoire |
| Jour de semaine reconstitué ≠ en-tête | erreur d'année à l'import |

### Le cas `0:00`

Un jour peut afficher un total ADP de `0:00 h` **tout en portant quatre pointages valides et une
pause conforme**. Le total hebdomadaire du *Résumé* confirme que ce zéro est réellement pris en
compte : la journée entière est perdue dans le décompte.

Causes possibles, à départager : journée en attente de validation, défaut de pointage non résolu,
ou anomalie applicative. **C'est la justification même de l'outil** — sans recalcul indépendant,
une journée complète disparaît sans que rien ne le signale à l'écran.

À noter : ADP ne consolide les totaux qu'**en fin de journée, après minuit**. Un `0:00` sur le
jour en cours est donc normal. Sur un jour passé, il ne l'est pas.

---

## 4 bis. Le total employeur est une observation, pas une donnée

Puisqu'un total peut changer après coup — consolidation nocturne, validation d'un événement,
régularisation d'un défaut de pointage — il ne doit **jamais être écrasé en silence** lors d'un
réimport.

| Objet | Nature | Comportement au réimport |
|---|---|---|
| Pointage | fait immuable | dédupliqué, jamais modifié |
| Total employeur | **relevé horodaté** | une nouvelle observation est **ajoutée** |

On conserve ainsi l'historique des révisions du décompte de l'employeur (« le 24/07, ADP
annonçait 0:00 pour le 23/07 »), ce qui a une valeur directe en cas de désaccord.

Deux conséquences :

1. Le détecteur d'écart doit connaître l'**état de consolidation** d'une journée et rester
   silencieux tant que le total n'est pas stabilisé, sous peine d'alerter chaque soir sans motif.
2. Bonne pratique d'usage : recoller une semaine une seconde fois quelques jours plus tard, pour
   capter les valeurs consolidées. L'import étant idempotent, l'opération est sans risque.

### Corollaire : la fenêtre où seule l'application sait

ADP ne restitue aucun total avant minuit. Entre la prise de poste et la fin de journée,
l'application est la **seule** source capable de dire où en est l'utilisateur — en combinant les
pointages déjà collés et les saisies prévisionnelles (§ 4.6 de la spécification). C'est la
fonction que la pointeuse ne rend pas, et la raison d'un usage quotidien.

---

## 5. Conséquences sur l'application

1. **Écran « coller le pointage »** : une zone de texte, un aperçu de ce qui a été compris, une
   validation. Pas de saisie champ par champ.
2. **Le texte collé est conservé intégralement** dans `raw_payload`. Le parseur peut être rejoué
   sur l'historique sans ressaisie.
3. **Import idempotent** : la clé de déduplication est `(date, heure, rang)`. Recoller la même
   semaine ne doit rien dupliquer.
4. **Import à la semaine**, puisque c'est la maille de l'écran — ce qui tombe juste, la couche
   hebdomadaire étant précisément celle qu'exige le calcul des RTT.
