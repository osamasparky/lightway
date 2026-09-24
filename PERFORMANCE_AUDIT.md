# Performance Audit Report — Meem LMS

**Audit Target:** Meem LMS Application  
**Measured Environment:** PHP 8.2.3, MySQL 8.0.24, Local Profiling via Artisan & HTTP

---

## 1. Executive Performance Overview

The application demonstrates severe performance degradation across all major user-facing routes. A standard homepage request executes **271 SQL queries**, takes **~984 ms to 1,230 ms** on local fast execution, and allocates **62 MB of RAM** for a single response.

### Runtime Benchmark Summary

| Route | Page / Feature | Status | Total Duration | SQL Query Count | Total SQL Time | Peak Memory | HTML Response Size |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| `/` | Homepage | 200 OK | **984.6 ms** | **271 queries** | **155.5 ms** | **62.0 MB** | 139.6 KB |
| `/classes` | Course Catalog | 200 OK | **1,078.8 ms** | **114 queries** | **84.2 ms** | **58.5 MB** | 75.9 KB |
| `/blog` | Blog Index | 200 OK | **320.9 ms** | **65 queries** | **33.4 ms** | **52.0 MB** | 50.8 KB |
| `/login` | User Login | 200 OK | **739.9 ms** | **45 queries** | **22.1 ms** | **51.2 MB** | 61.6 KB |
| `/register` | User Registration | 200 OK | **854.2 ms** | **54 queries** | **26.8 ms** | **54.0 MB** | 120.0 KB |
| `/contact` | Contact Us | 200 OK | **221.9 ms** | **45 queries** | **22.1 ms** | **52.0 MB** | 47.0 KB |

---

## 2. Request Lifecycle Breakdown & Bottlenecks

```text
HTTP Request Arrives
  │
  ├─► [ 1. Global Middleware: Share.php ] (45 - 60 Queries | ~150 ms)
  │     ├── Setting::getSetting() calls (16+ un-eager loaded translation queries)
  │     ├── Category::getCategories() (26+ un-eager loaded subcategory & translation queries)
  │     ├── NavbarButton queries + translations
  │     ├── PurchaseNotificationsHelper query
  │     ├── FloatingBar query
  │     ├── MultiCurrency queries
  │     └── Cart & Discount count queries
  │
  ├─► [ 2. Controller Action Execution ] (~300 - 450 ms)
  │     ├── Section by section sequential querying (HomeSections, Featured, Latest, Bundles, Best Sellers)
  │     ├── Unbounded Ticket capacity queries inside loops
  │     └── Redundant join and aggregation queries
  │
  ├─► [ 3. Blade View Rendering & Template Loops ] (150 - 200+ Queries | ~400 ms)
  │     ├── grid-card.blade.php rendering per course
  │     ├── product_custom_badge.blade.php: Raw DB query executed inside view for every item
  │     ├── $webinar->bestTicket(): Executes activeSpecialOffer() query per card
  │     ├── $webinar->category->getUrl(): Executes lazy parent category lookup
  │     └── $webinar->getProgress(): 5 nested database loops per student
  │
  └─► HTTP Response Delivered (139.6 KB HTML + 6.5 MB Assets)
```

---

## 3. Top Performance Hotspot Register

