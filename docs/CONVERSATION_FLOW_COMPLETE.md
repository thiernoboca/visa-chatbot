# CONVERSATION FLOW SEQUENCE - Version Complète

## Vue d'Ensemble

Le flux conversationnel est **ADAPTATIF** - les étapes affichées dépendent de:
- Type de passeport (ORDINAIRE, DIPLOMATIQUE, SERVICE, LP_ONU, LP_UA)
- Nationalité du demandeur
- Âge (majeur/mineur)
- Type de visa demandé

---

## PHASE 1: IDENTIFICATION (Étapes 0-4)

### Étape 0: WELCOME
```
Bot: "Bienvenue sur le portail e-Visa Côte d'Ivoire..."
     [Démarrer] [Reprendre une demande]
```

### Étape 1: PASSPORT (Document Critique)
```
Bot: "Veuillez scanner votre passeport..."
     [Upload] [Webcam] [QR Mobile]

→ OCR Triple Couche:
  1. Google Vision → MRZ extraction
  2. Gemini Flash → Parsing intelligent
  3. Claude Sonnet → Supervision qualité (async)

→ Extraction:
  - Type passeport → Détermine workflow (STANDARD/PRIORITY)
  - Nationalité → Détermine documents requis
  - Date naissance → Détection mineur
  - Date expiration → Validation 6 mois minimum
```

**Branchement après Passeport:**
```
┌─────────────────────────────────────────────────────────────┐
│                    TYPE DE PASSEPORT                         │
├──────────────┬──────────────┬──────────────┬────────────────┤
│  ORDINAIRE   │ DIPLOMATIQUE │   SERVICE    │   LP_ONU/UA    │
├──────────────┼──────────────┼──────────────┼────────────────┤
│ Flux STANDARD│ Flux PRIORITY│ Flux PRIORITY│ Flux PRIORITY  │
│ 15-19 étapes │ 8-10 étapes  │ 10-12 étapes │ 8-10 étapes    │
│              │              │              │                │
│ Documents:   │ Documents:   │ Documents:   │ Documents:     │
│ - Ticket ✓   │ - Note Verb. │ - Note Verb. │ - Note Verb.   │
│ - Hotel ✓    │ - Invitation │ - Invitation │ - Lettre LP    │
│ - Vaccination│ (optionnel)  │              │                │
│ - Photo ✓    │ - Photo ✓    │ - Photo ✓    │ - Photo ✓      │
└──────────────┴──────────────┴──────────────┴────────────────┘
```

### Étape 2: RESIDENCE (Conditionnel)
**Condition:** `nationality !== 'CIV' && residenceRequired`
```
Bot: "Quel est votre pays de résidence actuel?"
     [Liste pays] ou [Je réside dans mon pays de nationalité]
```

### Étape 3: RESIDENCE_CARD (Conditionnel)
**Condition:** `residence !== nationality`
```
Bot: "Veuillez fournir votre titre de séjour..."
     [Upload] [Je n'en ai pas]

→ Validation:
  - Pays émetteur = Pays de résidence déclaré
  - Date validité > date voyage
```

---

## PHASE 2: DOCUMENTS DE VOYAGE (Étapes 4-8)

### Étape 4: TICKET (Billet d'avion)
**Condition:** `passportType === 'ORDINAIRE'`
```
Bot: "Veuillez fournir votre billet d'avion ou réservation..."
     [Upload billet] [Je n'ai pas encore de billet]

→ Extraction:
  - Nom passager → Cross-validation avec passeport
  - Dates voyage → Calcul durée séjour
  - Aéroport arrivée → Validation Côte d'Ivoire
  - Compagnie aérienne

→ Validation croisée:
  - Nom billet = Nom passeport (tolérance Levenshtein < 2)
  - Date arrivée avant expiration passeport
```

