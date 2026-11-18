# Cahier des charges — plateforme de gestion scolaire multi-établissements

Version de travail. Statuts : **Fait** (développé et testé côté serveur), **Partiel**, **À faire**, **Bloqué** (dépend d'un accès externe).

## 1. Objet

Plateforme SaaS de gestion scolaire pour écoles africaines francophones : élèves, inscriptions, appels et pointage, notes et bulletins, frais, espace parent. Un super administrateur gère les établissements et les abonnements.

## 2. Formules d'abonnement

Source : `backend/config/plans.php`.

| Fonctionnalité | Standard | VIP | Entreprise |
|---|:-:|:-:|:-:|
| Élèves, classes, inscriptions, enseignants, matières, emploi du temps | oui | oui | oui |
| Présences, notes, bulletins, palmarès, frais, reçus | oui | oui | oui |
| Messages, dossiers d'inscription, calendrier, relances | oui | oui | oui |
| Notifications parents (absences, convocations, sorties, rendez-vous) | oui | oui | oui |
| Fiche de suivi | oui | oui | oui |
| Conseil de classe, export comptable, alertes, journal | — | oui | oui |
| Espace parent, cahier de texte, fil d'actualité, planning des examens | — | oui | oui |
| Bulletin personnalisé (signataire, mention, couleur) | — | oui | oui |
| Permissions personnalisées par utilisateur | — | oui | oui |
| Personnalisation de la couleur de l'espace | — | — | oui |
| Utilisateurs illimités, API, multi-sites, archivage | — | — | oui |
| **Nombre d'utilisateurs** | 10 | 25 | illimité |
| **Prix mensuel** | 25 000 FCFA | 50 000 FCFA | 100 000 FCFA |

Contrôle : chaque fonctionnalité réservée est vérifiée côté serveur (middleware `plan:`) et masquée à l'écran. Tests : `FonctionnalitesFormulesTest`, `ControleFormulesBulletinTest`.

## 3. Rôles

Direction (administrateur, proviseur, censeur, CPE, surveillant), personnel (secrétaire, gestionnaire, agent comptable, responsable IT), enseignant, parent, super administrateur de la plateforme. Périmètre : un proviseur, censeur, CPE ou surveillant peut être limité à un cycle (collège, lycée) ou à un bâtiment.

## 4. État des fonctionnalités

| Domaine | Statut | Commentaire |
|---|---|---|
| Authentification, double authentification, mot de passe oublié | Fait | Verrouillage après 5 échecs |
| Connexion par téléphone (code WhatsApp) | Bloqué | Code et tests faits ; il faut un modèle d'authentification approuvé par Meta et les accès WhatsApp |
| Activation d'un établissement par e-mail | Bloqué | Fait ; les e-mails dépendent de l'accès SMTP Brevo |
| Création d'élève, inscription, réinscription, compte parent automatique | Fait | Montée de niveau réservée aux élèves admis |
| Modification et suppression (élèves, classes, inscriptions, frais) | Fait | Règles : pas de suppression avec historique (notes, paiements) |
| Périmètre par cycle ou bâtiment | Fait | Appliqué à toutes les listes |
| Appel et pointage des élèves (hors ligne) | Fait | Envoi automatique au retour du réseau |
| Pointage des enseignants | Fait | 15 min autour du cours, une fois par cours, dans le rayon de l'école (GPS) |
| Notes, bulletins par année, moyennes de classe | Fait | Année de référence = dernière inscription |
| Frais, paiements en espèces ou chèque, reçus PDF | Fait | Paiement en ligne retiré |
| Espace parent, autorisations de sortie signées, rendez-vous | Fait | VIP et plus |
| Notifications à la carte (e-mail, WhatsApp) | Partiel | Préférences faites ; l'envoi WhatsApp dépend de l'accès |
| Contact WhatsApp (admin ↔ parent) | Fait | Lien « Discuter sur WhatsApp » : la personne envoie elle-même le message |
| Thème par établissement | Fait | Un thème par école, couleur nationale par défaut |
| Multi-pays (22 pays d'Afrique francophone) | Fait | Monnaie, en-tête officiel, indicatif |
| Espace élève | À faire | Rôle, compte, pages élève |
| Rendez-vous, cahier de texte | Partiel | Rendez-vous fait ; cahier de texte à revérifier |
| Super administration (établissements, abonnements, journal, erreurs) | Fait | |
| Sauvegardes nocturnes | Fait | À vérifier sur le serveur de production |

## 5. Exigences non fonctionnelles

- **Isolation des données** : chaque école ne voit que ses données (testé).
- **Sécurité** : journal des modifications, sessions de 12 heures, jetons à usage unique, chiffrement des données sensibles.
- **Dépendances** : aucune faille connue côté serveur (`composer audit`) ni côté écran (`npm audit`) à la date de cette version.
- **Hors ligne** : l'appel fonctionne sans réseau et se synchronise.
- **Langue** : français ; messages d'erreur en français.

## 6. Dépendances externes

| Service | Usage | Ce qu'il faut |
|---|---|---|
| Brevo (SMTP) | E-mails d'activation, relances, notifications | Identifiant SMTP et clé |
| WhatsApp Cloud API (Meta) | Connexion par téléphone, notifications | Compte Business, numéro, modèle d'authentification approuvé |
| Serveur HTTPS | Hébergement | Nom de domaine, certificat, base MySQL dédiée |

## 7. Décisions en attente

1. **Personnalisation de la couleur** : réservée à Entreprise (choix actuel) ou ouverte à VIP ?
2. **Support** : adresse e-mail et numéro WhatsApp de la société (voir `frontend/.env`).
3. **Pays prioritaires** : ordre de déploiement et particularités locales à prévoir.

## 8. Reste à faire

Voir section 4 (« À faire » et « Partiel »), puis `docs/MISE_EN_PRODUCTION.md` pour le déploiement.
