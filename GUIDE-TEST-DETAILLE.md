# Guide de Test Détaillé - Phase 1 Inline Editing

**Date:** 2025-12-31
**Demandeur:** GEZAHEGN MOGES EJIGU
**Nationalité:** Éthiopien

---

## 🎯 Objectif du Test

Tester la fonctionnalité d'édition inline (Phase 1) avec un dossier complet de demande de visa pour la Côte d'Ivoire.

---

## 📂 Documents de Test Disponibles

| # | Type | Fichier | Taille | Status |
|---|------|---------|--------|--------|
| 1 | Passeport | `passportpassport-scan.pdf` | 1.2MB | ✅ Disponible |
| 2 | Billet d'avion | `billetelectronic-ticket...pdf` | 133KB | ✅ Disponible |
| 3 | Réservation hôtel | `hotelgmail-thanks-your-booking...pdf` | 426KB | ✅ Disponible |
| 4 | Carnet vaccinal | `vaccinationyellow-faver-certificate...pdf` | 274KB | ✅ Disponible |
| 5 | Photo d'identité | `gezahegn-moges...passport-photo.jpg` | 34KB | ✅ Disponible |
| 6 | Lettre d'invitation | `ordremissioninvitation-letter...pdf` | 314KB | ✅ Disponible |

**Chemin complet:**
```
/Users/cheickmouhamedelhadykane/Downloads/test/
```

---

## 📋 Données Attendues par Document

### 1️⃣ PASSEPORT (EQ1799898)

**Données à extraire:**
```
┌─────────────────────────────────────────────┐
│ Type de passeport: P (Ordinaire)            │
│ Pays émetteur: Ethiopia (ETH)               │
│ N° Passeport: EQ1799898                     │
│ Nom: EJIGU                                  │
│ Prénoms: GEZAHEGN MOGES                     │
│ Nationalité: ETHIOPIAN                      │
│ Sexe: M                                     │
│ Date de naissance: 22/08/1995               │
│ Lieu de naissance: SHASHEMENE               │
│ Date d'émission: 07/09/2025                 │
│ Date d'expiration: 16/09/2030                │
│ Autorité: MAIN DEPARTMENT FOR IMMIGRATION   │
│ MRZ: P<ETHEJIG U<<GEZAHEGN<MOGES...        │
└─────────────────────────────────────────────┘
```

**Points de validation:**
- ✅ Nom de famille peut apparaître comme "EJIGU" ou "MOGES" selon l'ordre
- ✅ Le passeport est valide (expire en 2030)
- ✅ Type "P" = Passeport ordinaire → Workflow STANDARD

### 2️⃣ BILLET D'AVION (Ethiopian Airlines)

**Données à extraire:**
```
┌─────────────────────────────────────────────┐
│ Compagnie aérienne: Ethiopian Airlines      │
│ N° de billet: 0712157308494                 │
│ Nom du passager: EJIGU/GEZAHEGN MOGES MR   │
│ Référence de réservation: KTKPJV            │
│ Date d'émission: 19/12/2025                 │
│                                             │
│ VOL ALLER:                                  │
│ N° vol: ET 935                              │
│ Départ: ADDIS ABABA (ADD) Terminal 2        │
│ Date départ: 28/12/2025 à 10:30             │
│ Arrivée: ABIDJAN (ABJ)                      │
│ Date arrivée: 28/12/2025 à 13:45            │
│ Classe: Economy (N)                         │
│ Bagages: 2 pièces                           │
│                                             │
│ VOL RETOUR:                                 │
│ N° vol: ET 513                              │
│ Départ: ABIDJAN (ABJ)                       │
│ Date départ: 25/01/2026 à 12:30             │
│ Arrivée: ADDIS ABABA (ADD) Terminal 2       │
│ Date arrivée: 25/01/2026 à 21:35            │
│ Classe: Economy (N)                         │
│ Bagages: 2 pièces                           │
└─────────────────────────────────────────────┘
```