**Si pas de billet:**
```
Bot: "Pas de problème. Quelles sont vos dates de voyage prévues?"
     [Date arrivée] [Date départ]

Note: Le visa sera conditionnel à la fourniture du billet final.
```

### Étape 5: HOTEL / ACCOMMODATION
**Condition:** `passportType === 'ORDINAIRE' && !hasInvitation`
```
Bot: "Où séjournerez-vous en Côte d'Ivoire?"
     [Réservation hôtel] [Hébergé par un proche] [Autre]
```

**Branche A - Hôtel:**
```
Bot: "Veuillez fournir votre confirmation de réservation..."
     [Upload réservation]

→ Extraction:
  - Nom hôtel
  - Adresse complète
  - Dates séjour → Cohérence avec billet
  - Nom réservation → Cross-validation
```

**Branche B - Hébergement privé:**
```
Bot: "Veuillez fournir une lettre d'invitation de votre hôte..."
     → Redirige vers étape INVITATION
```

### Étape 6: VACCINATION
**Condition:** `requiresYellowFever(nationality, transitCountries)`
```
Bot: "La vaccination Fièvre Jaune est requise. Veuillez fournir votre certificat..."
     [Upload certificat] [J'ai une exemption médicale]

→ Extraction:
  - Nom vacciné → Cross-validation passeport
  - Date vaccination → Valide si > 10 jours avant voyage
  - Type vaccin → OMS approved list
  - Centre vaccination → Validité

→ Règles métier:
  - Vaccination valide à vie depuis 2016 (OMS)
  - Exemption médicale = Document additionnel requis
```

**Pays exemptés (pas de fièvre jaune):**
- Europe, Amérique du Nord, Australie, Nouvelle-Zélande
- Japon, Corée du Sud, Singapour

### Étape 7: INVITATION (Conditionnel)
**Condition:** `hasPrivateHost || businessVisa || passportType === 'DIPLOMATIQUE'`
```
Bot: "Veuillez fournir la lettre d'invitation..."
     [Upload lettre]

→ Extraction:
  - Nom invitant
  - Adresse en Côte d'Ivoire
  - Relation avec demandeur
  - Période invitation

→ Pour DIPLOMATIQUE:
  - Lettre doit provenir d'une institution officielle
  - Validation signature/tampon
```

### Étape 8: VERBAL_NOTE (Diplomatiques uniquement)
**Condition:** `passportType in ['DIPLOMATIQUE', 'SERVICE', 'LP_ONU', 'LP_UA']`
```
Bot: "Veuillez fournir la Note Verbale de votre Ministère..."
     [Upload Note Verbale]

→ Validation:
  - Émise par MAE ou organisme international
  - Référence officielle
  - Date récente (< 3 mois)
  - Destinée à l'Ambassade CI
```

---

## PHASE 3: VÉRIFICATION ÉLIGIBILITÉ (Étape 9)

### Étape 9: ELIGIBILITY (Checkpoint Critique)
```
Bot: "Je vérifie votre éligibilité..."
     [Animation processing]
```

**Validations effectuées:**
```
┌────────────────────────────────────────────────────────────┐
│                 CONTRÔLES D'ÉLIGIBILITÉ                     │
├────────────────────────────────────────────────────────────┤
│ 1. CROSS-DOCUMENT VALIDATION                               │
│    ├─ Cohérence noms (passeport ↔ tous documents)         │
│    ├─ Cohérence dates (voyage ↔ validité documents)       │
│    └─ Cohérence nationalité (passeport ↔ déclarations)    │
│                                                            │
│ 2. COMPLETENESS CHECK (Requirements Matrix)                │
│    ├─ Tous documents requis présents                       │
│    ├─ Tous champs obligatoires remplis                     │
│    └─ Qualité OCR suffisante (confidence > 0.85)          │
│                                                            │
│ 3. BUSINESS RULES                                          │
│    ├─ Passeport expire > 6 mois après retour              │
│    ├─ Durée séjour ≤ 90 jours (visa touristique)          │
│    ├─ Pas de restriction pays (blacklist)                  │
│    └─ Vaccination valide si requise                        │
│                                                            │
│ 4. MINOR DETECTION                                         │
│    └─ Si âge < 18 → Déclenche flux parental               │
└────────────────────────────────────────────────────────────┘
```

