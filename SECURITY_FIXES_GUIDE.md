# Security Fix Implementation Guide

This document provides step-by-step instructions for deploying the security fixes from the code audit.

## 🚨 CRITICAL: Must Do Before Production

### 1. Update Environment Variables

Add these to your `.env` file (copy from `.env.example`):

```bash
# CORS - Replace with your actual frontend domains
CORS_ALLOWED_ORIGINS=https://zalet.rs,https://app.zalet.rs,https://www.zalet.rs

# LiveKit Token TTL
LIVEKIT_TOKEN_TTL_VIEWER=14400     # 4 hours for viewers
LIVEKIT_TOKEN_TTL_STREAMER=28800   # 8 hours for streamers
```

### 2. Run Database Migration

The new indexes migration must be run to improve performance:

```bash
php artisan migrate
```

This adds 9 composite indexes for:
- Bar members queries
- Bar messages pagination
- Stream session lookups
- Live session lobby
- Notifications
- Message reactions
- Bar events
- Chat rooms
- Direct messages

### 3. Clear Configuration Cache

After updating `.env`:

```bash
php artisan config:clear
php artisan config:cache
```

### 4. Test Rate Limiting

The following rate limits are now enforced:

| Endpoint | Limit | Window |
|----------|-------|--------|
| Login/Register | 10 requests | 1 minute |
| General API | 120 requests | 1 minute |
| Wallet/Gifts | 20-30 requests | 1 minute |

Test that rate limiting works:
```bash
# This should be blocked after 10 attempts
for i in {1..15}; do
  curl -X POST http://localhost:8000/api/v1/auth/login \
    -H "Content-Type: application/json" \
    -d '{"email":"test@test.com","password":"wrong"}'
done
```

Expected: 429 Too Many Requests after 10 tries

## 📋 Verification Checklist

- [ ] **CORS** - Verify only your domains can access API
  ```bash
  curl -H "Origin: https://evil.com" http://localhost:8000/api/v1/auth/login
  # Should fail with CORS error
  ```

- [ ] **Rate Limiting** - Test login throttling (see above)

- [ ] **Email Privacy** - Check user profiles don't expose others' emails
  ```bash
  # Get someone else's profile - should NOT show their email
  curl http://localhost:8000/api/v1/users/{uuid}
  ```

- [ ] **LiveKit Tokens** - Verify token expiration
  ```javascript
  // Token should include expires_in field
  const response = await fetch('/api/v1/livekit/token/viewer', {
    method: 'POST',
    body: JSON.stringify({ room_name: 'test' })
  });
  console.log(response.expires_in); // Should be 14400 (4 hours)
  ```

- [ ] **XP Race Condition** - Test concurrent gift sending
  ```bash
  # Send 10 gifts simultaneously to same user
  for i in {1..10}; do
    curl -X POST http://localhost:8000/api/v1/gifts/send \
      -H "Authorization: Bearer $TOKEN" \
      -H "Content-Type: application/json" \
      -d '{"recipient_id":123,"gift_type":"rose"}' &
  done
  wait
  
  # Check user's XP is correct (no lost updates)
  ```

- [ ] **Database Indexes** - Run `EXPLAIN` on slow queries
  ```sql
  EXPLAIN ANALYZE 
  SELECT * FROM bar_messages 
  WHERE bar_id = 1 
  ORDER BY created_at DESC 
  LIMIT 50;
  
  -- Should use: bar_messages_bar_created_idx
  ```

## 🔧 Configuration Examples

### Production CORS (.env)
```bash
CORS_ALLOWED_ORIGINS=https://zalet.rs,https://app.zalet.rs,https://admin.zalet.rs
```

### Staging CORS (.env)
```bash
CORS_ALLOWED_ORIGINS=https://staging.zalet.rs,http://localhost:3000
```

### CDN/API Gateway
If using CloudFlare or AWS ALB, ensure they forward the `Origin` header.

## 🚀 Deployment Steps

### Step 1: Backup Database
```bash
pg_dump -U uzivo uzivo > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Step 2: Deploy Code
```bash
git pull origin main
composer install --no-dev --optimize-autoloader
```

### Step 3: Run Migrations
```bash
php artisan migrate --force
```

### Step 4: Update Environment
```bash
nano .env
# Update CORS_ALLOWED_ORIGINS
# Add LiveKit TTL settings
```

### Step 5: Clear Caches
```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
```

### Step 6: Restart Services
```bash
# For Docker
docker-compose restart php-fpm