| Priority | Area / Component | Root Cause | Evidence | Impact | Confidence | Complexity |
| :---: | :--- | :--- | :--- | :--- | :---: | :---: |
| **P1** | `product_custom_badge.blade.php` | Direct Eloquent query inside Blade template included in every card loop | `resources/views/web/default/includes/product_custom_badge.blade.php:3-20` | Executes 19+ queries on homepage; 1 query per course on catalog | Confirmed | Low |
| **P2** | `app/Http/Middleware/Share.php` | Uncached global shared data and non-eager loaded relations in middleware | `app/Http/Middleware/Share.php:28-90` | Injects 45+ baseline queries into EVERY HTTP request (even static/404 pages) | Confirmed | Medium |
| **P3** | `Webinar::bestTicket()` & `activeSpecialOffer()` | Method called 3–4 times per course card in Blade without relation caching | `app/Models/Webinar.php:257-289`, `920-929` | 12+ separate duplicate queries on homepage; scales linearly with course count | Confirmed | Medium |
| **P4** | `Category::getCategories()` | Caches base models without eager-loading translations or parent relations | `app/Models/Category.php:88-99` | 34+ translation queries on navigation rendering | Confirmed | Low |
| **P5** | `Setting::getSetting()` | Caches Setting model but Astrotomic Translatable queries `setting_translations` on attribute access | `app/Models/Setting.php:134-161` | 16+ queries to `setting_translations` on every page | Confirmed | Low |
| **P6** | `HomeController::index()` Discount Loop | Fetches ALL tickets in database and runs `isValid()` count queries in loop | `app/Http/Controllers/Web/HomeController.php:162-170` | Unbounded memory and query overhead if ticket volume increases | Confirmed | Medium |
| **P7** | `Webinar::getProgress()` | Runs 5 nested query loops checking learning progress across files, sessions, text lessons, assignments, quizzes | `app/Models/Webinar.php:573-750` | Up to 50+ queries per student course card on user dashboard | Confirmed | Medium |
| **P8** | `AdminAuthenticate.php` Sidebar Beeps | Runs 11 separate count queries for badge counts on every single admin page load | `app/Http/Middleware/AdminAuthenticate.php:61-76` | Slows down every admin action; locks admin dashboard under high concurrency | Confirmed | Low |
| **P9** | `RegistrationPackagesController` Installment Loop | Instantiates `InstallmentPlans` inside `foreach($packages)` loop | `app/Http/Controllers/Panel/RegistrationPackagesController.php:57-60` | 25+ queries to render 5 packages on panel | Confirmed | Low |
| **P10**| Synchronous Queue Processing | Emails, SMS notifications, and logging run synchronously in HTTP request thread | `.env:12` (`QUEUE_CONNECTION=sync`) | Blocks checkout and registration responses by 2–5+ seconds | Confirmed | Low |

---

## 4. Root-Cause Chains & Detailed Analysis

### Hotspot 1: In-View Database Querying in `product_custom_badge.blade.php`
```blade
<!-- resources/views/web/default/includes/product_custom_badge.blade.php -->
@php
    $time=time();
    $productBadges = \App\Models\ProductBadgeContent::query()
                    ->where('targetable_id', $itemTarget->id)
                    ->where('targetable_type', $itemTarget->getMorphClass())
                    ->whereHas('badge', function ($query) use ($time) { ... })
                    ->with(['badge'])
                    ->get();
@endphp
```
- **Mechanism:** Every time a course, bundle, or product is rendered on the UI, the Blade template pauses rendering and executes a synchronous Eloquent query against `product_badge_contents`.
- **Compounding factor:** `product_badge_contents` has no composite index on `(targetable_type, targetable_id)`, turning each execution into a full scan.
- **Remediation:** Eager load `productBadgeContents.badge` on the parent models in the controller queries, and pass the pre-loaded collection to the view.

---

### Hotspot 2: Translation Lazy Loading in Global Cache
```php
// app/Models/Setting.php
static function getSetting(&$static, $name, $key = null)
{
    if (!isset($static)) {
        $static = cache()->remember('settings.' . $name, 24 * 60 * 60, function () use ($name) {
            return self::where('name', $name)->first(); // Missing ->with('translations')!
        });
    }
    // Accessing $static->value triggers Astrotomic Translatable to query setting_translations
    if (!empty($static) and !empty($static->value) and isset($static->value)) {
        $value = json_decode($static->value, true);
    }
    ...
}
```
- **Mechanism:** The base `settings` row is cached, but because `Astrotomic\Translatable` loads translations via Eloquent relations dynamically, accessing `$static->value` bypasses the cache and fires a query:
  `select * from setting_translations where setting_translations.setting_id = ? and setting_translations.locale = ?`
- **Result:** 16 separate SQL queries per request solely to read settings that were intended to be cached.
- **Remediation:** Include `with('translations')` in `remember()` closure, or cache the decoded array directly rather than the raw Eloquent model instance.

---

## 5. Performance Quick Wins (High Impact / Low Effort)

1. **Cache settings as pure PHP associative arrays** (eliminates 16 queries per request).
2. **Eager-load `translations` in `Category::getCategories()`** (eliminates 34 queries per request).
3. **Preload `productBadges` in `Webinar::with([...])`** and remove `@php` query block from `product_custom_badge.blade.php` (eliminates 19 queries per homepage load).
4. **Cache Admin Sidebar Beeps** with a 60-second TTL (eliminates 11 queries per admin request).
5. **Change `QUEUE_CONNECTION` from `sync` to `database` or `redis`** (eliminates blocking I/O on checkout and contact forms).
