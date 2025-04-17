# Feedback API

Aplikace pro sběr a správu zpětné vazby pomocí API rozhraní.

## Požadavky

- PHP 8.1 nebo vyšší
- Composer
- MySQL/MariaDB
- Symfony CLI (volitelné, pro lokální vývoj)

## Instalace

### 1. Klonování repozitáře

```bash
git clone https://github.com/Nimuell/feedback-api.git
cd feedback-api
```

### 2. Instalace závislostí

```bash
composer install
```

### 3. Konfigurace prostředí

Zkopírujte soubor `.env.skeleton` do `.env.local` a upravte nastavení databáze a další konfigurace:

```bash
cp .env.skeleton .env.local
```

Upravte proměnné v souboru `.env.local`:

```
DATABASE_URL="mysql://uživatel:heslo@127.0.0.1:3306/feedback_api?serverVersion=8.0"
ADMIN_AUTH_BEARER= bearer token
```

### 4. Spuštění vývojového serveru

```bash
# Pomocí Symfony CLI
symfony server:start

#pro spuštění databáze
docker compose up -d --build
```

### 5. Vytvoření databáze

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```



## API Endpointy

### Veřejné endpointy

#### Odeslání zpětné vazby

`POST /api/feedback`

**Příklad požadavku:**
```json
{
  "email": "uzivatel@example.com",
  "phone": "+420123456789",
  "message": "Vaše zpětná vazba zde",
  "wants_contact": true
}
```

### Administrátorské endpointy

Pro přístup k administrátorským endpointům je vyžadován API klíč v hlavičce `Authorization: Bearer VášAPIKlíč`.

#### Seznam zpětné vazby

`GET /api/admin/feedback`

#### Detail zpětné vazby

`GET /api/admin/feedback/{id}`

#### Aktualizace zpětné vazby

`PATCH /api/admin/feedback/{id}`

**Příklad požadavku:**
```json
{
  "status": "resolved",
  "contacted": true,
  "internal_note": "Poznámka pro interní účely"
}
```

**Možné hodnoty pro status:**
- `new` - Nová zpětná vazba
- `in_progress` - Zpracovává se
- `resolved` - Vyřešeno
- `closed` - Uzavřeno

## Testování

### Instalace testovacích závislostí

```bash
composer require --dev symfony/phpunit-bridge
```

### Spuštění testů

```bash
# Spuštění všech testů
php bin/phpunit

# Spuštění jednotkových testů
php bin/phpunit tests/Unit

# Spuštění testů API
php bin/phpunit tests/ApiResource
```
