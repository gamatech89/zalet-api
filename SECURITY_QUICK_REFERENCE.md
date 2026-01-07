# Security & Performance Quick Reference

## 🚨 Critical Security Settings

### CORS Configuration
```bash
# .env - PRODUCTION
CORS_ALLOWED_ORIGINS=https://zalet.rs,https://app.zalet.rs

# .env - DEVELOPMENT
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8000
```

### API Rate Limits
| Route Pattern | Limit | Purpose |
|--------------|-------|---------|
| `/api/v1/auth/*` | 10/min | Prevent brute force |
| `/api/v1/*` (protected) | 120/min | General API |
| `/api/v1/wallet/*` | 20/min | Prevent abuse |
| `/api/v1/gifts/send` | 30/min | Prevent spam |

### Token Security
- **Viewer tokens**: 4 hours (14400 sec)
- **Streamer tokens**: 8 hours (28800 sec)
- Tokens include `expires_in` field
- Refresh before expiry

---

## 🔐 Secure Coding Patterns

### ✅ DO: Protect Email Privacy
```php
// UserResource.php
'email' => $this->when(
    $request->user()?->id === $this->id,
    $this->email
),
```

### ✅ DO: Use Database Locking for Critical Updates
```php
public function addXp(int $amount): bool
{
    return DB::transaction(function () use ($amount) {
        $userLevel = self::query()->lockForUpdate()->find($this->id);
        $userLevel->xp += $amount;
        // ... level up logic
        $userLevel->save();
        return $leveled;
    });
}
```

### ✅ DO: Validate All Inputs
```php
public function awardWatchXp(User $user, int $minutes): bool
{
    if ($minutes <= 0) {
        throw new \InvalidArgumentException('Minutes must be positive');
    }
    // ... rest of logic
}
```

### ❌ DON'T: Expose Sensitive Data
```php
// BAD
'email' => $this->email,  // Shows to everyone!

// GOOD
'email' => $this->when(
    $request->user()?->id === $this->id,
    $this->email
),
```

### ❌ DON'T: Update Without Locking
```php
// BAD - Race condition!
$this->xp += $amount;
$this->save();

// GOOD
DB::transaction(function () {
    $model = self::lockForUpdate()->find($this->id);
    $model->xp += $amount;
    $model->save();
});
```

---

## 🚀 Performance Best Practices

### Database Indexes (Already Added ✅)

```php
// These indexes are now in production:
bar_members(bar_id, user_id)           // Membership checks
bar_messages(bar_id, created_at)       // Message pagination
stream_sessions(status, is_public)     // Live stream queries
live_sessions(status, created_at)      // Lobby queries
notifications(user_id, read_at)        // Unread notifications
```

### Eager Loading (Avoid N+1)

```php
// ✅ GOOD - Eager load relationships
$bars = Bar::with(['owner.profile'])
    ->withCount('members')
    ->get();

// ❌ BAD - Loads all members (N+1 if 1000+ members)
$bar->load('members.user.profile');

// ✅ GOOD - Use separate paginated endpoint
// GET /api/v1/bars/{bar}/members
```

### Query Optimization

```php
// ✅ Use indexes
Bar::where('is_public', true)
    ->where('is_active', true)
    ->orderByDesc('member_count')
    ->get();

// ✅ Limit results
->limit(50)

// ✅ Use cursor pagination for large datasets
->cursorPaginate(50)
```

---

## 🧪 Testing Security

### 1. Test Rate Limiting
```bash
# Should get 429 after 10 attempts
for i in {1..15}; do
  curl -X POST http://localhost:8000/api/v1/auth/login \
    -d '{"email":"test@test.com","password":"wrong"}'
done
```

### 2. Test CORS
```bash
# Should fail from wrong origin
curl -H "Origin: https://evil.com" \
  http://localhost:8000/api/v1/users/123
```

### 3. Test Email Privacy
```bash
# Get another user's profile - should NOT show email
curl http://localhost:8000/api/v1/users/{other-user-uuid}
```