**Résultats possibles:**

**A. ÉLIGIBLE:**
```
Bot: "Excellente nouvelle! Vous êtes éligible au visa. Continuons..."
     → Passe à PHASE 4
```

**B. DOCUMENTS MANQUANTS:**
```
Bot: "Il manque les documents suivants:"
     - [x] Certificat de vaccination
     - [x] Réservation hôtel

     [Ajouter documents] [Sauvegarder et continuer plus tard]
```

**C. INCOHÉRENCE DÉTECTÉE:**
```
Bot: "J'ai détecté une incohérence:"
     "Le nom sur votre billet (KANE MOUHAMAD) diffère de votre passeport (KANE MOUHAMADOU)"

     [C'est correct, juste une variante] [Corriger le document]
```

**D. MINEUR DÉTECTÉ:**
```
Bot: "Je vois que vous avez moins de 18 ans. Des documents supplémentaires sont requis:"
     - Autorisation parentale signée
     - Copie pièce d'identité parent/tuteur

     [Continuer avec documents parentaux]
```

---

## PHASE 4: PHOTO & INFORMATIONS (Étapes 10-14)

### Étape 10: PHOTO (Obligatoire)
```
Bot: "Nous avons besoin de votre photo d'identité biométrique..."
     [Prendre photo] [Upload photo existante] [QR Code mobile]

→ Validation Innovatrics:
  - Détection visage
  - Qualité biométrique
  - Fond neutre
  - Éclairage correct
  - Format 35x45mm

→ Comparaison faciale:
  - Photo vs photo passeport (si disponible)
  - Score similarité > 0.75
```

### Étape 11: CONTACT (Informations personnelles)
```
Bot: "Quelques informations de contact..."

Formulaire:
- Email (validation format)
- Téléphone (avec indicatif pays)
- Adresse complète pays résidence
```

### Étape 12: TRIP (Détails du voyage)
```
Bot: "Précisons les détails de votre voyage..."

Questions:
- Motif voyage [Tourisme] [Affaires] [Famille] [Transit] [Autre]
- Première visite CI? [Oui] [Non]
- Voyagez-vous seul? [Oui] [Non, en groupe]
```

**Si voyage en groupe:**
```
Bot: "Combien de personnes voyagent avec vous?"
     [Nombre] + [Noms des accompagnants]
```

### Étape 13: HEALTH (Déclaration santé)
```
Bot: "Quelques questions de santé..."

Questions:
- Maladies transmissibles? [Non] [Oui, préciser]
- Traitement médical en cours? [Non] [Oui]
```

### Étape 14: CUSTOMS (Déclaration douanière)
```
Bot: "Déclaration douanière..."

Questions:
- Devises > 10,000 EUR équivalent? [Non] [Oui, montant]
- Biens à déclarer? [Non] [Oui, détails]
```

---

## PHASE 5: PAIEMENT & FINALISATION (Étapes 15-18)

### Étape 15: PAYMENT
```
Bot: "Passons au paiement des frais de visa..."

Affichage:
┌────────────────────────────────────────┐
│ RÉCAPITULATIF FRAIS                    │
├────────────────────────────────────────┤
│ Visa touristique (30 jours)    70 EUR  │
│ Frais de service               15 EUR  │
├────────────────────────────────────────┤
│ TOTAL                          85 EUR  │
└────────────────────────────────────────┘

     [Payer par carte] [Payer par Mobile Money]
```

**Après paiement:**
```
Bot: "Paiement reçu! Référence: PAY-2024-XXXXX"
     [Reçu envoyé par email]
```

