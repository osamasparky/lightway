# Architecture & Structural Audit Report — Meem LMS

**Audit Target:** Layer Boundaries, Middleware Architecture, Coupling, Service Boundaries, Anti-Patterns

---

## 1. Architectural Model & High-Level Topology

```text
Browser / Client (Vue.js + Vanilla JS + Blade UI)
       ↓
Nginx / Web Server (Static Asset Serving)
       ↓
Laravel Routing & Global Middleware Stack
       ├── SessionValidity, CheckRestriction, CheckMaintenance
       └── Share.php (Web) / AdminAuthenticate.php (Admin)  <-- [ARCHITECTURAL BOTTLENECK]
       ↓
Controllers (Heavy Logic & Direct DB Queries)
       ├── Direct Eloquent Queries (Missing Service Boundaries)
       └── Inline Transaction & Accounting Orchestration
       ↓
Models (Eloquent Models with Business & Calculation Logic)
       ├── Astrotomic Translatable Relations
       └── Sluggable & Morphic Traits
       ↓
Database (MySQL 8.0)
```

---

## 2. Structural Architecture Register

| Area | Structural Problem | Root Cause | Impact | Risk Level | Recommended Architecture |
| :--- | :--- | :--- | :--- | :---: | :--- |
| **Global Middleware** | Monolithic Data Sharing (`Share.php`) | Injecting dynamic business data into views at the HTTP middleware layer | High baseline latency on every route (45+ queries per hit) | 🚨 **CRITICAL** | Transition from middleware query sharing to scoped Laravel View Composers (`View::composer('layouts.app', ...)`). |
| **Presentation Layer** | Business and Database Logic in Blade | Views executing raw queries (`product_custom_badge.blade.php`, `bestTicket()`) | N+1 explosion, tight coupling between views and database schema | 🚨 **CRITICAL** | Enforce strict separation: Views must only receive pre-computed ViewModels / DTOs. |
| **Financial Subsystem** | Unmanaged Transaction Boundaries | Controllers directly invoking `Accounting::createAccounting(...)` across loops | Risk of partial ledger writes and balance desynchronization | 🚨 **CRITICAL** | Encapsulate order fulfillment inside a dedicated `OrderFulfillmentService` executing within atomic transactions. |
| **Admin Middleware** | Admin Sidebar Notification Polling | Middleware calling 11 individual count queries on every admin page load | Slows down every administrative action | 🟠 **HIGH** | Use cache-tagged aggregations or an asynchronous polling API for admin badge counts. |
| **Domain Layer** | Anemic Services / Overloaded Models | `Webinar.php` handling 20+ unrelated domain responsibilities | Code duplication, high cognitive load, difficult testing | 🟡 **MEDIUM** | Break out domain concerns into modular Service classes (`CourseProgressService`, `PricingEngine`). |

---

## 3. Detailed Architectural Anti-Patterns

### 1. Global Middleware Data Injection Anti-Pattern
In standard clean architecture, HTTP middleware is strictly reserved for request filtering (authentication, rate limiting, localization, CORS). 
In this application, `app/Http/Middleware/Share.php` acts as a monolithic data loader:
- Reads database categories
- Counts cart items and active discounts
- Reads floating bars
- Reads active purchase notifications
- Configures currencies and locales

**Solution:** Use Laravel View Composers scoped only to the specific partials that require them (e.g., `View::composer('web.default.includes.navbar', NavbarComposer::class)`), allowing static, API, or lightweight pages to execute with near-zero baseline overhead.
