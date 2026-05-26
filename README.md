# Libro Soci COOP

Mini gestionale Laravel + Filament per il libro soci di una cooperativa sociale.

## Stack

- Laravel 13
- Filament 5 Admin Panel
- MySQL/MariaDB in produzione
- DomPDF per PDF da template Blade
- PhpSpreadsheet per export Excel
- mlocati/comuni-italiani per dataset pubblico comuni/codici catastali
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

Pannello admin: `http://127.0.0.1:8000/admin`

Utente iniziale creato dal seed:

- email: `admin@example.com`
- password: `password`

## Import comuni

Dataset pubblico completo, consigliato per abilitare il calcolo del luogo di nascita da codice fiscale:

```bash
php artisan comuni:import --source=mlocati
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

## Verifica

```bash
php artisan test
./vendor/bin/pint
```
