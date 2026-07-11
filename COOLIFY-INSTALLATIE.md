# OmniFactuur op Coolify

Deze bestanden zijn bedoeld voor de repository:

`https://github.com/omnifexit/OmniFactuur`

De configuratie gebruikt de officiële image
`invoiceshelf/invoiceshelf:3.0.0-alpha.1` als basis en kopieert daarna de
OmniFactuur-code uit de repository over de image heen.

> Let op: InvoiceShelf 3.0.0-alpha.1 is een test-/insiderrelease en is niet
> bedoeld als stabiele productieversie voor bedrijfskritische facturatie.

## Bestanden toevoegen

Plaats deze bestanden in de root van de GitHub-repository:

- `Dockerfile`
- `docker-compose.yml`
- `.dockerignore`

Maak daarnaast in `.env.example` de bestaande `APP_KEY` leeg:

```env
APP_KEY=
```

De echte sleutel wordt door Coolify gegenereerd en blijft bij herdeployments
hetzelfde.

## Resource in Coolify maken

1. Open het gewenste project en de gewenste environment in Coolify.
2. Kies **New Resource**.
3. Kies de publieke GitHub-repository.
4. Selecteer **Docker Compose** als Build Pack.
5. Gebruik branch `main`.
6. Base Directory: `/`
7. Docker Compose Location: `/docker-compose.yml`
8. Sla de configuratie op.

## Domein

De compose-configuratie maakt een URL voor de service `app` en routeert die
naar containerpoort `8080`.

Voor een eigen domein open je in de service-stack de service `app` en zet je
bij Domains bijvoorbeeld:

```text
https://facturen.jouwdomein.nl:8080
```

`8080` is alleen de interne containerpoort. Bezoekers gebruiken normaal HTTPS
op poort 443.

Zorg vooraf voor een DNS A-record (en eventueel AAAA-record) naar de server
waar Coolify draait.

## Deployen

Klik op **Deploy**. De eerste build gebruikt de officiële alpha-image en
kopieert jouw repository eroverheen.

Open daarna het ingestelde domein. Wanneer de installatiewizard om de
databasegegevens vraagt, gebruik je:

```text
Database type: MariaDB / MySQL
Host: database
Port: 3306
Database: omnifactuur
Username: omnifactuur
Password: waarde van SERVICE_PASSWORD_64_DATABASE in Coolify
```

De databasenaam en gebruikersnaam kunnen in Coolify worden gewijzigd via
`DB_DATABASE` en `DB_USERNAME`.

## Terugkerende facturen

Maak na de installatie in Coolify een Scheduled Task voor de `app`-container:

```text
Schedule: every_minute
Command: php artisan schedule:run
```

Dit is nodig voor Laravel-taken zoals terugkerende facturen.

## Belangrijke persistente data

De stack maakt twee Docker-volumes:

- `omnifactuur_database` — MariaDB-gegevens
- `omnifactuur_storage` — uploads, logo's, documenten en applicatiebestanden

Maak van beide volumes back-ups voordat je update of migreert.

## Problemen oplossen

### 502 / No Available Server

Controleer dat het domein bij de service `app` eindigt op `:8080`.

### Installatiewizard kan database niet bereiken

Controleer:

```text
DB_HOST=database
DB_PORT=3306
```

Bekijk daarna de logs van zowel `app` als `database`.

### Rechtenfout in storage

Open de terminal van de `app`-container en voer uit:

```bash
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache
php artisan optimize:clear
```

### Na een domeinwijziging blijft de oude URL zichtbaar

Controleer in Coolify de waarden van:

```text
APP_URL
ASSET_URL
SESSION_DOMAIN
SANCTUM_STATEFUL_DOMAINS
```

Sla op en redeploy daarna de stack.