### Étape 16: CONFIRM (Récapitulatif final)
```
Bot: "Voici le récapitulatif de votre demande..."

┌────────────────────────────────────────────────────────────┐
│ DEMANDE DE VISA - RÉCAPITULATIF                            │
├────────────────────────────────────────────────────────────┤
│ Demandeur: KANE Mouhamadou El Hady                         │
│ Passeport: AB1234567 (expire: 15/03/2028)                  │
│ Nationalité: Sénégalaise                                   │
│ Type visa: Touristique - 30 jours                          │
│ Dates: 15/02/2025 → 28/02/2025                            │
│                                                            │
│ Documents fournis:                                         │
│ ✓ Passeport                                                │
│ ✓ Billet d'avion                                          │
│ ✓ Réservation hôtel                                       │
│ ✓ Certificat vaccination                                   │
│ ✓ Photo biométrique                                        │
│                                                            │
│ Paiement: 85 EUR - CONFIRMÉ                                │
└────────────────────────────────────────────────────────────┘

     [Modifier] [Confirmer et soumettre]
```

### Étape 17: SIGNATURE (Validation juridique)
```
Bot: "Veuillez signer électroniquement votre demande..."

Déclaration:
"Je certifie que les informations fournies sont exactes et
complètes. Je comprends qu'une fausse déclaration peut
entraîner le refus ou l'annulation de mon visa."

     [Signature tactile/souris]
     [✓] J'accepte les conditions générales

     [Soumettre la demande]
```

### Étape 18: COMPLETION
```
Bot: "Félicitations! Votre demande a été soumise avec succès!"

┌────────────────────────────────────────────────────────────┐
│ CONFIRMATION DE SOUMISSION                                  │
├────────────────────────────────────────────────────────────┤
│ Numéro de dossier: VISA-CI-2024-123456                     │
│ Date soumission: 27/12/2024 à 14:32                        │
│ Délai traitement estimé: 3-5 jours ouvrables               │
│                                                            │
│ Prochaines étapes:                                         │
│ 1. Vérification par nos services                           │
│ 2. Notification par email du statut                        │
│ 3. Téléchargement du visa électronique si approuvé         │
└────────────────────────────────────────────────────────────┘

     [Télécharger récépissé] [Suivre ma demande]
```

---

## FLUX SPÉCIAUX

### A. Flux MINEUR (âge < 18)

Insertion après étape ELIGIBILITY:
```
Étape MINOR_AUTH:
Bot: "Pour les demandeurs mineurs, des documents supplémentaires sont requis..."

Documents requis:
1. Autorisation parentale de sortie du territoire
2. Copie pièce d'identité du parent/tuteur légal
3. Si un seul parent: attestation de garde exclusive ou décès

     [Upload autorisation] [Upload pièce parent]
```

### B. Flux TRANSIT

Si `tripPurpose === 'TRANSIT'`:
```
Étape TRANSIT_INFO:
Bot: "Pour un transit, j'ai besoin d'informations supplémentaires..."

Questions:
- Destination finale?
- Durée du transit? (max 72h pour visa transit)
- Billet vers destination finale?

     [Upload billet continuation]
```

### C. Flux RÉCUPÉRATION

Si session précédente détectée:
```
Bot: "Bienvenue! Je vois que vous avez une demande en cours..."

Affichage progression:
━━━━━━━━━━●━━━━━━━━━━ 60%
Étape actuelle: Hotel

     [Reprendre] [Recommencer une nouvelle demande]
```

### D. Flux ERREUR OCR

Si confidence OCR < 0.70:
```
Bot: "J'ai du mal à lire ce document. Cela peut être dû à:"
     - Image floue
     - Mauvais éclairage
     - Document plié ou endommagé

     [Reprendre la photo] [Saisir manuellement]
```

---

## MATRICE DES EXIGENCES PAR TYPE DE PASSEPORT