**Points de validation:**
- ✅ Nom passager cohérent avec passeport
- ✅ Date départ (28/12) cohérente avec check-in hôtel (28/12)
- ✅ Billet aller-retour présent

### 3️⃣ RÉSERVATION HÔTEL (Booking.com)

**Données à extraire:**
```
┌─────────────────────────────────────────────┐
│ Nom de l'hôtel: Appartement 1 à 3 pièces   │
│                 Equipé Cosy Calme - Aigle   │
│ Plateforme: Booking.com                     │
│ N° de confirmation: 5628305412              │
│ PIN: 0485                                   │
│ Nom du client: Gezahegn Moges               │
│ Date d'arrivée: 28/12/2025 (14:00-23:00)    │
│ Date de départ: 29/12/2025 (08:00-12:00)    │
│ Durée: 1 nuit                               │
│ Nombre de personnes: 2 adultes              │
│ Adresse: Résidence Belle Plume              │
│ Ville: Yamoussoukro                         │
│ Pays: Côte d'Ivoire                         │
│ Téléphone: +33760651382                     │
│ Prix total: XOF 22,000                      │
└─────────────────────────────────────────────┘
```

**Points de validation:**
- ✅ Nom client cohérent avec passeport/billet
- ✅ Date check-in (28/12) = Date arrivée vol
- ✅ Adresse en Côte d'Ivoire confirmée

### 4️⃣ CARNET VACCINAL (Fièvre jaune)

**Données à extraire:**
```
┌─────────────────────────────────────────────┐
│ Type: Yellow Fever Certificate              │
│ Nom: GEZAHEGN MOGES                         │
│ Vaccin: Fièvre jaune (Yellow Fever)         │
│ [Détails à vérifier lors du test]           │
└─────────────────────────────────────────────┘
```

**Points de validation:**
- ✅ Nom cohérent avec autres documents
- ✅ Vaccin fièvre jaune obligatoire pour CI

### 5️⃣ PHOTO D'IDENTITÉ

**Caractéristiques:**
- Format: JPG
- Fond: Blanc/gris clair
- Qualité: Bonne (34KB)
- Personne: Homme, chemise bleue

### 6️⃣ LETTRE D'INVITATION

**Données à extraire:**
```
┌─────────────────────────────────────────────┐
│ Destinataire: GEZAHEGN MOGES EJIGU          │
│ Type: Ordre de mission / Invitation         │
│ [Détails à vérifier lors du test]           │
└─────────────────────────────────────────────┘
```

---

## 🧪 Séquence de Test Pas à Pas

### Préparation
1. Ouvrir Chrome/Safari
2. Naviguer vers: `http://localhost:8888/hunyuanocr/visa-chatbot/index.php`
3. Ouvrir DevTools (F12) → Onglet Console
4. Vérifier: `[InlineEditing] InlineEditingManager initialized`
5. Préparer fichier `TEST-RESULTS.md` pour enregistrer les résultats

---

### TEST 1: Upload Passeport + Confirmation Inline ✅

**Objectif:** Vérifier l'affichage inline des données extraites

**Étapes:**
1. Démarrer le chatbot (cliquer "Commencer")
2. Suivre le workflow jusqu'à l'étape "Passeport"
3. Cliquer sur le bouton d'upload
4. Sélectionner: `/Users/.../test/passportpassport-scan.pdf`
5. Attendre l'extraction OCR (10-30 secondes)

**Résultat Attendu:**
```
✅ Passeport lu avec succès !

┌─────────────────────────────────────┐
│ Nom:               EJIGU            │
│ Prénoms:           GEZAHEGN MOGES   │
│ N° Passeport:      EQ1799898        │
│ Date de naissance: 22/08/1995       │
│ Nationalité:       ETHIOPIAN        │
│ Date d'expiration: 16/09/2030       │
│ Sexe:              M                │
│ Type de passeport: P                │
│ Pays émetteur:     ETH              │
└─────────────────────────────────────┘

Ces informations sont-elles correctes ?

[Oui, c'est correct]  [Non, modifier]
```

