#!/usr/bin/env bash
# Déploiement de la plateforme scolaire sur le serveur Ubuntu (VPS).
#
# Usage, depuis la racine du projet sur le serveur :
#   VITE_API_URL=https://api.votre-domaine.com/api ./deploy.sh [branche]
#
# La branche par défaut est « main ». Le script s'arrête au premier échec.
# Prérequis : backend/.env de production (voir backend/.env.production.example),
# worker de file d'attente installé (deploy/laravel-worker.service).

set -euo pipefail

BRANCHE="${1:-main}"
RACINE="$(cd "$(dirname "$0")" && pwd)"
BACKEND="$RACINE/backend"
FRONTEND="$RACINE/frontend"

echo "🚀 Début du déploiement (branche : $BRANCHE)..."

# Garde-fous : refuser un déploiement sans configuration de production.
if [ ! -f "$BACKEND/.env" ]; then
    echo "❌ $BACKEND/.env introuvable. Copiez backend/.env.production.example et renseignez-le."
    exit 1
fi
grep -qx 'APP_ENV=production' "$BACKEND/.env" || { echo "❌ APP_ENV doit valoir production dans backend/.env."; exit 1; }
grep -qx 'APP_DEBUG=false' "$BACKEND/.env" || { echo "❌ APP_DEBUG doit valoir false dans backend/.env."; exit 1; }
if [ -z "${VITE_API_URL:-}" ]; then
    echo "❌ Définissez VITE_API_URL (ex. https://api.votre-domaine.com/api) avant le déploiement."
    exit 1
fi

echo "📥 Mise à jour du code..."
cd "$RACINE"
git fetch origin "$BRANCHE"
git pull --ff-only origin "$BRANCHE"

echo "🐘 Dépendances PHP et base de données..."
cd "$BACKEND"
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan storage:link || true

echo "⚡ Mise en cache de la configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "🔁 Redémarrage du worker de file d'attente..."
php artisan queue:restart

echo "🎨 Construction de l'application web..."
cd "$FRONTEND"
npm ci
npm run build

echo "✅ Déploiement terminé avec succès !"
echo "Rappel : vérifiez le worker avec « systemctl status laravel-worker »."