```
┌─────────────────┬───────┬───────┬───────┬───────┬───────┐
│ Document        │ORD    │DIPLO  │SERV   │LP_ONU │LP_UA  │
├─────────────────┼───────┼───────┼───────┼───────┼───────┤
│ Passeport       │ ✓     │ ✓     │ ✓     │ ✓     │ ✓     │
│ Photo           │ ✓     │ ✓     │ ✓     │ ✓     │ ✓     │
│ Billet avion    │ ✓     │ ○     │ ○     │ ○     │ ○     │
│ Hôtel/Héberg.   │ ✓     │ ○     │ ○     │ ○     │ ○     │
│ Vaccination     │ ✓*    │ ✓*    │ ✓*    │ ✓*    │ ✓*    │
│ Note Verbale    │ ✗     │ ✓     │ ✓     │ ✓     │ ✓     │
│ Invitation      │ ○     │ ○     │ ○     │ ○     │ ○     │
│ Titre séjour    │ ○     │ ○     │ ○     │ ○     │ ○     │
│ Auth. parentale │ ○**   │ ○**   │ ○**   │ ○**   │ ○**   │
├─────────────────┴───────┴───────┴───────┴───────┴───────┤
│ ✓ = Obligatoire  ○ = Conditionnel  ✗ = Non requis      │
│ * = Selon nationalité  ** = Si mineur                   │
└─────────────────────────────────────────────────────────┘
```

---

## VALIDATION CROISÉE DES DOCUMENTS

À chaque upload de document, le système effectue:

```
┌─────────────────────────────────────────────────────────────┐
│              CROSS-DOCUMENT VALIDATION ENGINE               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  PASSPORT (source de vérité)                                │
│     │                                                       │
│     ├── TICKET ───────────────────────────────────────────│
│     │   └─ Nom passager = Nom passeport (Levenshtein < 2) │
│     │   └─ Date voyage < Date expiration passeport        │
│     │                                                       │
│     ├── HOTEL ────────────────────────────────────────────│
│     │   └─ Nom réservation = Nom passeport                │
│     │   └─ Dates séjour ⊆ Dates billet                    │
│     │                                                       │
│     ├── VACCINATION ──────────────────────────────────────│
│     │   └─ Nom vacciné = Nom passeport                    │
│     │   └─ Date vaccination ≤ Date voyage - 10 jours      │
│     │                                                       │
│     ├── INVITATION ───────────────────────────────────────│
│     │   └─ Nom invité = Nom passeport                     │
│     │   └─ Période invitation ⊇ Dates voyage              │
│     │                                                       │
│     └── PHOTO ────────────────────────────────────────────│
│         └─ Face matching avec photo passeport (> 0.75)    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## GESTION DES ERREURS

### Erreur de Validation
```
Bot: "J'ai détecté un problème avec [DOCUMENT]:"
     [Message d'erreur spécifique]

     [Corriger] [Remplacer le document] [Contacter le support]
```

### Timeout de Session
```
Bot: "Votre session a expiré par mesure de sécurité."
     "Vos documents ont été sauvegardés."

     [Reprendre avec code de récupération]
```

### Erreur Technique
```
Bot: "Une erreur technique est survenue."
     "Référence: ERR-2024-XXXXX"

     [Réessayer] [Contacter le support]

Support: visa-support@ambassadeci.org
```

---

## MÉTRIQUES UX RECOMMANDÉES

| Métrique | Objectif |
|----------|----------|
| Temps moyen completion | < 12 minutes |
| Taux abandon | < 25% |
| Taux erreur OCR | < 5% |
| Taux première soumission réussie | > 80% |
| Score satisfaction utilisateur | > 4.2/5 |

---

## CHANGELOG

- v2.0 (2024-12-27): Flux complet avec 19 étapes, validation croisée, flux spéciaux
- v1.0 (2024-xx-xx): Flux initial 11 étapes
