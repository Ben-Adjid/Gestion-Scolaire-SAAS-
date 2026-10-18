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
| Database | MySQL (local, port 3307 en développement) |
| Deployment | Local (php artisan serve + npm run dev) — Docker non utilisé |
| Security | RBAC 10 rôles, journal d'audit, verrouillage des notes |

---

## Features

### Securite
- Authentication avec Sanctum & JWT
- Double authentification : à venir (non implémentée)
- 10 rôles d'accès (Super admin, Admin, Proviseur, Censeur, CPE, Enseignant, Secrétaire, Gestionnaire, Agent comptable, Responsable IT)
- Audit logging de toutes les operations
- Rate limiting (5 attempts/min on login)
- CORS avec tenant enforcement

### Gestion Academique
- CRUD Complet: Classes, Etudiants, Professeurs, Matieres
- Saisie de notes (individuelle & groupee)
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
- Paiements en ligne Stripe : à venir (non implémentés)
- Reçus de paiement : à venir (non implémentés)
- Tracking des arrieres

### Communication
- Messagerie unifiee (utilisateur ↔ utilisateur)
- Annonces par classe
- Notifications SMS/Email : à venir (service présent, envoi non configuré)
- Suivi des messages lus

---

## Quick Start

### Prerequisites
- PHP 8.2+, Composer, Node.js, MySQL
- Git

### Installation (5 minutes)

```bash
# 1. Cloner
git clone <url>
cd gestscolairecrud-main

# 2. Configuration
cp .env.example .env

# 3. Demarrer
docker-compose up -d

# 4. Migrations
docker-compose exec api php artisan migrate --seed

# 5. Acceder
# Frontend: http://localhost:3000
# API: http://localhost:8000
```

### Test Credentials

```
Email: admin@test.com
Password: password
Role: Admin
```

Voir QUICKSTART.md pour plus de details.

---

## Architecture

### Stack

Frontend (React 18 + Vite) -> Backend (Laravel 12 API) -> Database (MySQL)

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
│   ├── app/Models/            # 15 modeles Eloquent
│   ├── app/Http/Controllers/  # 15 controleurs API
│   ├── app/Services/          # Logique metier
│   ├── database/
│   │   ├── migrations/        # 12 migrations
│   │   ├── factories/         # Factories de test
│   │   └── seeders/           # Donnees initiales
│   └── routes/api.php         # Routes API
│
├── frontend/                   # React SPA
│   ├── src/components/        # Composants React
│   ├── src/hooks/             # useApi, useAuth
│   └── src/pages/             # Pages
│
├── docker-compose.yml         # Stack complete
├── .env.example               # Configuration
├── QUICKSTART.md              # Guide de demarrage
├── API_REFERENCE.md           # Documentation API
├── DEPLOYMENT.md              # Guide de deploiement
└── PROJECT_STATUS.md          # Rapport complet
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
- Double authentification : à venir
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

## Deploiement

### Local (Development)
```bash
docker-compose up -d
docker-compose exec api php artisan migrate --seed
# Acceder: http://localhost:3000
```

### Production
```bash
docker-compose -f docker-compose.prod.yml build
docker-compose -f docker-compose.prod.yml up -d
docker-compose -f docker-compose.prod.yml exec api php artisan migrate --force
```

Voir DEPLOYMENT.md pour instructions detaillees.

---

## Support

### Troubleshooting
- Port already in use: Changer port dans docker-compose.yml
- DB connection error: Redemarrer postgres service
- Migration fails: Executer php artisan migrate:reset
- API 401: Verifier token JWT dans Authorization header

Voir QUICKSTART.md pour solutions completes.

---

## Checklist de Livraison

- [x] Phase 1: Core System
- [x] Phase 2: Intelligence
- [x] Phase 3: Finance
- [x] Phase 4: Infrastructure
- [x] Security: RBAC par rôle, journal d'audit (2FA à venir)
- [x] API: 30+ endpoints documented
- [x] Frontend: React components
- [x] Tests: Unit & Feature tests
- [x] Documentation: Complete guides
- [x] Performance: Optimized

---

## License

Proprietary - Tous droits reserves

---

## Commencer

1. Lire QUICKSTART.md
2. Executer docker-compose up -d
3. Acceder a http://localhost:3000
4. Se connecter avec admin@test.com / password
5. Consulter API_REFERENCE.md pour fonctionnalites avancees

**Systeme pret pour production!** 🚀
