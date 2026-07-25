# Spécification — application de pointages

Source : rétro-ingénierie du proto `Calendrier Pointage 2025 GHU.xlsx`, complétée par les
précisions métier de l'utilisateur. Révision du 24/07/2026.

> **Note de confidentialité.** Ce document est versionné et le dépôt a vocation à être public
> (portfolio). Les horaires cités en exemple sont **fictifs** : ils illustrent des formats et
> des cas limites réels sans reproduire de données personnelles. Les fichiers sources réels
> restent dans `private/`, non versionné.

> **Origine du proto.** Le classeur a été écrit par un collègue non-développeur puis repris
> tel quel. Ses formules ne font donc autorité sur rien : elles sont une piste de lecture du
> besoin, pas une référence de calcul. Tout ce qui n'a pas été confirmé explicitement par
> l'utilisateur est signalé comme tel.

---

## 1. Sources de données

### 1.1 La donnée maître

**L'onglet `Pointage` est la seule source de vérité du proto.** Une ligne par jour, l'année
entière, `A1:BC374`.

Quatre horodatages saisis par jour :

| Colonne | Sens |
|---|---|
| `Matin` | prise de poste |
| `Midi` | départ en pause déjeuner |
| `Après-Midi` | retour de pause déjeuner |
| `Soir` | fin de journée |

Format : **texte** `08h55` — séparateur `h`, zéro initial présent.

### 1.2 Les autres onglets

| Onglet | Statut |
|---|---|
| `Feuil1` | **Bac à sable, aucune valeur probante.** Copies d'écran de la pointeuse collées pour être retranscrites par un LLM, dont les sorties ont ensuite été recopiées à la main dans `Pointage`. Une session a produit une **plage horaire inventée** (`08h55 - 09h08`) qui n'existe dans aucun export réel. À ignorer intégralement. |
| `Calendrier` | Vue annuelle destinée à l'impression (`B4:X39`). Restitution, pas calcul. |
| `Paramétrage` | Constantes du calcul. Fait autorité pour les quotas. |

**Conséquence sur le projet.** La saisie actuelle passe par une chaîne
capture d'écran → transcription par un LLM → recopie manuelle. C'est fragile, non reproductible
et non vérifiable : c'est précisément ce que l'application doit remplacer. Reste à établir si
la pointeuse permet un **export CSV natif** — auquel cas l'import devient direct et le projet
gagne sa fiabilité d'un coup.

---

## 2. Vocabulaire métier

### Événements exprimés en jours (1 ou 0,5)

| Code | Sens |
|---|---|
| `CP` | Congé Payé |
| `CA` | Congé Ancienneté |
| `RTT` | RTT — se **pose** en jours, s'**acquiert** en heures (§ 4.3) |
| `JF` | Jour Férié |
| `TT` | Télétravail |

### Événements exprimés en heures

| Code | Sens |
|---|---|
| `HS` | Heures Supplémentaires (à récupérer) à poser |
| `HV` | Heures Variables à poser |
| `Abs` | Absence — autres heures à poser |

### Compteurs

`Dispo`, `Transfert`, `Variable`, `Récup`, `Boni`, `Récup Total`, `Paiement`, `Payé`.

Une heure supplémentaire suit l'un de **trois destins** exclusifs, choisis à la main :
`Récupération`, `Variable` ou `Paiement`. Un champ libre `Motif` accompagne la saisie.

---

## 3. Paramètres

| Paramètre | Valeur 2025 | Confirmé |
|---|---|---|
| Durée hebdomadaire de référence | 35 h | ✅ |
| Seuil de bascule en heures supplémentaires | 37 h | ✅ |
| Acquisition RTT maximale | 2 h / semaine | ✅ |
| Pause déjeuner minimale | 30 min | ✅ |
| Fenêtre autorisée de pause déjeuner | 11h30 – 14h00 | ✅ |
| Journée de référence | 7 h (35 / 5) | déduit |
| Quota télétravail | 1 j / semaine, soit 47 j / an | ✅ |
| Semaines ouvrées | 47 | ✅ |
| Quotas de congés | CP 25 j · CP-1 4 j · CA 4 j · CA-1 0 j · RTT-1 2 j | proto |
| Majorations HS (25 % / 50 %) | — | ❌ **hors périmètre** (§ 4.5) |

---

## 4. Règles de calcul

### 4.1 Temps de présence quotidien

Présence brute = (`Midi` − `Matin`) + (`Soir` − `Après-Midi`), en minutes entières.