**Vérifications:**
- [ ] Message "✅ Passeport lu avec succès !" affiché
- [ ] Données structurées visibles
- [ ] Tous les champs critiques remplis
- [ ] Aucun champ avec ⚠️ (si OCR bon)
- [ ] Bouton vert "Oui, c'est correct" présent
- [ ] Bouton gris "Non, modifier" présent
- [ ] Boutons ont le bon style (gradient, ombre)
- [ ] Hover effect fonctionne
- [ ] Console: `[InlineEditing] Data confirmed by user` (après clic)

**Enregistrer:**
- Screenshot de l'affichage inline
- Toutes les valeurs extraites
- Temps d'extraction: _____ secondes

---

### TEST 2: Flux "Oui, c'est correct" ✅

**Objectif:** Vérifier la confirmation et passage à l'étape suivante

**Étapes:**
1. (Suite du TEST 1)
2. Cliquer sur le bouton vert "Oui, c'est correct"

**Résultat Attendu:**
```
Utilisateur: ✓ Données confirmées

[Les boutons disparaissent]

[Le chatbot passe à l'étape suivante]
```

**Vérifications:**
- [ ] Message utilisateur "✓ Données confirmées" apparaît
- [ ] Boutons disparaissent
- [ ] Workflow continue (étape suivante)
- [ ] Pas d'erreur console
- [ ] Console: `[InlineEditing] Data confirmed`

---

### TEST 3: Upload Passeport + Flux "Non, modifier" ✏️

**Objectif:** Vérifier l'ouverture du modal d'édition

**Étapes:**
1. Rafraîchir la page (F5)
2. Recommencer le workflow
3. Upload passeport à nouveau
4. Attendre affichage inline
5. Cliquer sur le bouton gris "Non, modifier"

**Résultat Attendu:**
```
Utilisateur: ✏️ Modifier les données

[Les boutons disparaissent]

[MODAL D'ÉDITION S'OUVRE]
┌────────────────────────────────────┐
│ ✏️ Modifier les informations       │
│                                    │
│ Nom:              [EJIGU      ] ✓  │
│ Prénoms:          [GEZAHEGN...] ✓  │
│ N° Passeport:     [EQ1799898  ] ✓  │
│ Date naissance:   [22/08/1995 ] ✓  │
│ etc...                             │
│                                    │
│ [Annuler]  [Valider]               │
└────────────────────────────────────┘
```

**Vérifications:**
- [ ] Message "✏️ Modifier les données" apparaît
- [ ] Boutons inline disparaissent
- [ ] Modal s'ouvre (effet glassmorphism)
- [ ] Tous les champs pré-remplis avec valeurs extraites
- [ ] Champs sont éditables
- [ ] Bouton "Annuler" présent
- [ ] Bouton "Valider" présent
- [ ] Console: `[InlineEditing] Edit requested`

**Enregistrer:**
- Screenshot du modal ouvert
- Style glassmorphism présent (oui/non)

---

### TEST 4: Édition et Validation ✏️

**Objectif:** Vérifier l'édition et sauvegarde des données

**Étapes:**
1. (Suite du TEST 3 - modal ouvert)
2. Modifier un champ (ex: changer "EJIGU" en "EJIGU MODIFIED")
3. Cliquer "Valider"

**Résultat Attendu:**
```
[Modal se ferme]

[Workflow continue avec données modifiées]
```

**Vérifications:**
- [ ] Modal se ferme
- [ ] Données modifiées sauvegardées
- [ ] Workflow continue
- [ ] Pas d'erreur console

**Enregistrer:**
- Champ modifié: ___________
- Valeur avant: ___________
- Valeur après: ___________

---

### TEST 5: Édition et Annulation ❌

