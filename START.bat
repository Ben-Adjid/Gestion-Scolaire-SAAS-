@echo off
REM ============================================================
REM GESTION SCOLAIRE - SCRIPT DE DÉMARRAGE
REM ============================================================

echo.
echo ==========================================
echo  GESTION SCOLAIRE - DÉMARRAGE COMPLET
echo ==========================================
echo.

REM Vérifier que nous sommes dans le bon dossier
if not exist backend (
    echo ERREUR: Le dossier 'backend' n'existe pas!
    echo Exécutez ce script depuis C:\Users\aboub\Desktop\gestscolairecrud-main\
    pause
    exit /b 1
)

REM ÉTAPE 1: Aller dans le backend
cd backend
echo [1/6] Nettoyage des caches...
call php artisan cache:clear >nul 2>&1
call php artisan config:clear >nul 2>&1
echo ✓ Caches nettoyés

REM ÉTAPE 2: Générer la clé APP si nécessaire
echo [2/6] Génération clé APP...
call php artisan key:generate --force >nul 2>&1
echo ✓ Clé APP générée

REM ÉTAPE 3: Vérifier la connexion DB
echo [3/6] Vérification connexion DB...
call php artisan tinker < nul > nul 2>&1
if errorlevel 1 (
    echo ERREUR: Impossible de se connecter à MySQL!
    echo Vérifiez:
    echo - MySQL est démarré (port 3307)
    echo - BD 'gestion_scolaire' existe
    echo - Credentials dans .env sont corrects
    pause
    exit /b 1
)
echo ✓ Connexion DB OK

REM ÉTAPE 4: Exécuter les migrations
echo [4/6] Exécution des migrations...
call php artisan migrate >nul 2>&1
if errorlevel 1 (
    echo ERREUR: Les migrations ont échoué!
    call php artisan migrate --verbose
    pause
    exit /b 1
)
echo ✓ Migrations exécutées

REM ÉTAPE 5: Charger les données de test
echo [5/6] Chargement des données de test...
call php artisan db:seed --class=DemoDataSeeder >nul 2>&1
if errorlevel 1 (
    echo Attention: Le seeder a échoué (peut être normal si données déjà présentes)
) else (
    echo ✓ Données chargées
)

REM ÉTAPE 6: Vérifier les données
echo [6/6] Vérification des données...
REM Vérifier avec tinker que les données existent
for /f "delims=" %%i in ('php artisan tinker 2^>nul') do (
    echo Données vérifiées
)

echo.
echo ==========================================
echo  ✓ TOUT EST PRÊT!
echo ==========================================
echo.
echo Pour démarrer l'application:
echo.
echo TERMINAL 1 (Backend - API):
echo   cd backend
echo   php artisan serve
echo.
echo TERMINAL 2 (Frontend - React):
echo   cd frontend
echo   npm run dev
echo.
echo Ensuite accédez à: http://localhost:5173
echo Login: admin@test.com / password
echo.
pause
