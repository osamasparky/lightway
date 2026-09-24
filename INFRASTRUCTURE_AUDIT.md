# Infrastructure & Hosting Audit Report — Meem LMS

**Audit Target:** Web Server, PHP-FPM, MySQL Configuration, OPcache, Caching Drivers, Queue Workers

---

## 1. Executive Infrastructure Summary

The staging environment is hosted on Plesk with Nginx reverse proxy + Apache/PHP-FPM and MySQL. While the server hardware is capable, key runtime configurations (synchronous queue execution, file-based sessions, unconfigured OPcache, and unindexed database queries) throttle the application under concurrency.

---

## 2. Server & Runtime Configuration Register

| Component | Current Configuration | Risk / Identified Limitation | Recommended Target Configuration |
| :--- | :--- | :--- | :--- |
| **Queue System** | `QUEUE_CONNECTION=sync` (`.env:12`) | 🚨 **CRITICAL**: Every email, SMS, push notification, and logging task runs synchronously during the HTTP request lifecycle. | Set `QUEUE_CONNECTION=database` or `redis` with a background daemon worker managed via Supervisor (`php artisan queue:work`). |
| **Session Driver** | `SESSION_DRIVER=file` (`.env:13`) | Disk I/O contention and session file locking under multi-user traffic spikes. | Transition to `SESSION_DRIVER=redis` or `cookie` for high-throughput concurrency. |
| **Cache Driver** | `CACHE_DRIVER=file` (`.env:11`) | File cache does not support atomic tagging (`Cache::tags()`), making selective invalidation difficult. | Use Redis cache driver (`CACHE_DRIVER=redis`) when scaling beyond a single web node. |
| **OPcache** | Default PHP CLI / FPM | If OPcache timestamps are rechecked on every request in production, filesystem overhead increases. | Set `opcache.enable=1`, `opcache.validate_timestamps=0` in production, with deployment-triggered cache resets. |
| **MySQL Engine** | MySQL 8.0 / InnoDB | `table_definition_cache` and buffer pool sizes need tuning according to database size. | Set `innodb_buffer_pool_size` to 60–70% of dedicated database RAM. |

---

## 3. Production Deployment & Queue Architecture

```text
[ Current Bottleneck Architecture ]
User Action (e.g., Buy Course)
       ↓
PHP-FPM Process
       ├── Database Updates
       ├── Send Email (SMTP Network Call - 1500ms)
       ├── Send SMS (API Network Call - 800ms)
       └── Render View
HTTP Response: ~3,000ms

[ Recommended Async Architecture ]
User Action (e.g., Buy Course)
       ↓
PHP-FPM Process
       ├── Database Updates
       └── Dispatch OrderPurchased Job to Queue (<5ms)
HTTP Response: ~150ms
       ↓
Background Queue Worker (Supervisor)
       ├── Process Send Email Job
       └── Process Send SMS Job
```