**Objectif:** Vérifier l'annulation et retour aux boutons

**Étapes:**
1. Rafraîchir, recommencer workflow
2. Upload passeport
3. Cliquer "Non, modifier"
4. Modifier un champ (optionnel)
5. Cliquer "Annuler"

**Résultat Attendu:**
```
[Modal se ferme]

[Boutons de confirmation réapparaissent]
[Oui, c'est correct]  [Non, modifier]
```

**Vérifications:**
- [ ] Modal se ferme
- [ ] Boutons inline réapparaissent
- [ ] Données non modifiées
- [ ] Aucune erreur

---

### TEST 6: Upload Billet d'Avion ✈️

**Objectif:** Tester inline editing avec un autre type de document

**Étapes:**
1. Continuer le workflow (ou recommencer si nécessaire)
2. Arriver à l'étape "Billet d'avion"
3. Upload: `billetelectronic-ticket-receipt...pdf`
4. Attendre extraction

**Résultat Attendu:**
```
✅ Billet d'avion lu avec succès !

┌─────────────────────────────────────────┐
│ Compagnie aérienne:  Ethiopian Airlines │
│ N° de vol:           ET 935             │
│ Date de départ:      28/12/2025         │
│ Aéroport de départ:  ADDIS ABABA (ADD)  │
│ Aéroport d'arrivée:  ABIDJAN (ABJ)      │
│ Nom du passager:     EJIGU/GEZAHEGN...  │
│ Référence:           KTKPJV             │
└─────────────────────────────────────────┘

Ces informations sont-elles correctes ?

[Oui, c'est correct]  [Non, modifier]
```

**Vérifications:**
- [ ] Message "✅ Billet d'avion lu avec succès !"
- [ ] Données du billet affichées
- [ ] Boutons présents
- [ ] Nom passager cohérent avec passeport

**Enregistrer:**
- Toutes les valeurs extraites
- Cohérence nom: Oui/Non

---

### TEST 7: Upload Réservation Hôtel 🏨

**Objectif:** Tester extraction hôtel

**Étapes:**
1. Continuer à l'étape "Hôtel"
2. Upload: `hotelgmail-thanks-your-booking...pdf`
3. Attendre extraction

**Résultat Attendu:**
```
✅ Réservation d'hôtel lue avec succès !

┌─────────────────────────────────────────┐
│ Nom de l'hôtel:     Appartement 1...    │
│ Date d'arrivée:     28/12/2025          │
│ Date de départ:     29/12/2025          │
│ Nom du client:      Gezahegn Moges      │
│ N° de confirmation: 5628305412          │
│ Adresse:            Yamoussoukro, CI     │
└─────────────────────────────────────────┘

Ces informations sont-elles correctes ?

[Oui, c'est correct]  [Non, modifier]
```

**Vérifications:**
- [ ] Message "✅ Réservation d'hôtel lue avec succès !"
- [ ] Données hôtel affichées
- [ ] Date check-in = Date arrivée vol (28/12)
- [ ] Nom client cohérent

**Enregistrer:**
- Cohérence dates: Oui/Non
- Cohérence nom: Oui/Non

---

### TEST 8: Upload Carnet Vaccinal 💉

**Objectif:** Tester extraction vaccination

**Étapes:**
1. Continuer à l'étape "Vaccination"
2. Upload: `vaccinationyellow-faver-certificate...pdf`
3. Attendre extraction

**Résultat Attendu:**
```
✅ Carnet vaccinal lu avec succès !

[Données vaccination affichées]

[Boutons de confirmation]
```

**Vérifications:**
- [ ] Message succès affiché
- [ ] Données vaccination extraites
- [ ] Nom cohérent
- [ ] Boutons présents

---

### TEST 9: Validation Cross-Document 🔍

**Objectif:** Vérifier la cohérence entre documents

**Étapes:**
1. Après avoir uploadé tous les documents
2. Observer si un rapport de cohérence apparaît