### 4. Test XP Race Condition
```php
// In test:
User::factory()->create();

// Send 10 gifts concurrently
$promises = [];
for ($i = 0; $i < 10; $i++) {
    $promises[] = $this->postJson('/api/v1/gifts/send', [
        'recipient_id' => $user->id,
        'gift_type' => 'rose',
    ]);
}

// XP should be correct (10 * rose_xp)
$user->refresh();
$this->assertEquals(expected_xp, $user->level->xp);
```

---

## 📊 Monitoring & Alerts

### Key Metrics to Track

1. **Rate Limit Hits**
   ```php
   // Log 429 responses
   if ($response->status() === 429) {
       Log::warning('Rate limit hit', [
           'user' => $request->user()?->id,
           'ip' => $request->ip(),
           'endpoint' => $request->path(),
       ]);
   }
   ```

2. **Failed Authentications**
   ```php
   Log::warning('Login failed', [
       'email' => $request->email,
       'ip' => $request->ip(),
   ]);
   ```

3. **XP Anomalies**
   ```php
   if ($xpGained > 10000) {
       Log::alert('Suspicious XP gain', [
           'user_id' => $user->id,
           'xp_gained' => $xpGained,
       ]);
   }
   ```

### Redis Monitoring
```bash
# Check rate limiter keys
redis-cli KEYS "*throttle*"

# Check cache hit rate
redis-cli INFO stats | grep hit_rate
```

---

## 🔧 Common Tasks

### Clear Rate Limit for Testing
```php
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::clear('login:' . $request->ip());
```

### Manually Lock User Level
```php
$userLevel = UserLevel::lockForUpdate()->find($id);
```

### Check Active Indexes
```sql
SELECT tablename, indexname, indexdef 
FROM pg_indexes 
WHERE schemaname = 'public' 
ORDER BY tablename, indexname;
```

### Force Token Expiry
```php
// Update config/livekit.php
'token_ttl_viewer' => 60, // 1 minute for testing
```

---

## 🐛 Common Issues & Fixes

### Issue: "Too Many Requests" in Development

**Cause:** Hit rate limit during testing

**Fix:**
```php
// Disable rate limiting in tests
$this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
```

### Issue: CORS Error After Deploy

**Cause:** Config cache not cleared

**Fix:**
```bash
php artisan config:clear
php artisan config:cache
```

### Issue: Emails Still Showing Publicly

**Cause:** Old cached response

**Fix:**
```bash
php artisan cache:clear
# Or clear specific user cache
Cache::forget("user.{$userId}");
```

### Issue: XP Lost on Concurrent Operations

**Cause:** Database doesn't support row locking (SQLite)

**Fix:** Use PostgreSQL (required for production)

---

## 📋 Pre-Deployment Checklist

- [ ] Updated `.env` with production CORS origins
- [ ] Verified `CORS_ALLOWED_ORIGINS` has NO wildcards
- [ ] Ran `php artisan migrate` (added indexes)
- [ ] Cleared all caches (`config:clear`, `route:clear`)
- [ ] Tested rate limiting on staging
- [ ] Verified emails hidden in API responses
- [ ] Checked XP calculations work correctly
- [ ] Reviewed logs for errors
- [ ] Backed up database
- [ ] Smoke tested all critical features

---

## 📞 Emergency Rollback

If issues occur in production:

```bash
# 1. Rollback migration
php artisan migrate:rollback --step=1

# 2. Revert CORS (emergency only - insecure!)
# In config/cors.php temporarily:
'allowed_origins' => ['*'],

# 3. Clear caches
php artisan config:clear

# 4. Restart services
docker-compose restart php-fpm
```

Then investigate and fix properly.

---

## 📚 Learn More

- [Laravel Security Best Practices](https://laravel.com/docs/11.x/security)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Database Indexing Guide](https://use-the-index-luke.com/)
- [API Rate Limiting Strategies](https://cloud.google.com/architecture/rate-limiting-strategies-techniques)

---

**Version:** 1.0  
**Last Updated:** 2026-01-07  
**Maintainer:** DevOps Team
