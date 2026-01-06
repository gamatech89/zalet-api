# DEVELOPMENT PLAN: "UŽIVO" PLATFORM

> **Version:** 1.0.0  
> **Created:** 5 January 2026  
> **Philosophy:** Modular Monolith with Domain-Driven Design  
> **Payment Integration:** RaiAccept (Stubbed for Phase 1)

---

## TABLE OF CONTENTS

1. [Project Overview](#project-overview)
2. [Technical Stack](#technical-stack)
3. [Domain Architecture](#domain-architecture)
4. [Database Schema Overview](#database-schema-overview)
5. [Milestones](#milestones)
6. [Bottleneck Analysis & Mitigations](#bottleneck-analysis--mitigations)
7. [RaiAccept Integration Strategy](#raiaccept-integration-strategy)

---

## PROJECT OVERVIEW

**Uživo** is a diaspora-focused social streaming platform enabling:

-   Location-based content discovery (hometown + current city)
-   Real-time "Kafana" group chats via WebSockets
-   Live Duels between creators with gift-based scoring
-   Virtual credit economy with Raiffeisen Bank payment integration

**Target Users:** Serbian/Balkan diaspora communities worldwide

---

## TECHNICAL STACK

| Layer             | Technology               | Version |
| ----------------- | ------------------------ | ------- |
| **Backend**       | Laravel                  | 11.x    |
| **PHP**           | PHP                      | 8.3+    |
| **Frontend**      | React + TypeScript       | 19.x    |
| **UI Components** | Shadcn/UI + Tailwind CSS | Latest  |
| **State/Data**    | TanStack Query           | 5.x     |
| **Forms**         | React Hook Form + Zod    | Latest  |
| **WebSockets**    | Laravel Reverb           | 1.x     |
| **Auth**          | Laravel Sanctum          | 4.x     |
| **Database**      | PostgreSQL               | 16.x    |
| **Cache/Queue**   | Redis                    | 7.x     |
| **Search**        | Meilisearch              | 1.x     |

---

## DOMAIN ARCHITECTURE

```
app/
├── Domains/
│   ├── Identity/           # Users, Auth, Profiles, Locations, Follows
│   │   ├── Actions/
│   │   ├── Models/
│   │   ├── Resources/
│   │   ├── Requests/
│   │   └── Events/
│   │
│   ├── Wallet/             # Credits, Ledger, Payments, Gifts
│   │   ├── Actions/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Contracts/      # PaymentProviderInterface
│   │   └── Events/
│   │
│   ├── Streaming/          # Posts, Videos, Moments, Embeds
│   │   ├── Actions/
│   │   ├── Models/
│   │   ├── Resources/
│   │   └── Services/
│   │
│   └── Duel/               # Live Sessions, Chat, Real-time Events
│       ├── Actions/
│       ├── Models/
│       ├── Events/
│       └── Jobs/
│
├── Http/
│   ├── Controllers/
│   │   └── Api/            # Thin controllers → Domain Actions
│   └── Middleware/
│
├── Providers/
│   ├── DomainServiceProvider.php
│   └── BroadcastServiceProvider.php
│
└── Support/
    ├── Enums/
    └── Traits/
```

### Domain Boundaries

| Domain        | Responsibility                               | Key Aggregates                       |
| ------------- | -------------------------------------------- | ------------------------------------ |
| **Identity**  | User lifecycle, authentication, social graph | User, Profile, Follow                |
| **Wallet**    | Financial transactions, credits, payments    | Wallet (Aggregate Root), LedgerEntry |
| **Streaming** | Content creation, media metadata, feeds      | Post, VideoMetadata                  |
| **Duel**      | Real-time sessions, chat, live events        | LiveSession, DuelEvent, ChatRoom     |

---

## DATABASE SCHEMA OVERVIEW

### Core Tables

```sql
-- IDENTITY DOMAIN
users (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user' CHECK (role IN ('admin', 'creator', 'user')),
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

profiles (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    username VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NULL,
    bio TEXT NULL,
    avatar_url VARCHAR(500) NULL,
    origin_location_id BIGINT REFERENCES locations(id),
    current_location_id BIGINT REFERENCES locations(id),
    is_private BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

locations (
    id BIGSERIAL PRIMARY KEY,
    city VARCHAR(100) NOT NULL,
    country VARCHAR(100) NOT NULL,
    country_code CHAR(2) NOT NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(city, country_code)
);

follows (
    id BIGSERIAL PRIMARY KEY,
    follower_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    following_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    accepted_at TIMESTAMP NULL,  -- NULL = pending for private profiles
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(follower_id, following_id)
);

-- WALLET DOMAIN (Double-Entry Ledger)
wallets (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    balance BIGINT DEFAULT 0 CHECK (balance >= 0),  -- Credits (smallest unit)
    currency VARCHAR(10) DEFAULT 'CREDITS',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ledger_entries (
    id BIGSERIAL PRIMARY KEY,
    wallet_id BIGINT REFERENCES wallets(id) ON DELETE CASCADE,
    type VARCHAR(30) NOT NULL CHECK (type IN (
        'deposit', 'withdrawal', 'gift_sent', 'gift_received',
        'purchase', 'refund', 'adjustment'
    )),
    amount BIGINT NOT NULL,  -- Positive for credit, negative for debit
    balance_after BIGINT NOT NULL,
    reference_type VARCHAR(50) NULL,  -- Polymorphic: 'payment_intent', 'duel_event'
    reference_id BIGINT NULL,
    description TEXT NULL,
    meta JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

payment_intents (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    provider VARCHAR(30) DEFAULT 'raiaccept',
    provider_order_id VARCHAR(255) NULL,  -- RaiAccept orderIdentification
    provider_session_url TEXT NULL,       -- Payment form URL
    amount_cents BIGINT NOT NULL,         -- EUR cents
    credits_amount BIGINT NOT NULL,       -- Credits to grant
    currency CHAR(3) DEFAULT 'EUR',
    status VARCHAR(30) DEFAULT 'pending' CHECK (status IN (
        'pending', 'processing', 'completed', 'failed', 'refunded', 'cancelled'
    )),
    idempotency_key VARCHAR(64) UNIQUE NOT NULL,
    webhook_received_at TIMESTAMP NULL,
    meta JSONB DEFAULT '{}',  -- Full provider response
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- STREAMING DOMAIN
posts (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(20) NOT NULL CHECK (type IN ('video', 'short_clip', 'image')),
    title VARCHAR(255) NULL,
    description TEXT NULL,
    source_url TEXT NOT NULL,
    provider VARCHAR(30) NULL,  -- 'youtube', 'vimeo', 'mux', 'local'
    provider_id VARCHAR(100) NULL,
    thumbnail_url TEXT NULL,
    duration_seconds INT NULL,
    is_premium BOOLEAN DEFAULT FALSE,
    is_published BOOLEAN DEFAULT TRUE,
    published_at TIMESTAMP NULL,
    meta JSONB DEFAULT '{}',  -- Resolution, aspect ratio, etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- DUEL DOMAIN
chat_rooms (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    type VARCHAR(20) DEFAULT 'public_kafana' CHECK (type IN (
        'public_kafana', 'private', 'duel'
    )),
    location_id BIGINT REFERENCES locations(id) NULL,  -- For city-based kafanas
    max_participants INT DEFAULT 500,
    is_active BOOLEAN DEFAULT TRUE,
    meta JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

messages (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    chat_room_id BIGINT REFERENCES chat_rooms(id) ON DELETE CASCADE,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    content TEXT NOT NULL,
    type VARCHAR(20) DEFAULT 'text' CHECK (type IN ('text', 'gift', 'system')),
    meta JSONB DEFAULT '{}',  -- Gift details, mentions, etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

live_sessions (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    host_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    guest_id BIGINT REFERENCES users(id) NULL,
    chat_room_id BIGINT REFERENCES chat_rooms(id) NULL,
    status VARCHAR(20) DEFAULT 'waiting' CHECK (status IN (
        'waiting', 'active', 'paused', 'completed', 'cancelled'
    )),
    host_score BIGINT DEFAULT 0,
    guest_score BIGINT DEFAULT 0,
    winner_id BIGINT REFERENCES users(id) NULL,
    scheduled_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    duration_seconds INT NULL,
    meta JSONB DEFAULT '{}',  -- Stream URL, category, settings
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

duel_events (
    id BIGSERIAL PRIMARY KEY,
    live_session_id BIGINT REFERENCES live_sessions(id) ON DELETE CASCADE,
    event_type VARCHAR(30) NOT NULL CHECK (event_type IN (
        'gift_sent', 'user_joined', 'user_left', 'score_update',
        'pause', 'resume', 'host_ready', 'guest_ready'
    )),
    actor_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    target_id BIGINT REFERENCES users(id) NULL,  -- Recipient of gift
    payload JSONB NOT NULL DEFAULT '{}',  -- {gift_type, credits, score_delta}
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- INDEXES
CREATE INDEX idx_profiles_origin_location ON profiles(origin_location_id);
CREATE INDEX idx_profiles_current_location ON profiles(current_location_id);
CREATE INDEX idx_profiles_username ON profiles(username);
CREATE INDEX idx_follows_follower ON follows(follower_id);
CREATE INDEX idx_follows_following ON follows(following_id);
CREATE INDEX idx_ledger_wallet ON ledger_entries(wallet_id, created_at DESC);
CREATE INDEX idx_payment_intents_user ON payment_intents(user_id, status);
CREATE INDEX idx_payment_intents_provider ON payment_intents(provider_order_id);
CREATE INDEX idx_posts_user ON posts(user_id, is_published, created_at DESC);
CREATE INDEX idx_posts_type ON posts(type, is_published);
CREATE INDEX idx_messages_room ON messages(chat_room_id, created_at DESC);
CREATE INDEX idx_live_sessions_status ON live_sessions(status) WHERE status IN ('waiting', 'active');
CREATE INDEX idx_live_sessions_host ON live_sessions(host_id, status);
CREATE INDEX idx_duel_events_session ON duel_events(live_session_id, created_at DESC);
```

---

## MILESTONES

---

### MILESTONE 1: Project Scaffolding & Infrastructure

**Duration:** 3-4 days  
**Priority:** 🔴 Critical Path

#### Objectives

-   Initialize Laravel 11 project with strict PHP 8.3 configuration
-   Set up React 19 + TypeScript frontend with Vite
-   Establish Domain-Driven folder structure
-   Configure development environment (Docker, Redis, PostgreSQL)

#### Deliverables

**Backend:**

```
✅ Laravel 11 installation with strict typing
✅ Domain folder structure (Identity, Wallet, Streaming, Duel)
✅ Base Action class with transaction support
✅ Base Resource class with camelCase transformation
✅ PostgreSQL + Redis configuration
✅ PHPStan level 8 configuration
✅ Pest testing framework setup
```

**Frontend:**

```
✅ React 19 + TypeScript + Vite setup
✅ Tailwind CSS + Shadcn/UI installation
✅ TanStack Query configuration
✅ React Hook Form + Zod setup
✅ Folder structure: components/ui, components/shared, features/
✅ API client with Axios + interceptors
✅ ESLint + Prettier configuration
```

**Infrastructure:**

```
✅ docker-compose.yml (PHP-FPM, Nginx, PostgreSQL, Redis, Meilisearch)
✅ Makefile with common commands
✅ .env.example with all required variables
✅ GitHub Actions CI pipeline (lint, test)
```

#### Database Migrations

```
- 2026_01_01_000001_create_locations_table.php
- 2026_01_01_000002_create_users_table.php (extend default)
- 2026_01_01_000003_create_profiles_table.php
- 2026_01_01_000004_create_wallets_table.php
```

#### Acceptance Criteria

-   [ ] `make up` starts all services
-   [ ] `make test` runs PHPStan + Pest with 0 errors
-   [ ] `npm run dev` serves React app with hot reload
-   [ ] Domain folders exist with empty base classes
-   [ ] Database migrations run successfully

---

### MILESTONE 2: Identity & Authentication

**Duration:** 4-5 days  
**Priority:** 🔴 Critical Path  
**Domain:** Identity

#### Objectives

-   Implement Sanctum authentication (stateful web + token mobile)
-   Build User and Profile models with relationships
-   Create location search and selection system
-   Multi-step registration flow

#### Backend Deliverables

**Actions:**

```php
app/Domains/Identity/Actions/
├── RegisterUserAction.php       # Create user + profile + wallet
├── LoginUserAction.php          # Authenticate, return token
├── LogoutUserAction.php         # Revoke tokens
├── UpdateProfileAction.php      # Update profile fields
├── SearchLocationsAction.php    # Query locations table
└── SetUserLocationAction.php    # Update origin/current location
```

**Models:**

```php
app/Domains/Identity/Models/
├── User.php                     # Core user with role
├── Profile.php                  # Extended social data
└── Location.php                 # City/Country reference
```

**Resources:**

```php
app/Domains/Identity/Resources/
├── UserResource.php             # id, email, role, profile
├── ProfileResource.php          # username, bio, locations
└── LocationResource.php         # city, country, coordinates
```

**API Endpoints:**

```
POST   /api/auth/register        → RegisterUserAction
POST   /api/auth/login           → LoginUserAction
POST   /api/auth/logout          → LogoutUserAction
GET    /api/auth/me              → Current user + profile
PUT    /api/profile              → UpdateProfileAction
GET    /api/locations/search     → SearchLocationsAction (query param: q)
```

#### Frontend Deliverables

**Features:**

```
features/auth/
├── components/
│   ├── LoginForm.tsx
│   ├── RegisterForm.tsx         # Multi-step wizard
│   └── LocationSearchInput.tsx  # Autocomplete with debounce
├── hooks/
│   ├── useAuth.ts               # Auth state + mutations
│   └── useLocationSearch.ts     # TanStack Query search
└── pages/
    ├── LoginPage.tsx
    └── RegisterPage.tsx
```

#### Database Seeders

```
- LocationSeeder.php             # 500+ Balkan cities + major diaspora cities
- AdminUserSeeder.php            # Default admin account
```

#### Acceptance Criteria

-   [ ] User can register with email, password, username
-   [ ] Registration includes origin city selection with autocomplete
-   [ ] Login returns Sanctum token (cookie for web, bearer for API)
-   [ ] Protected routes return 401 without valid token
-   [ ] Profile displays selected hometown correctly

---

### MILESTONE 3: Social Graph & Follows

**Duration:** 3-4 days  
**Priority:** 🟡 High  
**Domain:** Identity

#### Objectives

-   Implement follower/following relationships
-   Support private profiles with follow requests
-   Build user discovery by location

#### Backend Deliverables

**Actions:**

```php
app/Domains/Identity/Actions/
├── FollowUserAction.php         # Create follow (pending if private)
├── UnfollowUserAction.php       # Remove follow relationship
├── AcceptFollowRequestAction.php
├── RejectFollowRequestAction.php
├── GetFollowersAction.php       # Paginated list
├── GetFollowingAction.php       # Paginated list
└── GetUsersByLocationAction.php # Discovery by city
```

**API Endpoints:**

```
POST   /api/users/{uuid}/follow       → FollowUserAction
DELETE /api/users/{uuid}/follow       → UnfollowUserAction
POST   /api/follow-requests/{id}/accept
POST   /api/follow-requests/{id}/reject
GET    /api/users/{uuid}/followers    → Paginated
GET    /api/users/{uuid}/following    → Paginated
GET    /api/users?location={id}       → Discovery
GET    /api/follow-requests           → Pending requests for current user
```

#### Frontend Deliverables

**Components:**

```
components/shared/
├── UserCard.tsx                 # Avatar, name, location, follow button
├── FollowButton.tsx             # State: follow/requested/following
└── UserList.tsx                 # Paginated list with infinite scroll

features/profile/
├── components/
│   ├── ProfileHeader.tsx        # Avatar, bio, stats
│   ├── FollowersList.tsx
│   └── FollowingList.tsx
└── pages/
    └── ProfilePage.tsx
```

#### Acceptance Criteria

-   [ ] User can follow/unfollow other users
-   [ ] Private profile follows require acceptance
-   [ ] Follow requests show in notifications
-   [ ] Follower/following counts display correctly
-   [ ] Users discoverable by hometown

---

### MILESTONE 4: Content & Posts

**Duration:** 4-5 days  
**Priority:** 🟡 High  
**Domain:** Streaming

#### Objectives

-   Implement polymorphic post system (video, short_clip, image)
-   YouTube/Vimeo URL parsing and embed
-   Build content feed with filtering

#### Backend Deliverables

**Actions:**

```php
app/Domains/Streaming/Actions/
├── CreatePostAction.php         # Create with media validation
├── UpdatePostAction.php
├── DeletePostAction.php
├── GetFeedAction.php            # Algorithmic feed
├── GetUserPostsAction.php       # Profile posts
└── ParseVideoUrlAction.php      # Extract provider + ID
```

**Services:**

```php
app/Domains/Streaming/Services/
├── VideoParserService.php       # YouTube, Vimeo regex extraction
└── ThumbnailService.php         # Generate thumbnail URLs
```

**API Endpoints:**

```
POST   /api/posts                → CreatePostAction
GET    /api/posts/{uuid}         → Single post with user
PUT    /api/posts/{uuid}         → UpdatePostAction
DELETE /api/posts/{uuid}         → DeletePostAction
GET    /api/feed                 → GetFeedAction (paginated)
GET    /api/users/{uuid}/posts   → GetUserPostsAction
```

#### Frontend Deliverables

**Components:**

```
features/feed/
├── components/
│   ├── PostCard.tsx             # Renders video/image based on type
│   ├── VideoEmbed.tsx           # YouTube/Vimeo iframe
│   ├── CreatePostModal.tsx      # URL input + validation
│   └── FeedFilters.tsx          # Type, location filters
├── hooks/
│   ├── useFeed.ts               # Infinite query
│   └── useCreatePost.ts
└── pages/
    └── FeedPage.tsx
```

#### Video Parser Patterns

```typescript
// YouTube: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID
// Vimeo: vimeo.com/ID, player.vimeo.com/video/ID
```

#### Acceptance Criteria

-   [ ] User can create post by pasting YouTube/Vimeo URL
-   [ ] Video thumbnail and duration auto-extracted
-   [ ] Feed displays posts from followed users
-   [ ] Posts filterable by type (video, moments, images)
-   [ ] Embedded videos play inline

---

### MILESTONE 5: Wallet Foundation & Ledger

**Duration:** 4-5 days  
**Priority:** 🔴 Critical Path  
**Domain:** Wallet

#### Objectives

-   Implement double-entry ledger system
-   Create Wallet as aggregate root with balance invariants
-   Build transaction history UI

#### Backend Deliverables

**Models:**

```php
app/Domains/Wallet/Models/
├── Wallet.php                   # Aggregate root
└── LedgerEntry.php              # Immutable transaction record
```

**Wallet Aggregate Methods:**

```php
class Wallet extends Model
{
    public function credit(int $amount, string $type, ?string $referenceType, ?int $referenceId): LedgerEntry
    public function debit(int $amount, string $type, ?string $referenceType, ?int $referenceId): LedgerEntry
    public function canDebit(int $amount): bool
    public function getBalanceAttribute(): int  // Computed from ledger OR cached
}
```

**Actions:**

```php
app/Domains/Wallet/Actions/
├── GetWalletAction.php          # Current balance + recent transactions
├── GetTransactionHistoryAction.php  # Paginated ledger
└── TransferCreditsAction.php    # Internal transfer (future: P2P)
```

**API Endpoints:**

```
GET    /api/wallet               → GetWalletAction
GET    /api/wallet/transactions  → GetTransactionHistoryAction (paginated)
```

#### Frontend Deliverables

**Components:**

```
features/wallet/
├── components/
│   ├── WalletBalance.tsx        # Large balance display
│   ├── TransactionList.tsx      # History with icons per type
│   ├── TransactionItem.tsx      # Single row
│   └── CreditPackageCard.tsx    # Buyable package
├── hooks/
│   ├── useWallet.ts             # Balance query
│   └── useTransactions.ts       # Paginated history
└── pages/
    └── WalletPage.tsx
```

#### Acceptance Criteria

-   [ ] Wallet created automatically on user registration
-   [ ] Balance never goes negative (DB constraint + app validation)
-   [ ] All balance changes create ledger entry
-   [ ] Transaction history shows type, amount, timestamp
-   [ ] Balance matches sum of ledger entries

---

### MILESTONE 6: RaiAccept Payment Integration (Stubbed)

**Duration:** 5-6 days  
**Priority:** 🔴 Critical Path  
**Domain:** Wallet

#### Objectives

-   Define `PaymentProviderInterface` contract
-   Implement stubbed RaiAccept service for development
-   Build payment intent flow with webhook handling
-   Prepare for production swap

#### Backend Deliverables

**Contracts:**

```php
app/Domains/Wallet/Contracts/PaymentProviderInterface.php

interface PaymentProviderInterface
{
    /**
     * Authenticate with payment provider and get access token
     */
    public function authenticate(): AuthToken;

    /**
     * Create order entry in provider system
     * Returns provider's order ID
     */
    public function createOrder(CreateOrderDTO $dto): OrderResponse;

    /**
     * Create payment session/form URL
     */
    public function createPaymentSession(CreateSessionDTO $dto): SessionResponse;

    /**
     * Verify webhook signature and parse payload
     */
    public function parseWebhook(Request $request): WebhookPayload;

    /**
     * Issue refund for a completed transaction
     */
    public function issueRefund(RefundDTO $dto): RefundResponse;

    /**
     * Get order details from provider
     */
    public function getOrderDetails(string $providerOrderId): OrderDetails;
}
```

**DTOs:**

```php
app/Domains/Wallet/DTOs/
├── CreateOrderDTO.php
│   - merchantOrderReference: string
│   - amount: int (cents)
│   - currency: string
│   - customerEmail: string
│   - customerReference: string
│   - successUrl: string
│   - failureUrl: string
│   - cancelUrl: string
│   - notificationUrl: string
│
├── CreateSessionDTO.php
│   - orderIdentification: string
│   - language: string (sr, en, de)
│
├── RefundDTO.php
│   - orderIdentification: string
│   - transactionId: string
│   - amount: int (cents, partial or full)
│
└── WebhookPayload.php
    - orderIdentification: string
    - transactionId: string
    - status: string
    - responseCode: string
    - amount: int
    - meta: array
```

**Stubbed Implementation:**

```php
app/Domains/Wallet/Services/StubRaiAcceptService.php

class StubRaiAcceptService implements PaymentProviderInterface
{
    // Simulates RaiAccept API responses
    // Configurable via config/services.php for different scenarios:
    // - 'success': Always succeeds
    // - 'failure': Always fails
    // - 'random': Random outcomes for testing

    public function createOrder(CreateOrderDTO $dto): OrderResponse
    {
        return new OrderResponse(
            orderIdentification: 'STUB-' . Str::uuid(),
            merchantOrderReference: $dto->merchantOrderReference,
            status: 'created'
        );
    }

    public function createPaymentSession(CreateSessionDTO $dto): SessionResponse
    {
        // Returns URL to internal stub payment page
        return new SessionResponse(
            sessionUrl: route('stub.payment.form', ['order' => $dto->orderIdentification]),
            expiresAt: now()->addHours(1)
        );
    }

    // Stub payment form controller simulates card entry and triggers webhook
}
```

**Actions:**

```php
app/Domains/Wallet/Actions/
├── InitiateCreditPurchaseAction.php   # Create PaymentIntent + provider order
├── ProcessPaymentWebhookAction.php    # Handle provider callback
├── CompleteCreditPurchaseAction.php   # Credit wallet on success
├── FailCreditPurchaseAction.php       # Mark failed, notify user
└── RequestRefundAction.php            # Initiate refund flow
```

**API Endpoints:**

```
POST   /api/wallet/purchase             → InitiateCreditPurchaseAction
       Body: { packageId: string }
       Response: { paymentUrl: string, intentId: string }

POST   /api/webhooks/raiaccept          → ProcessPaymentWebhookAction
       (Called by RaiAccept/stub)

GET    /api/wallet/purchase/{id}/status → Check PaymentIntent status

# Stub-only routes (dev environment)
GET    /stub/payment/{order}            → Stub payment form
POST   /stub/payment/{order}/complete   → Simulate success
POST   /stub/payment/{order}/fail       → Simulate failure
```

**Configuration:**

```php
// config/services.php
'raiaccept' => [
    'mode' => env('RAIACCEPT_MODE', 'stub'), // 'stub' | 'sandbox' | 'production'
    'client_id' => env('RAIACCEPT_CLIENT_ID'),
    'client_secret' => env('RAIACCEPT_CLIENT_SECRET'),
    'cognito_url' => env('RAIACCEPT_COGNITO_URL'),
    'api_url' => env('RAIACCEPT_API_URL', 'https://api.raiaccept.com'),
    'stub_behavior' => env('RAIACCEPT_STUB_BEHAVIOR', 'success'), // success|failure|random
],

// Credit packages
'credit_packages' => [
    ['id' => 'starter', 'credits' => 100, 'price_cents' => 500, 'currency' => 'EUR'],
    ['id' => 'popular', 'credits' => 500, 'price_cents' => 2000, 'currency' => 'EUR'],
    ['id' => 'premium', 'credits' => 1200, 'price_cents' => 4000, 'currency' => 'EUR'],
],
```

**Service Provider Binding:**

```php
// app/Providers/WalletServiceProvider.php
public function register(): void
{
    $this->app->bind(PaymentProviderInterface::class, function ($app) {
        return match (config('services.raiaccept.mode')) {
            'stub' => new StubRaiAcceptService(),
            'sandbox', 'production' => new RaiAcceptService(
                new CognitoAuthService(),
                new HttpClient()
            ),
        };
    });
}
```

#### Frontend Deliverables

**Components:**

```
features/wallet/
├── components/
│   ├── CreditPackageSelector.tsx    # Package cards with prices
│   ├── PaymentModal.tsx             # iframe or redirect handling
│   └── PurchaseStatusBadge.tsx      # Pending/Success/Failed
├── hooks/
│   └── useCreditPurchase.ts         # Mutation + polling
└── pages/
    ├── PurchasePage.tsx
    └── PurchaseResultPage.tsx       # Success/failure landing
```

**Stub Payment Form (Dev):**

```
features/wallet/
└── stub/
    └── StubPaymentForm.tsx          # Simulates RaiAccept card form
```

#### Webhook Flow Diagram

```
User clicks "Buy 100 Credits"
         │
         ▼
┌─────────────────────────────┐
│ InitiateCreditPurchaseAction │
│ - Create PaymentIntent       │
│ - Call provider.createOrder  │
│ - Call provider.createSession│
│ - Return payment URL         │
└─────────────────────────────┘
         │
         ▼
User redirected to payment form (iframe/redirect)
         │
         ▼
User enters card details → 3DS verification
         │
         ▼
┌─────────────────────────────┐
│ RaiAccept/Stub calls webhook │
│ POST /api/webhooks/raiaccept │
└─────────────────────────────┘
         │
         ▼
┌─────────────────────────────┐
│ ProcessPaymentWebhookAction  │
│ - Verify signature           │
│ - Find PaymentIntent         │
│ - Check idempotency          │
│ - Update status              │
└─────────────────────────────┘
         │
    ┌────┴────┐
    │         │
 Success   Failure
    │         │
    ▼         ▼
┌─────────┐ ┌──────────────────┐
│ Credit  │ │ FailCredit       │
│ Wallet  │ │ PurchaseAction   │
└─────────┘ └──────────────────┘
```

#### Idempotency Strategy

```php
// In ProcessPaymentWebhookAction
public function handle(WebhookPayload $payload): void
{
    $intent = PaymentIntent::where('provider_order_id', $payload->orderIdentification)
        ->lockForUpdate()
        ->firstOrFail();

    // Already processed? Skip
    if ($intent->webhook_received_at !== null) {
        Log::info('Duplicate webhook ignored', ['order' => $payload->orderIdentification]);
        return;
    }

    // Mark as received BEFORE processing
    $intent->update(['webhook_received_at' => now()]);

    // Process based on status...
}
```

#### Acceptance Criteria

-   [ ] User can select credit package and initiate purchase
-   [ ] Stub payment form displays (dev environment)
-   [ ] Completing stub payment triggers webhook
-   [ ] Wallet balance increases on successful payment
-   [ ] PaymentIntent status trackable
-   [ ] Duplicate webhooks handled idempotently
-   [ ] Transaction appears in wallet history

---

### MILESTONE 7: Gift System & Creator Earnings

**Duration:** 4-5 days  
**Priority:** 🟡 High  
**Domain:** Wallet + Duel

#### Objectives

-   Implement virtual gift catalog
-   Build gift sending flow with animations
-   Track creator earnings

#### Backend Deliverables

**Gift Catalog (Config-driven initially):**

```php
// config/gifts.php
return [
    'rakija' => ['name' => 'Rakija', 'credits' => 5, 'icon' => '🥃', 'animation' => 'bounce'],
    'rose' => ['name' => 'Ruža', 'credits' => 10, 'icon' => '🌹', 'animation' => 'float'],
    'heart' => ['name' => 'Srce', 'credits' => 25, 'icon' => '❤️', 'animation' => 'pulse'],
    'crown' => ['name' => 'Kruna', 'credits' => 100, 'icon' => '👑', 'animation' => 'sparkle'],
    'car' => ['name' => 'Auto', 'credits' => 500, 'icon' => '🚗', 'animation' => 'drive'],
];
```

**Actions:**

```php
app/Domains/Wallet/Actions/
├── SendGiftAction.php           # Debit sender, credit recipient
├── GetGiftCatalogAction.php     # Return available gifts
└── GetCreatorEarningsAction.php # Sum of received gifts

// SendGiftAction flow:
// 1. Validate sender has balance
// 2. DB Transaction:
//    - Debit sender wallet (gift_sent)
//    - Credit recipient wallet (gift_received)
//    - Create DuelEvent if in live session
// 3. Dispatch GiftSent event for broadcast
```

**Events:**

```php
app/Domains/Wallet/Events/
└── GiftSent.php
    - senderId: int
    - recipientId: int
    - giftType: string
    - credits: int
    - liveSessionId: ?int
```

**API Endpoints:**

```
GET    /api/gifts                    → GetGiftCatalogAction
POST   /api/gifts/send               → SendGiftAction
       Body: { recipientId, giftType, liveSessionId? }
GET    /api/earnings                 → GetCreatorEarningsAction (for creators)
```

#### Frontend Deliverables

**Components:**

```
features/gifts/
├── components/
│   ├── GiftCatalog.tsx          # Grid of gift icons
│   ├── GiftButton.tsx           # Single gift with price
│   ├── GiftAnimation.tsx        # Lottie/CSS animation overlay
│   └── GiftSentToast.tsx        # Confirmation
├── hooks/
│   ├── useGiftCatalog.ts
│   └── useSendGift.ts
└── context/
    └── GiftAnimationContext.tsx # Queue animations
```

#### Acceptance Criteria

-   [ ] Gift catalog displays with icons and prices
-   [ ] User can send gift if sufficient balance
-   [ ] Sender balance decreases, recipient increases
-   [ ] Gift animation plays on send
-   [ ] Transaction history shows gift sent/received
-   [ ] Insufficient balance shows error

---

### MILESTONE 8: Real-time Infrastructure (Laravel Reverb)

**Duration:** 4-5 days  
**Priority:** 🔴 Critical Path  
**Domain:** Duel

#### Objectives

-   Configure Laravel Reverb WebSocket server
-   Set up Echo client in React
-   Implement presence channels for live sessions
-   Establish <100ms broadcast target

#### Backend Deliverables

**Configuration:**

```php
// config/broadcasting.php
'reverb' => [
    'driver' => 'reverb',
    'app_id' => env('REVERB_APP_ID'),
    'app_key' => env('REVERB_APP_KEY'),
    'app_secret' => env('REVERB_APP_SECRET'),
    'options' => [
        'host' => env('REVERB_HOST', '127.0.0.1'),
        'port' => env('REVERB_PORT', 8080),
        'scheme' => env('REVERB_SCHEME', 'http'),
    ],
],

// routes/channels.php
Broadcast::channel('duel.{sessionUuid}', function (User $user, string $sessionUuid) {
    $session = LiveSession::where('uuid', $sessionUuid)->firstOrFail();

    if ($session->canUserJoin($user)) {
        return [
            'id' => $user->id,
            'name' => $user->profile->display_name,
            'avatar' => $user->profile->avatar_url,
        ];
    }

    return false;
});

Broadcast::channel('kafana.{roomUuid}', function (User $user, string $roomUuid) {
    // Similar presence channel for chat rooms
});
```

**Events:**

```php
app/Domains/Duel/Events/
├── DuelStarted.php              # Broadcast to duel.{uuid}
├── DuelGiftSent.php             # Real-time gift with animation data
├── DuelScoreUpdated.php         # Score bar sync
├── DuelEnded.php                # Winner announcement
├── UserJoinedDuel.php           # Presence update
└── UserLeftDuel.php

// Example: DuelGiftSent
class DuelGiftSent implements ShouldBroadcast
{
    public function __construct(
        public LiveSession $session,
        public User $sender,
        public User $recipient,
        public string $giftType,
        public int $credits,
        public int $newHostScore,
        public int $newGuestScore,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('duel.' . $this->session->uuid);
    }

    public function broadcastAs(): string
    {
        return 'gift.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->profile->display_name,
            ],
            'recipient' => $this->recipient->id === $this->session->host_id ? 'host' : 'guest',
            'gift' => [
                'type' => $this->giftType,
                'credits' => $this->credits,
                'animation' => config("gifts.{$this->giftType}.animation"),
            ],
            'scores' => [
                'host' => $this->newHostScore,
                'guest' => $this->newGuestScore,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }
}
```

**Performance Optimization:**

```php
// Use ShouldBroadcastNow for <100ms requirement
class DuelGiftSent implements ShouldBroadcastNow
{
    // Bypasses queue for instant broadcast
}

// Redis for score caching
// app/Domains/Duel/Services/DuelScoreService.php
class DuelScoreService
{
    public function incrementScore(LiveSession $session, string $target, int $amount): array
    {
        $key = "duel:{$session->id}:scores";

        // Atomic increment in Redis
        $newScore = Redis::hincrby($key, $target, $amount);

        return [
            'host' => (int) Redis::hget($key, 'host') ?? 0,
            'guest' => (int) Redis::hget($key, 'guest') ?? 0,
        ];
    }

    public function persistScores(LiveSession $session): void
    {
        $key = "duel:{$session->id}:scores";
        $scores = Redis::hgetall($key);

        $session->update([
            'host_score' => $scores['host'] ?? 0,
            'guest_score' => $scores['guest'] ?? 0,
        ]);
    }
}
```

#### Frontend Deliverables

**Echo Setup:**

```typescript
// lib/echo.ts
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

export const echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === "https",
    enabledTransports: ["ws", "wss"],
});
```

**Hooks:**

```typescript
features/realtime/
├── hooks/
│   ├── usePresenceChannel.ts    # Join/leave with user data
│   ├── useDuelChannel.ts        # Subscribe to duel events
│   └── useKafanaChannel.ts      # Subscribe to chat events
└── context/
    └── EchoContext.tsx          # Provide Echo instance
```

#### Acceptance Criteria

-   [ ] Reverb server starts with `php artisan reverb:start`
-   [ ] Echo client connects and authenticates
-   [ ] Presence channel shows online users
-   [ ] Events broadcast and receive in <100ms (local)
-   [ ] Reconnection handles gracefully

---

### MILESTONE 9: Kafana Chat System

**Duration:** 4-5 days  
**Priority:** 🟡 High  
**Domain:** Duel

#### Objectives

-   Implement city-based public chat rooms
-   Build real-time messaging with Laravel Reverb
-   Message persistence and history loading

#### Backend Deliverables

**Actions:**

```php
app/Domains/Duel/Actions/
├── GetChatRoomsAction.php       # List available kafanas
├── GetChatRoomAction.php        # Single room with recent messages
├── JoinChatRoomAction.php       # Add user to presence
├── LeaveChatRoomAction.php
├── SendMessageAction.php        # Persist + broadcast
└── GetChatHistoryAction.php     # Paginated messages
```

**Events:**

```php
app/Domains/Duel/Events/
├── MessageSent.php              # ShouldBroadcastNow
├── UserJoinedRoom.php
└── UserLeftRoom.php
```

**API Endpoints:**

```
GET    /api/chat-rooms                    → GetChatRoomsAction
GET    /api/chat-rooms/{uuid}             → GetChatRoomAction
POST   /api/chat-rooms/{uuid}/join        → JoinChatRoomAction
POST   /api/chat-rooms/{uuid}/leave       → LeaveChatRoomAction
POST   /api/chat-rooms/{uuid}/messages    → SendMessageAction
GET    /api/chat-rooms/{uuid}/messages    → GetChatHistoryAction (cursor pagination)
```

#### Frontend Deliverables

**Components:**

```
features/chat/
├── components/
│   ├── ChatRoomList.tsx         # List of kafanas
│   ├── ChatRoom.tsx             # Full chat interface
│   ├── MessageList.tsx          # Virtualized message list
│   ├── MessageBubble.tsx        # Single message
│   ├── ChatInput.tsx            # Input with send button
│   └── OnlineUsersList.tsx      # Presence sidebar
├── hooks/
│   ├── useChatRoom.ts           # Room data + messages
│   ├── useSendMessage.ts        # Optimistic update
│   └── useChatPresence.ts       # Online users
└── pages/
    └── KafanaPage.tsx
```

**Optimistic Updates:**

```typescript
// useSendMessage.ts
const mutation = useMutation({
    mutationFn: sendMessage,
    onMutate: async (newMessage) => {
        // Cancel outgoing refetches
        await queryClient.cancelQueries(["messages", roomId]);

        // Snapshot previous
        const previous = queryClient.getQueryData(["messages", roomId]);

        // Optimistically add message
        queryClient.setQueryData(["messages", roomId], (old) => ({
            ...old,
            messages: [...old.messages, { ...newMessage, pending: true }],
        }));

        return { previous };
    },
    onError: (err, newMessage, context) => {
        queryClient.setQueryData(["messages", roomId], context.previous);
    },
    onSettled: () => {
        queryClient.invalidateQueries(["messages", roomId]);
    },
});
```

#### Database Seeders

```
- ChatRoomSeeder.php             # Create kafanas for major cities
  - "Beograd Kafana"
  - "Novi Sad Kafana"
  - "Frankfurt Dijaspora"
  - "Wien Balkan"
  - etc.
```

#### Acceptance Criteria

-   [ ] User can browse available kafana rooms
-   [ ] Joining room shows in presence list
-   [ ] Messages appear instantly for all users
-   [ ] Chat history loads on scroll up
-   [ ] Typing indicators work (optional)
-   [ ] Message persistence survives refresh

---

### MILESTONE 10: Live Duels MVP

**Duration:** 6-7 days  
**Priority:** 🔴 Critical Path  
**Domain:** Duel + Wallet

#### Objectives

-   Implement full duel lifecycle (create → join → active → end)
-   Real-time gift scoring with <100ms broadcasts
-   Score bar visualization
-   Winner determination

#### Backend Deliverables

**Actions:**

```php
app/Domains/Duel/Actions/
├── CreateDuelAction.php         # Host creates session
├── JoinDuelAction.php           # Guest joins
├── StartDuelAction.php          # Both ready, begin
├── SendDuelGiftAction.php       # Gift + score update + broadcast
├── PauseDuelAction.php          # Host pauses
├── ResumeDuelAction.php
├── EndDuelAction.php            # Determine winner, persist scores
├── GetActiveDuelsAction.php     # Discovery
└── GetDuelAction.php            # Single duel state
```

**SendDuelGiftAction Flow:**

```php
class SendDuelGiftAction
{
    public function __construct(
        private DuelScoreService $scoreService,
        private SendGiftAction $sendGiftAction,
    ) {}

    public function execute(
        User $sender,
        LiveSession $session,
        string $giftType,
        string $target // 'host' | 'guest'
    ): void {
        $recipient = $target === 'host' ? $session->host : $session->guest;
        $credits = config("gifts.{$giftType}.credits");

        DB::transaction(function () use ($sender, $recipient, $session, $giftType, $credits, $target) {
            // 1. Execute wallet transfer
            $this->sendGiftAction->execute($sender, $recipient, $giftType, $session->id);

            // 2. Atomic score increment in Redis
            $scores = $this->scoreService->incrementScore($session, $target, $credits);

            // 3. Record event for audit
            DuelEvent::create([
                'live_session_id' => $session->id,
                'event_type' => 'gift_sent',
                'actor_id' => $sender->id,
                'target_id' => $recipient->id,
                'payload' => [
                    'gift_type' => $giftType,
                    'credits' => $credits,
                    'score_delta' => $credits,
                    'target' => $target,
                ],
            ]);

            // 4. Broadcast immediately (ShouldBroadcastNow)
            broadcast(new DuelGiftSent(
                session: $session,
                sender: $sender,
                recipient: $recipient,
                giftType: $giftType,
                credits: $credits,
                newHostScore: $scores['host'],
                newGuestScore: $scores['guest'],
            ));
        });
    }
}
```

**EndDuelAction Flow:**

```php
class EndDuelAction
{
    public function execute(LiveSession $session, User $actor): LiveSession
    {
        // Only host can end
        if ($actor->id !== $session->host_id) {
            throw new UnauthorizedException();
        }

        // Persist Redis scores to DB
        $this->scoreService->persistScores($session);

        // Refresh from DB
        $session->refresh();

        // Determine winner
        $winnerId = match (true) {
            $session->host_score > $session->guest_score => $session->host_id,
            $session->guest_score > $session->host_score => $session->guest_id,
            default => null, // Tie
        };

        $session->update([
            'status' => 'completed',
            'ended_at' => now(),
            'winner_id' => $winnerId,
            'duration_seconds' => $session->started_at->diffInSeconds(now()),
        ]);

        // Record event
        DuelEvent::create([
            'live_session_id' => $session->id,
            'event_type' => 'duel_ended',
            'actor_id' => $actor->id,
            'payload' => [
                'winner_id' => $winnerId,
                'final_scores' => [
                    'host' => $session->host_score,
                    'guest' => $session->guest_score,
                ],
            ],
        ]);

        // Broadcast end
        broadcast(new DuelEnded($session, $winnerId));

        // Cleanup Redis
        Redis::del("duel:{$session->id}:scores");

        return $session;
    }
}
```

**API Endpoints:**

```
POST   /api/duels                        → CreateDuelAction
GET    /api/duels                        → GetActiveDuelsAction
GET    /api/duels/{uuid}                 → GetDuelAction
POST   /api/duels/{uuid}/join            → JoinDuelAction
POST   /api/duels/{uuid}/start           → StartDuelAction
POST   /api/duels/{uuid}/gift            → SendDuelGiftAction
       Body: { giftType: string, target: 'host' | 'guest' }
POST   /api/duels/{uuid}/pause           → PauseDuelAction
POST   /api/duels/{uuid}/resume          → ResumeDuelAction
POST   /api/duels/{uuid}/end             → EndDuelAction
```

#### Frontend Deliverables

**Components:**

```
features/duel/
├── components/
│   ├── DuelLobby.tsx            # Waiting for guest
│   ├── DuelArena.tsx            # Active duel view
│   ├── ScoreBar.tsx             # Animated host vs guest bar
│   ├── DuelGiftPanel.tsx        # Gift buttons
│   ├── DuelParticipant.tsx      # Host/Guest video + score
│   ├── DuelWinner.tsx           # End screen
│   └── ActiveDuelsList.tsx      # Discovery
├── hooks/
│   ├── useDuel.ts               # Duel state
│   ├── useDuelChannel.ts        # Real-time subscriptions
│   └── useSendDuelGift.ts       # Gift mutation
└── pages/
    ├── DuelListPage.tsx
    ├── CreateDuelPage.tsx
    └── DuelPage.tsx             # Main duel view
```

**ScoreBar Animation:**

```typescript
// ScoreBar.tsx
const ScoreBar: React.FC<{ hostScore: number; guestScore: number }> = ({
    hostScore,
    guestScore,
}) => {
    const total = hostScore + guestScore || 1;
    const hostPercent = (hostScore / total) * 100;

    return (
        <div className="relative h-8 w-full rounded-full overflow-hidden bg-gray-200">
            <motion.div
                className="absolute left-0 h-full bg-blue-500"
                initial={{ width: "50%" }}
                animate={{ width: `${hostPercent}%` }}
                transition={{ type: "spring", stiffness: 300, damping: 30 }}
            />
            <motion.div
                className="absolute right-0 h-full bg-red-500"
                initial={{ width: "50%" }}
                animate={{ width: `${100 - hostPercent}%` }}
                transition={{ type: "spring", stiffness: 300, damping: 30 }}
            />
            <div className="absolute inset-0 flex justify-between items-center px-4 text-white font-bold">
                <span>{hostScore}</span>
                <span>{guestScore}</span>
            </div>
        </div>
    );
};
```

**Real-time Event Handling:**

```typescript
// useDuelChannel.ts
export const useDuelChannel = (sessionUuid: string) => {
    const queryClient = useQueryClient();

    useEffect(() => {
        const channel = echo.join(`duel.${sessionUuid}`);

        channel
            .listen(".gift.sent", (event: DuelGiftEvent) => {
                // Update scores immediately
                queryClient.setQueryData(
                    ["duel", sessionUuid],
                    (old: Duel) => ({
                        ...old,
                        hostScore: event.scores.host,
                        guestScore: event.scores.guest,
                    })
                );

                // Trigger gift animation
                triggerGiftAnimation(event.gift);
            })
            .listen(".duel.ended", (event: DuelEndedEvent) => {
                queryClient.setQueryData(
                    ["duel", sessionUuid],
                    (old: Duel) => ({
                        ...old,
                        status: "completed",
                        winnerId: event.winnerId,
                    })
                );
            })
            .here((users: User[]) => {
                // Initial presence
            })
            .joining((user: User) => {
                // User joined
            })
            .leaving((user: User) => {
                // User left
            });

        return () => {
            echo.leave(`duel.${sessionUuid}`);
        };
    }, [sessionUuid, queryClient]);
};
```

#### Acceptance Criteria

-   [ ] Host can create duel and get shareable link
-   [ ] Guest can join via link
-   [ ] Duel starts when both ready
-   [ ] Gifts update score bar in real-time (<100ms)
-   [ ] Gift animations play for all viewers
-   [ ] Score persists if page refreshed
-   [ ] Host can end duel, winner announced
-   [ ] Duel history shows in profiles

---

## BOTTLENECK ANALYSIS & MITIGATIONS

### Live Duel Bottlenecks

| Bottleneck                       | Risk                                                   | Mitigation                                                                       |
| -------------------------------- | ------------------------------------------------------ | -------------------------------------------------------------------------------- |
| **Race conditions on score**     | Two gifts processed simultaneously corrupt score       | Use Redis `HINCRBY` for atomic increments; persist to DB only on duel end        |
| **<100ms broadcast target**      | Queue delay exceeds target                             | Use `ShouldBroadcastNow` to bypass queue; process gifts synchronously            |
| **WebSocket disconnect**         | User loses duel state                                  | Store state in Redis with TTL; reconnection fetches current state from API       |
| **Database write amplification** | Every gift = 4 writes (2 ledger + 1 event + 1 session) | Batch ledger writes; async job for non-critical writes                           |
| **Matchmaking void**             | No clear matching algorithm                            | Start with "challenge by link" (no algorithm needed); queue-based matching in v2 |

### Raiffeisen Payment Bottlenecks

| Bottleneck                  | Risk                                | Mitigation                                                                     |
| --------------------------- | ----------------------------------- | ------------------------------------------------------------------------------ |
| **Single provider lock-in** | RaiAccept downtime = no purchases   | `PaymentProviderInterface` allows Stripe fallback; feature flag for provider   |
| **Webhook idempotency**     | Duplicate webhook = double credits  | `webhook_received_at` timestamp checked before processing; DB lock             |
| **Currency mismatch**       | EUR payments, RSD display confusion | Always process in EUR cents internally; display conversion at UI layer         |
| **KYC for payouts**         | Creators can't withdraw             | Add `verification_status` to profiles; payout blocked until verified (Phase 2) |
| **Token expiry**            | Cognito token expires mid-session   | Token refresh logic in `RaiAcceptService`; cache token with TTL                |

### General Performance Bottlenecks

| Bottleneck               | Risk                         | Mitigation                                                                  |
| ------------------------ | ---------------------------- | --------------------------------------------------------------------------- |
| **N+1 queries**          | Feed/chat loads slowly       | Eager load relationships; use `JsonResource` with consistent includes       |
| **Large chat rooms**     | 500+ users = broadcast storm | Debounce presence updates; paginate participant list; consider sharding     |
| **Gift animation queue** | Multiple gifts stack up      | Client-side animation queue with max concurrent; drop oldest if overwhelmed |

---

## RAIACCEPT INTEGRATION STRATEGY

### Phase 1: Stubbed (Current)

-   `StubRaiAcceptService` implements `PaymentProviderInterface`
-   Internal routes simulate payment form
-   Configurable success/failure scenarios
-   Full webhook flow tested

### Phase 2: Sandbox

-   Switch `RAIACCEPT_MODE=sandbox`
-   Real API calls to sandbox environment
-   Test with provided test card numbers
-   Verify 3DS flow

### Phase 3: Production

-   Switch `RAIACCEPT_MODE=production`
-   Production credentials from Merchant Portal
-   Enable real EUR transactions
-   Monitor via Merchant Portal dashboard

### API Flow Reference

```
1. Authenticate
   POST https://cognito-idp.{region}.amazonaws.com/
   → Access Token (1 hour TTL)

2. Create Order
   POST https://api.raiaccept.com/api/v1/order
   Headers: Authorization: Bearer {token}
   Body: {
       merchantOrderReference,
       invoice: { amount, currency },
       customer: { email, customerReference },
       urls: { successUrl, failureUrl, cancelUrl, notificationUrl }
   }
   → { orderIdentification, ... }

3. Create Payment Session
   POST https://api.raiaccept.com/api/v1/checkout/session
   Body: { orderIdentification, language }
   → { sessionUrl }

4. Redirect user to sessionUrl

5. User completes payment

6. Webhook received at notificationUrl
   POST /api/webhooks/raiaccept
   Body: { orderIdentification, transactionId, status, responseCode, ... }

7. Verify via Get Order Details (optional)
   GET https://api.raiaccept.com/api/v1/order/{orderIdentification}
```

---

## NEXT STEPS

1. **Approve this plan** → Begin Milestone 1
2. **Clarify any requirements** → Update AGENT_INSTRUCTIONS.md
3. **Set up development environment** → Docker + local services
4. **Initialize repositories** → Backend (Laravel) + Frontend (React)

---

_This document will be updated as milestones progress. Each milestone completion will include a brief status update._