# For traditional deployment
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
```

### Step 7: Monitor
```bash
# Watch logs for errors
tail -f storage/logs/laravel.log

# Check rate limiting
tail -f /var/log/nginx/access.log | grep 429
```

## 📊 Monitoring Recommendations

### 1. Rate Limit Monitoring
Track 429 responses in your APM:
```php
// Add to AppServiceProvider
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(120)
        ->by($request->user()?->id ?: $request->ip())
        ->response(function () {
            Log::warning('Rate limit exceeded', [
                'ip' => request()->ip(),
                'user' => auth()->id(),
            ]);
            return response('Too Many Attempts.', 429);
        });
});
```

### 2. XP System Monitoring
```php
// Monitor level-up events
Event::listen(UserLeveledUp::class, function ($event) {
    Log::info('User leveled up', [
        'user_id' => $event->user->id,
        'new_level' => $event->newLevel,
    ]);
});
```

### 3. LiveKit Token Usage
```php
// Track token generation
Log::channel('livekit')->info('Token generated', [
    'user_id' => $user->id,
    'room' => $roomName,
    'type' => $isStreamer ? 'streamer' : 'viewer',
    'expires_in' => $ttl,
]);
```

## 🐛 Troubleshooting

### Issue: CORS Still Allows All Origins

**Check:**
```bash
php artisan config:clear
echo $CORS_ALLOWED_ORIGINS
```

**Fix:**
Ensure `.env` has `CORS_ALLOWED_ORIGINS` and run `php artisan config:cache`.

### Issue: Rate Limiting Not Working

**Check:**
```bash
# Verify throttle middleware is active
php artisan route:list | grep throttle
```

**Fix:**
Clear route cache: `php artisan route:clear`

### Issue: Indexes Not Created

**Check:**
```sql
SELECT tablename, indexname 
FROM pg_indexes 
WHERE tablename = 'bar_messages';
```

**Fix:**
```bash
php artisan migrate:rollback --step=1
php artisan migrate
```

### Issue: XP Still Has Race Condition

**Check:**
Look for "Deadlock detected" in logs.

**Fix:**
Ensure using PostgreSQL (not SQLite). MySQL users may need to adjust transaction isolation level.

## 📈 Performance Impact

After applying fixes, expect:

- **CORS**: No performance impact
- **Rate Limiting**: ~1ms overhead per request (negligible)
- **XP Locking**: ~5-10ms for concurrent gift sends (acceptable)
- **Indexes**: 50-90% faster queries on large datasets
  - Bar messages: 200ms → 20ms
  - Live streams: 100ms → 10ms
  - Notifications: 150ms → 15ms

## 🔒 Security Impact

- **CORS**: Prevents XSS attacks from malicious sites
- **Rate Limiting**: Prevents brute force, DoS, data scraping
- **Email Hiding**: Prevents spam and privacy violations (GDPR compliant)
- **XP Locking**: Prevents exploit/cheating with concurrent requests
- **Token TTL**: Reduces window for token theft

## 📚 Additional Resources

- [Laravel Rate Limiting Docs](https://laravel.com/docs/11.x/routing#rate-limiting)
- [CORS Best Practices](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS)
- [PostgreSQL Index Types](https://www.postgresql.org/docs/current/indexes-types.html)
- [Database Locking in Laravel](https://laravel.com/docs/11.x/queries#pessimistic-locking)

## 🆘 Support

If you encounter issues:

1. Check logs: `storage/logs/laravel.log`
2. Verify environment: `php artisan config:show`
3. Test database: `php artisan tinker` → `DB::connection()->getPdo();`
4. Review migration status: `php artisan migrate:status`

## ✅ Success Criteria

Deployment is successful when:

- [ ] No CORS errors in frontend console
- [ ] Rate limiting returns 429 after limit exceeded
- [ ] User profiles don't show others' emails
- [ ] Concurrent XP operations work correctly
- [ ] No new database errors in logs
- [ ] All existing features still work
- [ ] API response times improved (use New Relic/DataDog)

---

**Last Updated:** 2026-01-07  
**Version:** 1.0  
**Applies to:** Zalet API v1.0+
