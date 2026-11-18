# Mise en production — liste de contrôle

À faire **avant** d'ouvrir la plateforme à une école. Les valeurs ci-dessous sont à adapter ; les secrets ne se mettent jamais dans le dépôt Git.

## 1. Fichier `.env` du serveur

| Variable | Valeur en production | Pourquoi |
|---|---|---|
| `APP_ENV` | `production` | Désactive les modes de développement |
| `APP_DEBUG` | `false` | Sans cela, les erreurs affichent le code et la configuration |
| `APP_URL` | `https://api.votre-domaine.com` | Liens et cookies |
| `FRONTEND_URL` | `https://app.votre-domaine.com` | Liens des e-mails (activation, réinitialisation) |
| `CORS_ALLOWED_ORIGINS` | `https://app.votre-domaine.com` | Seul le front peut appeler l'API |
| `SANCTUM_STATEFUL_DOMAINS` | `app.votre-domaine.com` | Sessions de confiance |
| `DB_USERNAME` / `DB_PASSWORD` | Utilisateur MySQL **dédié** (pas `root`) | Limite les dégâts en cas de faille |
| `QUEUE_CONNECTION` | `database` (ou `redis`) | Les e-mails partent en arrière-plan |
| `LOG_LEVEL` | `warning` | Journaux utiles sans données personnelles |
| `POINTAGE_MODE_TEST` | `false` | **Obligatoire** : sinon le GPS n'est pas vérifié |
| `MAIL_*` | Identifiants SMTP Brevo | Activation, relances, notifications |
| `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_ID` | Accès WhatsApp Cloud API | Connexion par téléphone, notifications |
| `WHATSAPP_OTP_TEMPLATE` | Nom du modèle d'authentification approuvé par Meta | Code de connexion |
| `PAIEMENT_WEBHOOK_SECRET` | Secret partagé avec le prestataire | Vérifie les notifications de paiement d'abonnement |

Puis :

```bash
php artisan key:generate --show   # à copier dans APP_KEY, une seule fois
php artisan config:cache
php artisan route:cache
php artisan migrate --force
```

## 2. Secrets à changer

- **Clé Brevo** : elle a été partagée dans une conversation. La révoquer et en créer une nouvelle.
- **Mot de passe MySQL** : un mot de passe fort, propre à l'application.
- **`APP_KEY`** : générée sur le serveur, jamais copiée depuis un autre environnement.

## 3. Comptes de démonstration

Les seeders (`DatabaseSeeder`, `DemoDataSeeder`, `PlanDemoSeeder`) créent des comptes `@test.com` avec le mot de passe `password`. **Ne jamais lancer les seeders en production.** Si la base de test a déjà été importée, supprimer ces comptes :

```bash
php artisan tinker --execute="App\Models\User::whereIn('email', ['superadmin@test.com','admin@test.com','proviseur@test.com'])->delete();"
```

## 4. Processus permanents

- **File d'attente** (e-mails, notifications) : un processus `php artisan queue:work --tries=3`, relancé automatiquement (systemd ou Supervisor).
- **Planificateur** : une tâche cron, toutes les minutes :

```bash
* * * * * cd /chemin/vers/backend && php artisan schedule:run >> /dev/null 2>&1
```

Tâches prévues : expiration des abonnements (00 h 10), relances (08 h), sauvegarde (02 h), résumé hebdomadaire (lundi 07 h), alertes santé (09 h), nettoyage des sessions (03 h).

## 5. Front-end

```bash
cd frontend
npm ci
npm run build        # produit le dossier dist/, à servir en HTTPS
```

## 6. HTTPS et serveur web

- Certificat HTTPS valide (Let's Encrypt) sur l'API et sur l'application.
- Le dossier `backend/public` est le seul accessible depuis Internet pour l'API.
- Aucun fichier `.env`, `storage/` ou `vendor/` n'est servi publiquement.

## 7. Sauvegardes

- La commande `sauvegarde:executer` tourne chaque nuit. Vérifier qu'elle écrit bien dans un dossier **hors** du serveur (copie distante).
- Tester une restauration au moins une fois avant la mise en service.

## 8. Contrôles avant ouverture

- [ ] `APP_DEBUG=false` et une page d'erreur inexistante ne révèle rien.
- [ ] Un mot de passe incorrect 5 fois bloque le compte 15 minutes.
- [ ] Un élève ne peut pas voir les données d'une autre école (tests automatiques OK).
- [ ] Le pointage refuse un enseignant hors du rayon de l'école.
- [ ] Un e-mail d'activation arrive bien, avec le lien en HTTPS.
- [ ] La sauvegarde a produit un fichier, et il a été restauré une fois.
