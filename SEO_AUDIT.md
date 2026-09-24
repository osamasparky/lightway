# Technical SEO Audit Report — Meem LMS

**Audit Target:** Technical SEO, Crawlability, Metadata, Indexing Directives, URLs, Structured Data

---

## 1. Executive SEO Summary

The application has a **critical indexing bug** in its root metadata template that actively instructs search engine crawlers **not to index public pages**. In addition, the site lacks XML sitemaps, canonical tags, and structured data schemas.

### SEO Findings Register

| Priority | Area / URL | Identified Issue | Evidence | Impact | Recommended Solution |
| :---: | :--- | :--- | :--- | :--- | :--- |
| 🚨 **P1** | Global Template (`metas.blade.php`) | **Default Robots Meta Tag Instructs Noindex/Nofollow** | `<meta name='robots' content="{{ $pageRobot ?? 'NOODP, nofollow, noindex' }}">` in `resources/views/web/default/includes/metas.blade.php:8` | 🚨 **CRITICAL**: Search engines drop public pages if `$pageRobot` is not set by controller | Change default to `'index, follow, all'` for all public web views. |
| 🚨 **P2** | Global Head | **Missing Canonical URL Tags (`<link rel="canonical">`)** | `resources/views/web/default/includes/metas.blade.php` has no canonical tag | High duplicate content penalties across query parameters and category filters | Add dynamic canonical tag based on clean current URL (`url()->current()`). |
| 🚨 **P3** | Sitemap Infrastructure | **No XML Sitemap (`sitemap.xml`) or Generation Route** | Checked `routes/web.php` and `public/robots.txt` — 0 sitemap definitions found | Crawlers cannot discover dynamic courses, blog posts, and instructor profiles efficiently | Implement automatic XML sitemap generator command & route for courses, blogs, and categories. |
| 🟠 **P4** | OpenGraph Metadata | **Malformed `og:locale` Attribute** | `<meta property='og:locale' content='{{ url(!empty($generalSettings['locale']) ? $generalSettings['locale'] : 'en_US') }}'>` outputs `http://domain/en_US` | Social share cards and rich snippets fail validation | Output standard BCP 47 locale format (`en_US`, `ar_SA`) without wrapping in `url()`. |
| 🟠 **P5** | Structured Data (Schema.org) | **Missing JSON-LD Schemas for Courses, Organization, FAQ** | 0 Schema.org JSON-LD scripts found in view templates | Loss of Google Course carousels, breadcrumb snippets, and star rating rich results | Add JSON-LD schemas (`Course`, `EducationalOrganization`, `BreadcrumbList`, `FAQPage`). |
| 🟡 **P6** | Public Assets Preloading | **Preloading Admin Asset on Public Pages** | `<link rel="preload" href="/assets/admin/img/front.png" as="image">` in `metas.blade.php:35` | Unnecessary download on mobile devices | Remove admin preload tag from public layout template. |
| 🟡 **P7** | Robots.txt | **Missing Sitemap Reference in `robots.txt`** | `public/robots.txt` only has `User-agent: * Disallow:` | Search engine bots miss sitemap discovery on domain crawl | Append `Sitemap: https://staging.lightway.meemdemo.com/sitemap.xml` to `robots.txt`. |

---

## 2. Detailed Technical SEO Breakdown

### 1. Critical De-Indexing Bug
```blade
<!-- resources/views/web/default/includes/metas.blade.php (Line 8) -->
<meta name='robots' content="{{ $pageRobot ?? 'NOODP, nofollow, noindex' }}">
```
- **Analysis:** In Laravel Blade, `??` defaults to the right-hand expression if `$pageRobot` is null or undefined. Because many controllers (e.g., specific static pages, tags, search, instructor profiles) do not pass `$pageRobot` explicitly in their view data arrays, the template defaults to **`NOODP, nofollow, noindex`**, explicitly instructing Googlebot and Bingbot to ignore the page and disregard all outgoing links.
- **Fix:**
```blade
<meta name='robots' content="{{ $pageRobot ?? 'index, follow, all' }}">
```

---

### 2. Structured Data Integration Strategy (JSON-LD)
To gain rich snippets and Google Course search visibility:
- **Course Pages (`/course/{slug}`):**
```json
{
  "@context": "https://schema.org",
  "@type": "Course",
  "name": "{{ $webinar->title }}",
  "description": "{{ $webinar->seo_description }}",
  "provider": {
    "@type": "Organization",
    "name": "{{ $generalSettings['site_name'] }}",
    "sameAs": "{{ url('/') }}"
  },
  "offers": {
    "@type": "Offer",
    "price": "{{ $webinar->price }}",
    "priceCurrency": "SAR",
    "category": "Paid"
  }
}
```
- **Blog Articles (`/blog/{slug}`):** Include `Article` / `BlogPosting` schema.
- **Breadcrumb Navigation:** Include `BreadcrumbList` schema on all category and subcategory pages.
