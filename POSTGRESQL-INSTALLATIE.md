# OmniFactuur – Coolify met PostgreSQL

Dit pakket vervangt de eerdere MariaDB-configuratie.

## Wat je moet vervangen

Vervang in de root van de GitHub-repository:

- `docker-compose.yml`
- `Dockerfile`
- `.dockerignore`

Commit en push de bestanden naar `main`.

## Opnieuw laden in Coolify

1. Open de OmniFactuur-resource.
2. Laat Coolify de nieuwste commit ophalen.
3. Controleer dat het Build Pack **Docker Compose** is.
4. Gebruik `/docker-compose.yml` als Compose-locatie.
5. Kies **Redeploy** of **Force Rebuild**.

Je hoeft geen losse PostgreSQL-resource aan te maken. Het Compose-bestand maakt
zelf een service `database` met PostgreSQL 15 aan.

## Coolify Environment Variables

De volgende waarden mogen blijven staan of worden aangemaakt:

```env
APP_NAME=OmniFactuur
APP_TIMEZONE=Europe/Amsterdam
APP_LOCALE=nl
DB_DATABASE=omnifactuur
DB_USERNAME=omnifactuur
SERVICE_PASSWORD_64_DATABASE=<door Coolify gegenereerd>
SERVICE_BASE64_32_APPKEY=<door Coolify gegenereerd>
```

`SERVICE_PASSWORD_64_DATABASE` en `SERVICE_BASE64_32_APPKEY` worden door
Coolify als magic variables gegenereerd wanneer het Compose-bestand wordt
ingelezen.

Deze oude MariaDB-variabele is niet meer nodig:

```env
SERVICE_PASSWORD_64_DATABASEROOT=
```

## Gegevens voor de InvoiceShelf-wizard

```text
Databaseverbinding: PostgreSQL
Hostname: database
Poort: 5432
Database: omnifactuur
Gebruikersnaam: omnifactuur
Wachtwoord: waarde van SERVICE_PASSWORD_64_DATABASE
```

Gebruik niet `127.0.0.1` of `localhost`. Binnen de Compose-stack is de
PostgreSQL-hostnaam `database`.

Laat **Overwrite existing database and proceed** uit bij een nieuwe database.

## Persistente volumes

- `omnifactuur_postgres`: PostgreSQL-database
- `omnifactuur_storage`: uploads en applicatieopslag
- `omnifactuur_modules`: geïnstalleerde modules

Een eventueel oud MariaDB-volume wordt niet meer gebruikt en kan pas worden
verwijderd nadat je hebt bevestigd dat PostgreSQL correct werkt.
