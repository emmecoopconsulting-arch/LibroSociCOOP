@echo off
setlocal

cd /d "%~dp0"

echo Salvataggio chiave di cifratura...
for /f "usebackq delims=" %%K in (`docker compose exec -T app sh -c "sed -n 's/^APP_KEY=//p' /var/www/html/.env 2^>/dev/null ^| head -n 1" 2^>nul`) do set "CURRENT_APP_KEY=%%K"

if defined CURRENT_APP_KEY (
    powershell -NoProfile -ExecutionPolicy Bypass -Command "$path = Join-Path (Get-Location) '.env'; $key = $env:CURRENT_APP_KEY; $lines = if (Test-Path $path) { @(Get-Content $path) } else { @() }; $found = $false; $updated = foreach ($line in $lines) { if ($line -match '^APP_KEY=') { $found = $true; 'APP_KEY=' + $key } else { $line } }; if (-not $found) { $updated += 'APP_KEY=' + $key }; Set-Content -Path $path -Value $updated -Encoding ascii"
    if errorlevel 1 goto error
)

echo Scaricamento nuova immagine Docker...
docker compose pull
if errorlevel 1 goto error

echo Riavvio applicazione...
docker compose up -d
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