**Points de Cohérence à Vérifier:**
- Nom sur passeport: EJIGU GEZAHEGN MOGES
- Nom sur billet: EJIGU/GEZAHEGN MOGES
- Nom sur hôtel: Gezahegn Moges
- Nom sur vaccination: [à vérifier]

- Date arrivée vol: 28/12/2025 10:30
- Date check-in hôtel: 28/12/2025 14:00-23:00
- ✅ Cohérence: OUI

**Vérifications:**
- [ ] Rapport de cohérence apparaît
- [ ] Aucune incohérence détectée
- [ ] OU incohérences listées si détectées

**Enregistrer:**
- Cohérence globale: Oui/Non
- Incohérences détectées: _________

---

### TEST 10: Dark Mode 🌙

**Objectif:** Vérifier compatibilité mode sombre

**Étapes:**
1. Activer le dark mode (toggle UI ou navigateur)
2. Upload un document
3. Observer l'affichage inline

**Vérifications:**
- [ ] Texte lisible en dark mode
- [ ] Boutons visibles avec bon contraste
- [ ] Container données a fond sombre
- [ ] Pas de texte blanc sur fond blanc
- [ ] Aucun bug visuel

**Enregistrer:**
- Screenshot dark mode
- Problèmes visuels: _________

---

### TEST 11: Responsive Mobile 📱

**Objectif:** Vérifier layout mobile

**Étapes:**
1. Redimensionner navigateur à 375px largeur
2. OU tester sur appareil mobile réel
3. Upload un document
4. Observer l'affichage

**Vérifications:**
- [ ] Boutons empilés verticalement
- [ ] Champs empilés (label au-dessus de valeur)
- [ ] Pas de scroll horizontal
- [ ] Boutons suffisamment grands (touch-friendly)
- [ ] Modal adapté à l'écran

**Enregistrer:**
- Device testé: _________
- Largeur: _____ px
- Screenshot mobile
- Problèmes: _________

---

## 📊 Tableau Récapitulatif des Tests

| # | Test | Status | Temps | Problèmes |
|---|------|--------|-------|-----------|
| 1 | Passport inline | ⏳ | ___ s | _______ |
| 2 | Flux "Oui" | ⏳ | ___ s | _______ |
| 3 | Flux "Non" modal | ⏳ | ___ s | _______ |
| 4 | Edit + Valider | ⏳ | ___ s | _______ |
| 5 | Edit + Annuler | ⏳ | ___ s | _______ |
| 6 | Flight ticket | ⏳ | ___ s | _______ |
| 7 | Hotel | ⏳ | ___ s | _______ |
| 8 | Vaccination | ⏳ | ___ s | _______ |
| 9 | Cross-validation | ⏳ | ___ s | _______ |
| 10 | Dark mode | ⏳ | ___ s | _______ |
| 11 | Mobile | ⏳ | ___ s | _______ |

**Légende Status:**
- ⏳ En attente
- 🔄 En cours
- ✅ Passé
- ⚠️ Passé avec remarques
- ❌ Échoué

---

## 🐛 Section Bugs Découverts

### Bug #1
**Titre:** ___________
**Gravité:** Critique / Haute / Moyenne / Basse
**Description:** ___________
**Reproduction:**
1. ___________
2. ___________

**Capture d'écran:** ___________

---

## 📝 Notes & Observations

```
[Espace libre pour notes durant les tests]






```

---

## ✅ Conclusion

**Taux de réussite:** ___ / 11 tests

**Recommandation:**
- [ ] ✅ Prêt pour production
- [ ] ⚠️ Corrections mineures nécessaires
- [ ] ❌ Corrections majeures requises

**Prochaines étapes:**
- [ ] Corriger les bugs critiques
- [ ] Passer à Phase 2 (Glassmorphism UI)
- [ ] Tests supplémentaires nécessaires

**Testeur:** ___________________
**Date:** ___________________
**Signature:** ___________________
