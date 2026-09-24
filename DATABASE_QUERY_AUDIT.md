# Database & SQL Audit Report — Meem LMS

**Audit Target:** Database Schemas, Indexes, Query Patterns, Raw SQL, and N+1 Cascades  
**Database Engine:** MySQL 8.0.24 (InnoDB)  
**Total Tables Audited:** 180+ Tables

---

## 1. Executive Summary

The database architecture contains rich relationships but exhibits significant performance bottlenecks under production loads:
1. **Critical Polymorphic Tables Lack Proper Indexes:** Tables such as `product_badge_contents` have no index on `(targetable_type, targetable_id)`.
2. **Heavy Traffic Tables Lack Composite Filter Indexes:** High-volume queries filtering by `status`, `private`, and sorting by `updated_at` perform table scans and filesorts.
3. **N+1 Cascades in Application & Views:** High query amplification due to lazy-loaded relations inside Blade loops and model accessor methods.
4. **Direct String Interpolation in Raw Queries:** Parameter values concatenated into `whereRaw` expressions rather than using prepared statement parameter binding.

---

## 2. Comprehensive SQL Query Register

| # | File & Line | Query Pattern | Identified Problem | Severity | Recommended Fix |
| :- | :--- | :--- | :--- | :---: | :--- |
| **1** | `resources/views/web/default/includes/product_custom_badge.blade.php:3-20` | `select * from product_badge_contents where targetable_id = ? and targetable_type = ?` | Executed inside Blade view inside card loops (19+ times on homepage) | 🚨 **CRITICAL** | Eager load `productBadgeContents` in controller queries; pass collection to view. |
| **2** | `app/Models/Webinar.php:922-926` | `select * from special_offers where webinar_id = ? and status = 'active' and from_date < ? and to_date > ? limit 1` | Called via `bestTicket()` 3–4 times per course card without memoization | 🚨 **CRITICAL** | Eager load `specialOffers` relationship and filter in memory via collection method. |
| **3** | `app/Models/Category.php:88-99` | `select * from category_translations where category_id = ? and locale = ?` | Fired 34+ times per request because `getCategories()` does not eager load `translations` or `subCategories.translations` | 🚨 **CRITICAL** | Update `getCategories()` closure with `with(['translations', 'subCategories.translations'])`. |
| **4** | `app/Models/Setting.php:137-146` | `select * from setting_translations where setting_id = ? and locale = ?` | Model cached without relation; attribute accessor triggers separate query per setting | 🚨 **CRITICAL** | Cache serialized array of translated values instead of raw Eloquent model. |
| **5** | `app/Http/Controllers/Web/InstructorFinderController.php:294-298` | `whereRaw('value >= ' . $minAge)` | **SQL Injection Risk**: Direct request variable concatenation into `whereRaw` | 🚨 **CRITICAL** | Use parameterized bindings: `whereRaw('value >= ?', [(int)$minAge])` or `where('value', '>=', (int)$minAge)`. |
| **6** | `app/Http/Controllers/Admin/traits/InstallmentOverdueTrait.php:45` | `whereRaw("((selected_installment_steps.deadline * 86400) + installment_orders.created_at) < {$time}")` | String interpolation into raw SQL expression | 🟠 **HIGH** | Use prepared statement bindings: `whereRaw('((selected_installment_steps.deadline * 86400) + installment_orders.created_at) < ?', [$time])`. |
| **7** | `app/Http/Controllers/Web/HomeController.php:162-170` | `select * from tickets where start_date < ? and end_date > ?` + `select count(*) from ticket_users where ticket_id = ?` | Unbounded retrieval of all tickets followed by N+1 capacity queries in PHP loop | 🟠 **HIGH** | Replace PHP loop with SQL query using `whereHas` or `withCount('ticketUsers')` and limit. |
| **8** | `app/Models/Webinar.php:573-750` | `select * from course_learning where user_id = ? and file_id = ?` (and 4 other tables) | 5 nested loops querying per-item completion status on course progress | 🟠 **HIGH** | Fetch all completed item IDs for the user in a single `whereIn` query per table. |
| **9** | `app/Http/Controllers/Panel/RegistrationPackagesController.php:57-60` | `select * from installments ...` inside `foreach($packages)` | Repeated instantiation of `InstallmentPlans` and un-eager loaded queries in loop | 🟠 **HIGH** | Load installment plans in a single batch query for all package IDs. |
| **10**| `app/Http/Middleware/AdminAuthenticate.php:61-76` | 11 separate `count(*)` queries on comments, webinars, bundles, reviews, offline payments | Executed on every admin panel request synchronously | 🟡 **MEDIUM** | Consolidate queries or cache sidebar beep counts with Redis/cache tags. |

