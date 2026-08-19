# Libro Soci COOP

Mini gestionale Laravel + Filament per il libro soci di una cooperativa sociale.

## Stack

- Laravel 13
- Filament 5 Admin Panel
- MySQL/MariaDB in produzione
- DomPDF per PDF da template Blade
- PhpSpreadsheet per export Excel
- matteocontrini/comuni-json per dataset pubblico comuni/codici catastali
- Spatie Permission per ruoli `amministratore` e `operatore`
- Spatie Activitylog e tabella `socio_changes` per storico variazioni

## Installazione

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configurare MySQL/MariaDB in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=libro_soci_coop
DB_USERNAME=root
DB_PASSWORD=
```

Poi eseguire:

```bash
php artisan migrate --seed
php artisan serve
```

Per la distribuzione locale delle buste paga sono richiesti Tesseract, il modello
italiano e Poppler:

```bash
# Debian/Ubuntu
sudo apt install tesseract-ocr tesseract-ocr-ita poppler-utils

# macOS con Homebrew
brew install tesseract tesseract-lang poppler
```

L'immagine Docker include già queste dipendenze.

Pannello admin: `http://127.0.0.1:8000/admin`

Al primo accesso il pannello mostra il setup iniziale per impostare nome utente e password amministratore. Questi valori sostituiscono le credenziali tecniche create dal seed.

## Docker

Per avviare l'app su un altro PC basta avere Docker installato, scaricare i file `docker-compose.yml` e `update.bat`, poi eseguire:

```bash
docker compose up -d
```

L'app sarà disponibile su:

```text
http://localhost:8000/admin
```

Il database MySQL è esposto sulla porta locale `3307` e i dati vengono salvati nel volume Docker `mysql_data`.
Al primo avvio il container genera la chiave Laravel, esegue le migrazioni e prepara l'utente amministratore iniziale. Aprendo il pannello viene richiesto di impostare nome utente e password definitivi.

La chiave di cifratura dell'applicazione viene conservata nel volume Docker `app_storage`, così le credenziali S3 e SMTP salvate nelle impostazioni restano leggibili dopo il riavvio o l'aggiornamento del container. `update.bat` salva inoltre nel file `.env` dell'installazione la chiave delle versioni precedenti prima di sostituire il container. Il file `.env` non deve essere cancellato o condiviso.

Comandi utili:

```bash
docker compose exec app php artisan comuni:import --source=comuni-json
docker compose exec app php artisan test
docker compose down
docker compose down -v
```

`docker compose down -v` elimina anche database e file persistenti salvati nei volumi.

### Pubblicazione immagine Docker

Le immagini Docker vengono pubblicate automaticamente su GitHub Container Registry:

```text
ghcr.io/emmecoopconsulting-arch/librosocicoop:latest
```

Il workflow `.github/workflows/docker-image.yml` costruisce e pubblica l'immagine a ogni push su `main`.

Se l'app deve essere installata senza login GitHub sui PC degli utenti, rendere pubblico il package GHCR dalle impostazioni del package su GitHub. In alternativa, eseguire prima `docker login ghcr.io` su ogni PC.

### Aggiornamento su Windows con Docker

Per aggiornare applicazione e container basta eseguire dalla cartella dove si trova `docker-compose.yml`:

```bat
update.bat
```

Lo script scarica l'ultima immagine Docker pubblicata su GitHub, riavvia il container, applica le migrazioni e pulisce la cache.
Sulle vecchie installazioni Windows lo script può continuare a essere usato manualmente.

### Aggiornamento da Portainer

Le immagini pubblicate contengono l'identificativo del commit GitHub con cui sono
state costruite. La pagina `Sistema > Aggiornamenti` confronta questa versione con
l'ultima build Docker completata con successo e abilita il pulsante solo quando è
disponibile un'immagine più recente.

Per collegare il pulsante al redeploy dello stack, creare un webhook dello stack in
Portainer e aggiungere l'URL alle variabili dello stack:

```dotenv
UPDATE_WEBHOOK_URL=https://portainer.example/api/stacks/webhooks/ID_WEBHOOK
```

Se Portainer usa un certificato locale non riconosciuto dal container, è possibile
disabilitare la verifica solo sulla rete interna:

```dotenv
UPDATE_VERIFY_TLS=false
```

Il webhook di stack è disponibile nelle edizioni di Portainer che includono questa
funzionalità. L'URL è un segreto e non deve essere pubblicato nel repository o
mostrato agli utenti. Dopo aver aggiunto la variabile è necessario ricreare una
volta il container applicativo da Portainer. Le installazioni precedenti alla
versionatura dell'immagine richiedono inoltre un primo aggiornamento manuale.

## Import comuni

Dataset pubblico completo, consigliato per abilitare il calcolo del luogo di nascita da codice fiscale:

```bash
php artisan comuni:import --source=comuni-json
```

CSV:

```bash
php artisan comuni:import storage/app/comuni.csv --delimiter=";"
```

Excel:

```bash
php artisan comuni:import storage/app/comuni.xlsx
```

Intestazioni riconosciute:

- `progressivo`
- `denominazione comune`, `denominazione italiana`, `denominazione`, `nome` oppure `comune`
- `ripartizione geografica`
- `regione`
- `provincia/unità territoriale`, `provincia`, `denominazione provincia` oppure `sigla provincia`
- `codice catastale`, `codice belfiore`, `belfiore` oppure `codice`

## Funzioni principali

- Anagrafica soci con soft delete, filtri per tipologia/stato e validazioni.
- Codice socio automatico nel formato `SOC-0001`.
- Codice fiscale formalmente validato con calcolo data nascita e luogo nascita tramite codice catastale, inclusa decodifica omocodia.
- Vista “Libro soci” filtrata sui soli soci attivi.
- Export libro soci in PDF ed Excel.
- Verbali di ammissione collegati al socio, generati da Blade e salvati in `storage/app/private/verbali`.
- Pagina Filament “Gestione Verbali” per generazione singola o massiva dei verbali mancanti.
- Storico modifiche su tabella dedicata e activity log.
- Distribuzione buste paga da PDF unico con OCR Tesseract locale, revisione
  manuale delle associazioni e invio tramite Amazon SES o SMTP tradizionale.

## Verifica

```bash
php artisan test
./vendor/bin/pint
```
