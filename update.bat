@echo off
setlocal

cd /d "%~dp0"

echo Aggiornamento sorgenti da GitHub...
git pull --ff-only
if errorlevel 1 goto error

echo Ricostruzione e riavvio Docker...
docker compose up -d --build
if errorlevel 1 goto error

echo Applicazione migrazioni database...
docker compose exec app php artisan migrate --force --no-interaction
if errorlevel 1 goto error

echo Pulizia cache applicazione...
docker compose exec app php artisan optimize:clear
if errorlevel 1 goto error

echo.
echo Aggiornamento completato. Apri http://localhost:8000/admin
exit /b 0

:error
echo.
echo Aggiornamento non riuscito. Controlla il messaggio sopra.
exit /b 1