---

## 3. Database Index Audit & Recommendations

### Existing vs Recommended Indexes

```text
Table: product_badge_contents
  Current Indexes:
    - PRIMARY (id)
    - foreign key (product_badge_id)
  Missing Critical Index:
    - (targetable_type, targetable_id)
  Benefit: Eliminates full table scans on every card badge lookup across the application.
  Downside: Minimal write overhead during badge assignment.
```

```text
Table: webinars
  Current Indexes:
    - PRIMARY (id)
    - unique (slug)
    - foreign key (teacher_id)
    - foreign key (category_id)
    - foreign key (creator_id)
  Missing Critical Composite Indexes:
    - (status, private, updated_at)
    - (status, category_id, updated_at)
    - (teacher_id, status)
  Benefit: Accelerates homepage, category listing, and search filters from filesort table scans to index range scans.
```

```text
Table: sales
  Current Indexes:
    - PRIMARY (id)
    - foreign keys (order_id, webinar_id, meeting_id, ticket_id, buyer_id, seller_id)
  Missing Critical Composite Indexes:
    - (buyer_id, access_to_purchased_item, refund_at)
    - (webinar_id, type, refund_at)
  Benefit: Speeds up user purchase checks (`checkUserHasBought()`) and sales revenue aggregation.
```

```text
Table: special_offers
  Current Indexes:
    - PRIMARY (id)
    - foreign keys (webinar_id, bundle_id, subscribe_id)
  Missing Critical Composite Index:
    - (webinar_id, status, from_date, to_date)
  Benefit: Optimizes `activeSpecialOffer()` queries when looking up active promotional discounts.
```

```text
Table: course_learning
  Current Indexes:
    - PRIMARY (id)
    - foreign keys (user_id, text_lesson_id, file_id, session_id)
  Missing Composite Indexes:
    - UNIQUE (user_id, file_id)
    - UNIQUE (user_id, session_id)
    - UNIQUE (user_id, text_lesson_id)
  Benefit: Prevents duplicate completion records and enables single-step index lookups for progress tracking.
```

---

## 4. Execution Plan (EXPLAIN) Analysis

### Suspicious Query Pattern: Best Rate Courses on Homepage
```sql
EXPLAIN SELECT webinars.*, avg(rates) as avg_rates 
FROM webinars 
JOIN webinar_reviews ON webinars.id = webinar_reviews.webinar_id AND webinar_reviews.status = 'active'
WHERE webinars.private = 0 AND webinars.status = 'active' AND webinar_reviews.rates IS NOT NULL 
GROUP BY webinars.id 
ORDER BY avg_rates DESC 
LIMIT 6;
```
- **Execution Plan Characteristics:**
  - `Using temporary; Using filesort` on `webinars` table.
  - Requires scanning all reviews and grouping across all active courses before sorting.
- **Risk Under Scale:** At 10,000+ courses and 100,000+ reviews, this query will cause multi-second disk I/O spikes.
- **Recommendation:** Denormalize average course rating (`rate` and `reviews_count` columns on `webinars` table, updated via review approval observer) or cache the homepage top-rated query.
