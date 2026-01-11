# Zalet Local Development Setup

> Instructions for AI agents and developers on how to set up and run the Zalet project locally.

## Project Structure

```
ZaletNewApp/
├── zalet-api/     # Laravel 11 PHP Backend
├── zalet-web/     # React + TypeScript Frontend
├── ZaletApp/      # React Native Mobile App
└── docs/          # Documentation
```

## Prerequisites

- **Docker Desktop** (required for backend services)
- **Node.js 18+** (for frontend)
- **PHP 8.2+** (if running backend outside Docker)
- **Composer** (PHP package manager)

---

## Backend (zalet-api)

### Quick Start with Docker

```bash
cd zalet-api

# Start all services
docker compose up -d

# Or start only specific services (minimal for testing)
docker compose up -d pgsql redis
```

### Docker Services

| Service | Container | Port | Description |
|---------|-----------|------|-------------|
| `pgsql` | uzivo_pgsql | 5432 | PostgreSQL 16 database |
| `redis` | uzivo_redis | 6379 | Redis cache & queue |
| `app` | uzivo_app | - | PHP-FPM application |
| `nginx` | uzivo_nginx | 8000 | Web server |
| `reverb` | uzivo_reverb | 8080 | WebSocket server |
| `queue` | uzivo_queue | - | Queue worker |
| `scheduler` | uzivo_scheduler | - | Cron scheduler |
| `meilisearch` | uzivo_meilisearch | 7700 | Full-text search |
| `livekit` | uzivo_livekit | 7880, 7881, 7882 | WebRTC streaming |

### Database Credentials (Default)

```env
DB_HOST=127.0.0.1        # Use localhost when running outside Docker
DB_HOST=pgsql            # Use service name inside Docker
DB_PORT=5432
DB_DATABASE=uzivo
DB_USERNAME=uzivo
DB_PASSWORD=secret
```

### Running Tests

**Important**: Tests require PostgreSQL (SQLite not supported due to CHECK constraints).

```bash
cd zalet-api

# 1. Start required Docker services
docker compose up -d pgsql redis

# 2. Create test database (one-time setup)
docker compose exec pgsql psql -U uzivo -c "CREATE DATABASE uzivo_test;"

# 3. Run tests with environment variables
DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=uzivo_test DB_USERNAME=uzivo DB_PASSWORD=secret php artisan test

# 4. Run tests in parallel (faster)
DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=uzivo_test DB_USERNAME=uzivo DB_PASSWORD=secret php artisan test --parallel
```

### Common Commands

```bash
# Artisan commands (inside Docker)
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose exec app php artisan cache:clear

# Artisan commands (local PHP with Docker DB)
DB_HOST=127.0.0.1 php artisan migrate
DB_HOST=127.0.0.1 php artisan test

# View logs
docker compose logs -f app
docker compose logs -f pgsql

# Reset database
docker compose exec pgsql psql -U uzivo -c "DROP DATABASE uzivo_test;"
docker compose exec pgsql psql -U uzivo -c "CREATE DATABASE uzivo_test;"

# Access PostgreSQL shell
docker compose exec pgsql psql -U uzivo

# Access Redis CLI
docker compose exec redis redis-cli
```

### Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate

# Install dependencies
composer install
```

---

## Frontend (zalet-web)

### Quick Start

```bash
cd zalet-web

# Install dependencies
npm install

# Start development server
npm run dev
```

### Build & Lint

```bash
npm run build          # Production build
npm run lint           # Run ESLint
npm run type-check     # TypeScript check
```

### Configuration

The frontend expects the backend API at `http://localhost:8000/api/v1`.

---

## Full Stack Development

### Start Everything

```bash
# Terminal 1 - Backend services
cd zalet-api
docker compose up -d

# Terminal 2 - Frontend
cd zalet-web
npm run dev
```

### Minimal Setup (for testing)

```bash
# Just database and cache
cd zalet-api
docker compose up -d pgsql redis

# Run backend tests
DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=uzivo_test DB_USERNAME=uzivo DB_PASSWORD=secret php artisan test
```

---

## Troubleshooting

### "Class Redis not found"
Redis PHP extension is required. When running tests locally, ensure Redis is running:
```bash
docker compose up -d redis
```

### "Database uzivo_test does not exist"
Create the test database:
```bash
docker compose exec pgsql psql -U uzivo -c "CREATE DATABASE uzivo_test;"
```

### "SQLSTATE connection refused"
Ensure Docker services are running and use correct host:
- Inside Docker containers: `DB_HOST=pgsql`
- From local machine: `DB_HOST=127.0.0.1`

### Migration fails on CHECK constraint
This project uses PostgreSQL-specific features. SQLite is not supported for testing.

### Port already in use
Check what's using the port:
```bash
lsof -i :5432  # PostgreSQL
lsof -i :6379  # Redis
lsof -i :8000  # Nginx
```

---

## Service Health Checks

```bash
# PostgreSQL
docker compose exec pgsql pg_isready -U uzivo

# Redis
docker compose exec redis redis-cli ping

# All containers status
docker compose ps
```

---

## Notes for AI Agents

1. **Always start Docker services first** before running backend tests
2. **Use environment variables** to override DB_HOST when running outside Docker
3. **Test database is separate** - create `uzivo_test` database for testing
4. **Redis is required** for tests that involve live sessions, duels, queues
5. **Check docker compose ps** to see running services
6. **Parallel tests** use `--parallel` flag but require multiple DB connections

### Quick Test Commands (Copy-Paste Ready)

**Option 1: Run tests inside Docker (recommended - has all PHP extensions)**
```bash
cd /path/to/ZaletNewApp/zalet-api && \
docker compose up -d pgsql redis app && \
sleep 3 && \
docker compose exec app php artisan test --parallel
```

**Option 2: Run tests from local machine (requires local PHP with phpredis)**
```bash
cd /path/to/ZaletNewApp/zalet-api && \
docker compose up -d pgsql redis && \
sleep 3 && \
docker compose exec pgsql psql -U uzivo -c "CREATE DATABASE IF NOT EXISTS uzivo_test;" 2>/dev/null || true && \
DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=uzivo_test DB_USERNAME=uzivo DB_PASSWORD=secret REDIS_HOST=127.0.0.1 php artisan test
```

### Known Test Dependencies

| Service | Tests Affected | Notes |
|---------|---------------|-------|
| PostgreSQL | All DB tests | Required - SQLite not supported |
| Redis | LiveSession, Duel, Queue tests | Required for phpredis extension |
| Reverb | Broadcast/WebSocket tests | Optional - tests fail gracefully |

### Expected Test Results

With `pgsql`, `redis`, and `app` containers running:
- **~487 passing** tests
- **~11 failing** tests (Reverb/broadcast related if port 8080 is in use)