### 4.2 Pause déjeuner

Deux règles distinctes, à ne pas confondre :

1. **Durée minimale — règle de calcul.** Si la pause effective est inférieure à 30 minutes, la
   différence est retranchée du temps de travail. Une pause de 20 minutes coûte donc 10 minutes
   de présence. *(Seule règle du proto qui soit saine et confirmée.)*
2. **Fenêtre autorisée — règle de contrôle.** La pause doit se situer entre **11h30 et 14h00**.
   Hors de cette fenêtre, l'employeur constate un **défaut de pointage**. Ce n'est pas un calcul
   mais une **anomalie à signaler** : la journée reste calculée, et l'application affiche un
   avertissement pour que l'utilisateur sache qu'une régularisation l'attend.

### 4.3 Acquisition des RTT — règle hebdomadaire

Confirmée par l'utilisateur. Elle porte sur le **total de la semaine**, pas sur la journée :

| Temps travaillé sur la semaine | Effet |
|---|---|
| ≤ 35 h | rien |
| 35 h → 37 h | le surplus alimente le **compteur RTT** (2 h au maximum) |
| > 37 h | 2 h en RTT, et **tout ce qui dépasse 37 h devient des heures supplémentaires** |

Le compteur RTT est alimenté en heures puis **posé en jours**.

> **Conséquence d'architecture.** Le calcul ne peut pas être purement quotidien : il faut une
> couche d'agrégation **hebdomadaire** entre la journée et le mois. C'est le point structurant
> du moteur, et le proto ne le modélise pas correctement.

### 4.4 Télétravail

1 jour par semaine de droit, soit 47 jours sur l'année. Les dépassements existent mais sont
exceptionnels (≈ 0,01 % des cas) : le quota se traite en **avertissement souple**, jamais en
blocage.

### 4.5 Ce que l'application ne calcule pas

Les taux de majoration (25 % / 50 %) figurent dans le proto sans que leur règle d'application
soit connue — l'auteur du classeur n'est pas joignable sur ce point et l'utilisateur ne peut pas
la confirmer.

**Décision : hors périmètre.** L'application compte des **heures de présence** et des
**compteurs**, jamais des montants ni des majorations. C'est aussi la recommandation du conseil :
coder une interprétation du droit du travail, c'est fabriquer la formule qui sera contestée. Les
majorations restent l'affaire du bulletin de paie ; l'outil sert à le confronter, pas à le
remplacer.

### 4.6 Mode prévisionnel — l'usage principal

L'application n'est pas seulement un registre rétrospectif : son usage quotidien est de
**projeter**. L'utilisateur saisit ou complète des horaires à la main pour répondre à deux
questions.

#### « À quelle heure puis-je partir aujourd'hui ? »

```
sortie = retour_de_pause + objectif + pénalité_pause − travail_du_matin

avec  pénalité_pause = max(0, 30 min − durée_de_pause)
      objectif        = 7 h 24 par défaut (§ 3), ajustable
```

La pénalité de pause doit impérativement être intégrée : c'est la correction qu'aucun calcul
mental ne fait spontanément, et elle vaut jusqu'à 30 minutes par jour.

*Vérification.* Sur une journée réelle — matin 2 h 59, pause de 26 minutes — la formule donne
une sortie à 16h42, valeur strictement identique à l'horaire théorique affiché par ADP le même
jour. La formule est donc alignée sur le décompte de l'employeur.

#### « Où j'en suis sur la semaine ? »

Le seuil RTT se jouant sur le total hebdomadaire (§ 4.3), l'application doit projeter la fin de
semaine : temps déjà accompli, temps restant pour atteindre 37 h, heure de sortie cible des jours
restants, et point de bascule au-delà duquel les heures deviennent des heures supplémentaires.
C'est la fonction qu'ADP ne rend pas.

#### Nature d'un pointage

| Nature | Origine | Cycle de vie |
|---|---|---|
| `réel` | collé depuis ADP | immuable, jamais modifié |
| `prévisionnel` | saisi à la main, par anticipation | remplacé par le réel dès qu'il arrive |

Les deux natures ne se mélangent **jamais** dans les totaux officiels. Un pointage prévisionnel
est une hypothèse de travail : il alimente la projection, il est exclu des soldes et des exports,
et il s'efface devant la donnée réelle. C'est ce qui permet de simuler librement sans jamais
altérer la valeur probante du journal.

---

## 5. Défauts du proto — convertis en cas de test

