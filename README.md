# Gestion Scolaire CRUD v1.0.0

**Systeme complet de gestion scolaire pour lycees et colleges**

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Quick Start](#quick-start)
- [Architecture](#architecture)
- [Documentation](#documentation)

---

## Overview

Un systeme complet pour gerer tous les aspects d'un etablissement scolaire:
- Academique: Classes, etudiants, notes, absences
- Administratif: Professeurs, emploi du temps, regles
- Finance: Frais scolaires, paiements, rapports
- Communication: Messagerie, alertes, notifications
- Intelligence: Tableau de bord, alertes AI, conseil de classe

### Technologies

| Layer | Technology |
|-------|-----------|
| Frontend | React 18 + Vite + TailwindCSS |
| Backend | Laravel 12 + Sanctum |
| Database | SQLite par défaut en développement, MySQL configurable |
| Deployment | API Laravel + frontend Vite ; aucun fichier Docker n'est fourni |
| Security | RBAC 10 rôles, journal d'audit, verrouillage des notes |

---

## Features

### Securite
- Authentication avec Sanctum & JWT
- Double authentification TOTP disponible par compte
- 10 rôles d'accès (Super admin, Admin, Proviseur, Censeur, CPE, Enseignant, Secrétaire, Gestionnaire, Agent comptable, Responsable IT)
- Audit logging de toutes les operations
- Rate limiting (5 attempts/min on login)
- CORS avec tenant enforcement

### Gestion Academique
- CRUD Complet: Classes, Etudiants, Professeurs, Matieres
- Saisie de notes individuelle et groupée (saisie rapide clavier et modèles de commentaires réutilisables)
- Avertissement de doublons potentiels à la création d'un élève, avec confirmation explicite et journalisée
- Suivi des absences avec auto-escalade
- Emploi du temps & creneaux
- Bulletins scolaires avec ranking
- Conseil de classe avec generation de PV

### Dashboard & Alertes
- Tableau de bord proviseur en temps reel
- Systeme d'alertes AI
- Detection baisse de performance
- Suivi des absences
- Escalade automatique au CPE
- Gestion des alertes (acknowledge, filter)

### Finance
- Gestion des frais scolaires
- Enregistrement des paiements (4 modes)
- Paiements d'abonnement par Mobile Money ou virement : demande puis validation manuelle par la plateforme
- Reçus de paiement des frais scolaires et d'abonnement
- Paiement automatisé en ligne : non activé avant le choix du prestataire et l'intégration de son protocole
- Tracking des arrieres

### Communication
- Messagerie unifiee (utilisateur ↔ utilisateur)
- Annonces par classe
- Notifications d'absence et de convocation aux parents incluses en Standard ; l'e-mail nécessite un SMTP opérationnel et WhatsApp ses identifiants
- Suivi des messages lus

Les relances individuelles des frais impayés et les notifications parentales sont incluses en Standard. La fiche de suivi élève (notes, absences et retards) est également disponible aux rôles de direction, censeur et CPE.

---

## Quick Start

### Prerequisites
- PHP 8.2+, Composer, Node.js 20+
- Git

### Installation locale

```bash
# 1. Cloner
git clone <url>
cd gestscolairecrud-main

# 2. Backend (terminal 1)
cd backend
copy .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000

# 3. Frontend (terminal 2, depuis la racine du dépôt)
cd frontend
npm ci
npm run dev
# Frontend: http://localhost:5173
# API: http://localhost:8000
```

SQLite est le choix de développement par défaut. Pour MySQL, configurez les variables `DB_*` dans `backend/.env` avant les migrations. Les comptes disponibles dépendent des seeders exécutés.

---

## Architecture

### Stack

Frontend (React 18 + Vite) -> Backend (Laravel 12 API) -> Database (SQLite ou MySQL)

### API Endpoints: 30+

- Classes: CRUD (5 endpoints)
- Students: CRUD + search (6 endpoints)
- Grades: CRUD + bulk (4 endpoints)
- Absences: CRUD + stats (4 endpoints)
- Dashboard: Stats + alerts (2 endpoints)
- Council: Prepare + PV (2 endpoints)
- Messages: Send + manage (3 endpoints)
- Finance: Fees + payments (3 endpoints)

Voir API_REFERENCE.md pour documentation complete.

---

## Project Structure

```
.
├── backend/                    # Laravel API
│   ├── app/Models/            # Modèles Eloquent
│   ├── app/Http/Controllers/  # Contrôleurs API
│   ├── app/Services/          # Logique metier
│   ├── database/
│   │   ├── migrations/        # Migrations de schéma
│   │   ├── factories/         # Factories de test
│   │   └── seeders/           # Donnees initiales
│   └── routes/api.php         # Routes API
│
├── frontend/                   # React SPA
│   ├── src/components/        # Composants React
│   ├── src/hooks/             # useApi, useAuth
│   └── src/pages/             # Pages
│
├── backend/.env.example       # Configuration API
├── frontend/package.json      # Scripts frontend
└── README.md                  # Guide et limites d'exploitation
```

---

## Documentation

### Pour Commencer
- QUICKSTART.md - Guide de demarrage rapide
- DEPLOYMENT.md - Deploiement en production

### Reference Technique
- API_REFERENCE.md - Documentation API complete
- PROJECT_STATUS.md - Rapport de completion
- IMPLEMENTATION_SUMMARY.md - Vue d'ensemble technique

---

## Security

### Implementation
- JWT Authentication (24h expiration)
- Double authentification TOTP disponible par compte
- 10 rôles RBAC avec permissions par rôle et périmètre enseignant
- Audit logging de toutes les operations sensibles
- Rate limiting (5 attempts/min on login)
- Validation des fichiers uploades
- Private file storage
- CORS avec tenant enforcement
- SQL injection prevention (Eloquent ORM)
- XSS protection (CSP headers)

Voir DEPLOYMENT.md pour checklist complète avant production.

---

## Paiements d'abonnement

Le renouvellement manuel permet à un administrateur d'établissement de choisir une formule, d'afficher les coordonnées configurées, de transmettre une référence et un justificatif privé. Le super-administrateur vérifie puis approuve ou refuse la demande. L'approbation seule active le plan et inscrit le paiement.

Configurez les coordonnées dans `backend/.env` à partir de `backend/.env.example` :

- `PAIEMENT_MOBILE_LIBELLE`, `PAIEMENT_MOBILE_NUMERO`, `PAIEMENT_MOBILE_TITULAIRE`
- `PAIEMENT_BANQUE_NOM`, `PAIEMENT_BANQUE_TITULAIRE`, `PAIEMENT_BANQUE_COMPTE`, `PAIEMENT_BANQUE_IBAN`, `PAIEMENT_BANQUE_BIC`
- `PAIEMENT_DEVISE` (XOF par défaut)

Les coordonnées vides désactivent le mode correspondant. La passerelle hébergée et son webhook ne doivent pas être considérés opérationnels avant le choix du prestataire, l'intégration de son protocole spécifique et la configuration de ses secrets. Ne stockez aucun secret de production dans le dépôt.

## Déploiement

Ce dépôt ne contient pas de configuration Docker ni de procédure de déploiement production vérifiée. Avant mise en production, configurez HTTPS, la base de données, les sauvegardes, le stockage privé des fichiers, SMTP, les tâches planifiées et les identifiants des fournisseurs réellement utilisés ; exécutez les migrations et toutes les suites de tests dans un environnement de préproduction. Les étapes locales ci-dessus ne constituent pas une certification de production.

---

## Support

### Troubleshooting
- Port déjà utilisé : modifier le port de `php artisan serve` ou de Vite.
- Erreur de base de données : vérifier `DB_CONNECTION` et les paramètres `DB_*` dans `backend/.env`.
- Migration échouée : lire l'erreur et corriger la configuration avant toute nouvelle tentative ; ne pas réinitialiser une base contenant des données.
- API 401 : vérifier la session Sanctum et l'URL de l'API configurée pour le frontend.

Voir QUICKSTART.md pour solutions completes.

---

## État de livraison

Le dépôt contient des modules métier, des contrôles d'accès, des tests et une procédure de paiement manuel des abonnements. La présence de tests ne garantit pas qu'ils passent dans chaque environnement. La passerelle de paiement automatisée, les coordonnées réelles, la configuration des services de production et la validation complète en préproduction restent à faire ; voir la section Déploiement.

---

## License

Proprietary - Tous droits reserves

---

## Commencer

1. Lire QUICKSTART.md
2. Suivre les commandes d'installation locale ci-dessus
3. Configurer les coordonnées de paiement souhaitées hors du dépôt
4. Exécuter les suites de tests en préproduction
5. Choisir et intégrer un prestataire avant d'activer le paiement hébergé

**Le code ne constitue pas à lui seul une certification de préparation à la production.**
