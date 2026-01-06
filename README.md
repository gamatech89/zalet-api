# UŽIVO Platform

> **Real-time live streaming and duel platform for Balkan creators**

[![PHPStan](https://img.shields.io/badge/PHPStan-Level%208-success)](https://phpstan.org/)
[![Tests](https://img.shields.io/badge/Tests-501%20passing-success)](https://pestphp.com/)
[![Laravel](https://img.shields.io/badge/Laravel-11.x-red)](https://laravel.com/)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue)](https://php.net/)

---

## 🚀 Project Status

**ALL BACKEND FEATURES COMPLETE ✅**

- 10 milestones completed (Identity → Notifications)
- 501 tests passing across all domains
- PHPStan Level 8 with zero errors
- Full REST API with real-time WebSocket support
- Production-ready backend infrastructure

---

## 📋 Features

### Identity & Social
- User authentication with Laravel Sanctum
- Public/private profiles with bio and location
- Follow system with private account requests
- 19 pre-seeded city kafanas across Balkans

### Content & Streaming
- Video posts with YouTube/Vimeo parsing
- Personalized feed generation
- Location-based content discovery

### Wallet & Payments
- Double-entry ledger system
- Payment intents with RaiAccept integration
- Virtual gift catalog (Rakija, Ruža, Srce, Kruna, Auto)
- Creator earnings tracking

### Live Duels
- Real-time 1v1 live streaming duels
- Redis-backed scoring system (sub-100ms updates)
- Gift-based score battles
- WebSocket broadcasting via Laravel Reverb
- Event history and replay data

### Chat System
- Direct Messages (1:1 private conversations)
- Public kafana rooms (role-based creation)
- Mute/block functionality
- Real-time message delivery
- Presence channels for active users

### Notifications
- 18 notification types (social, wallet, duels, system)
- Real-time delivery via WebSockets
- Unread count tracking
- Polymorphic relationships to all notifiable models
- Full CRUD API

---

## 🛠️ Tech Stack

| Component       | Technology      | Version |
|----------------|-----------------|---------|
| Framework      | Laravel         | 11.x    |
| Language       | PHP             | 8.3+    |
| Database       | PostgreSQL      | 16.x    |
| Cache/Queue    | Redis           | 7.x     |
| WebSockets     | Laravel Reverb  | Latest  |
| Testing        | Pest            | Latest  |
| Static Analysis| PHPStan         | Level 8 |
| Containerization| Docker         | Latest  |

---

## 🏗️ Architecture

### Domain-Driven Design

```
app/Domains/
├── Identity/     # Users, Auth, Profiles, Locations, Follows, Notifications
├── Wallet/       # Credits, Ledger, Payments, Gifts
├── Streaming/    # Posts, Videos, Feed
└── Duel/         # Live Sessions, Chat, Messages, Events
```

### Actions Pattern (Spatie-style)
- Single responsibility classes
- Located in `{Domain}/Actions/`
- Method signature: `public function execute(...): ReturnType`

### API Resources
- Consistent camelCase JSON responses
- Located in `{Domain}/Resources/`

---

## 🚦 Getting Started

### Prerequisites
- Docker & Docker Compose
- Make (optional, for convenience commands)

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd ZaletApp

# Start Docker containers
docker-compose up -d

# Install dependencies
docker exec uzivo_app composer install

# Run migrations and seeders
docker exec uzivo_app php artisan migrate:fresh --seed

# Generate application key
docker exec uzivo_app php artisan key:generate

# Start Laravel Reverb WebSocket server
docker exec uzivo_app php artisan reverb:start
```

### Run Tests

```bash
# All tests
./vendor/bin/pest

# Specific domain
./vendor/bin/pest tests/Feature/Identity/

# With coverage
./vendor/bin/pest --coverage
```

### Static Analysis

```bash
# Run PHPStan
./vendor/bin/phpstan analyse --memory-limit=512M --level=8
```

---

## 📚 API Documentation

### Base URL
```
http://localhost:8000/api/v1
```

### Authentication
All protected endpoints require `Authorization: Bearer {token}` header.

### Endpoints Summary

**Identity:**
- `POST /auth/register` - Register new user
- `POST /auth/login` - Login
- `GET /profiles/{uuid}` - Get profile
- `POST /follows/{uuid}` - Follow user

**Wallet:**
- `GET /wallet` - Get wallet balance
- `POST /gifts/send` - Send gift
- `GET /earnings` - Get creator earnings

**Streaming:**
- `GET /posts` - Get feed
- `POST /posts` - Create post
- `GET /posts/{uuid}` - Get post details

**Live Duels:**
- `GET /live-sessions` - List active sessions
- `POST /live-sessions` - Create duel
- `POST /live-sessions/{uuid}/join` - Join duel
- `POST /live-sessions/{uuid}/gift` - Send gift during duel

**Chat:**
- `GET /conversations` - List DMs
- `POST /conversations` - Start conversation
- `POST /chat-rooms` - Create public room
- `POST /chat-rooms/{uuid}/messages` - Send message

**Notifications:**
- `GET /notifications` - List notifications
- `GET /notifications/unread-count` - Unread count
- `POST /notifications/mark-read` - Mark as read

For full API documentation, see [AGENT_INSTRUCTIONS.md](AGENT_INSTRUCTIONS.md)

---

## 🧪 Testing

### Test Coverage
- **501 tests** across Unit and Feature tests
- **Identity Domain:** Auth, profiles, follows, notifications (148 tests)
- **Wallet Domain:** Ledger, payments, gifts (112 tests)
- **Streaming Domain:** Posts, feed (81 tests)
- **Duel Domain:** Live sessions, chat, scores (160 tests)

### Run Specific Test Suites

```bash
# Unit tests only
./vendor/bin/pest tests/Unit/

# Feature tests only
./vendor/bin/pest tests/Feature/

# Specific test file
./vendor/bin/pest tests/Feature/Identity/NotificationControllerTest.php
```

---

## 📂 Project Structure

```
ZaletApp/
├── app/
│   ├── Domains/          # Domain-driven modules
│   ├── Http/             # Controllers, Middleware, Requests
│   ├── Models/           # Base models
│   └── Providers/        # Service providers
├── config/               # Configuration files
├── database/
│   ├── factories/        # Model factories
│   ├── migrations/       # Database migrations
│   └── seeders/          # Database seeders
├── routes/
│   ├── api.php           # API routes
│   ├── channels.php      # Broadcast channels
│   └── web.php           # Web routes
├── tests/
│   ├── Feature/          # Feature tests
│   └── Unit/             # Unit tests
├── docker/               # Docker configuration
├── AGENT_INSTRUCTIONS.md # Detailed technical documentation
├── DEVELOPMENT_PLAN.md   # Original development roadmap
└── README.md             # This file
```

---

## 🎯 Next Steps

### Option 1: Frontend Development
- React 19 + TypeScript
- Shadcn/UI component library
- WebSocket integration for real-time features
- Responsive design with Tailwind CSS

### Option 2: Polish & Documentation
- Generate OpenAPI/Swagger specification
- Add performance monitoring
- Build admin panel for moderation
- Deployment guides and CI/CD

### Option 3: Advanced Features
- Tournament system for duels
- Leaderboards and rankings
- Replay system for completed duels
- Advanced analytics dashboard

---

## 📄 License

This project is proprietary and confidential.

---

## 🙏 Acknowledgments

Built with Laravel 11 and the amazing Laravel ecosystem.