Les défauts qui suivent portent sur l'onglet `Pointage`, seul onglet faisant autorité.

| # | Défaut | Conséquence |
|---|---|---|
| 1 | **Plage inversée dans `CalAbs`** : dès la 2ᵉ ligne de données, le `COUNTBLANK` porte sur la ligne précédente (`AD5:AJ4` au lieu de `AD5:AJ5`). Erreur de recopie. | Le classement journée pleine / demi-journée est faux sur **toute l'année sauf le premier jour**. |
| 2 | **Aucune tolérance au badgeage manquant.** Un seul horodatage absent sur quatre et la soustraction produit `""` : le total de la journée s'effondre sans alerte. | Cause probable d'une partie des écarts constatés. |
| 3 | **Découpage textuel fragile.** `LEFT`/`RIGHT` autour du `h` exigent le format `08h55` exact. `8h5` ou `08:55` cassent la formule silencieusement. | Toute saisie manuelle légèrement différente fausse la journée. |
| 4 | **Pas de gestion du passage à minuit.** Le calcul suppose l'heure de fin postérieure à l'heure de début. | Durée négative sur un poste chevauchant minuit. |
| 5 | **`RepasMax` pointe sur la mauvaise cellule** : la plage nommée désigne « Jours travaillés = 5 », pas une durée. | Vestige mort, bug latent. |
| 6 | **`Demi` pointe sur une cellule vide.** | Toute formule l'utilisant vaut 0. |
| 7 | **Trois écritures pour la même constante** (plage nommée, référence directe, `7` en dur). | Passer de 35 h à 37 h ne se propage pas partout. |
| 8 | **Comparaison `= 0` sur des cellules texte.** Comportement différent selon cellule vide ou chaîne vide. | Branche de calcul imprévisible. |
| 9 | **Dates calculées à rebours** : chaque ligne vaut la suivante moins un jour. | Toute insertion de ligne décale l'année entière. |
| 10 | **Arithmétique décimale sur des heures.** | Erreurs d'arrondi cumulées — d'où la règle **tout en minutes entières** retenue pour l'application. |
| 11 | **Aucune notion de semaine.** Le proto ne sait pas agréger par semaine. | L'acquisition des RTT (§ 4.3) ne peut structurellement pas être juste. |

---

## 6. Cas limites à couvrir par les tests

1. Pause déjeuner inférieure à 30 minutes → décompte de la différence.
2. Pause déjeuner hors de la fenêtre 11h30–14h00 → journée calculée **plus** anomalie signalée.
3. Badgeage manquant (un ou plusieurs des quatre) → journée incomplète tolérée, jamais de plantage.
4. Journée sans aucun badgeage → distinguer week-end, congé posé, et simple oubli.
5. Journée de télétravail sans horodatage → valorisée par l'événement, pas par la présence.
6. Semaine à exactement 35 h, 36 h, 37 h et 39 h → vérifier la répartition RTT / heures supplémentaires.
7. Poste à cheval sur minuit.
8. Semaine à cheval sur deux mois → l'agrégation hebdomadaire ne doit pas être tronquée par le mois.
9. Changement d'heure des 25 octobre et 26 octobre.
10. Jour férié tombant un jour normalement travaillé.

---

## 7. Rétention des données

Question restée ouverte côté utilisateur (3 ans, 5 ans, ou sans limite).

**Recommandation : aucune purge.** Le volume est négligeable — environ 1 500 badgeages par an,
soit quelques centaines de kilo-octets sur dix ans. Il n'existe donc aucune raison technique
d'effacer quoi que ce soit, et une donnée supprimée ne se récupère pas. La prescription
salariale de trois ans fixe la **profondeur utile en cas de litige**, pas une durée maximale de
conservation.

À prévoir en revanche : un **export annuel** lisible sans l'application, déposé hors de la base.

---

## 8. Questions ouvertes

1. **La pointeuse propose-t-elle un export CSV ou Excel natif ?** C'est désormais la seule
   inconnue structurante : elle décide si l'import est automatique ou si la saisie reste
   manuelle (§ 1.2).
2. Le proto distingue `Arrivée` (badgeage à l'entrée du site) et `Matin` (prise de poste). La
   pratique retenue est de ne compter que la prise de poste — à confirmer, car l'écart peut
   dépasser une heure par jour.
3. Comment se comportent les compteurs `Dispo`, `Transfert` et `Boni` ? Ils existent dans le
   proto mais leur alimentation n'est pas documentée.
