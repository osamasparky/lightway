# Security Audit Report — Meem LMS

**Audit Target:** Application Security, Authentication, Authorization, Injection, File Uploads, Secrets, Payments  
**Standard:** OWASP Top 10 & CWE Verification Guidelines

---

## 1. Executive Security Summary

The codebase contains several critical and high-severity security vulnerabilities that expose the system to **unauthenticated SQL Injection**, **arbitrary file upload with Remote Code Execution (RCE)**, **unauthorized push notifications**, and **financial state inconsistency** during payment processing.

### Security Risk Register

| Severity | Vulnerability Type | Vulnerable Location | Evidence | Impact | Confidence | Recommended Remediation |
| :---: | :--- | :--- | :--- | :--- | :---: | :--- |
| 🚨 **CRITICAL** | **SQL Injection (Unauthenticated)** | `app/Http/Controllers/Web/InstructorFinderController.php:294-298` | `$userAgeQuery->whereRaw('value >= ' . $minAge);` | Full database exfiltration / compromise | Confirmed | Replace with parameterized query `->where('value', '>=', (int)$minAge)`. |
| 🚨 **CRITICAL** | **Zip Slip & Arbitrary File Upload (RCE)** | `app/Http/Controllers/Panel/FileController.php:217-224` | `$zip->extractTo(public_path($storageExtractPath));` with no entry path validation or PHP extension filtration | Instructor can upload zip containing `.php` files or `../` path traversal to overwrite core system files | Confirmed | Validate entry filenames, reject `.php`/`.phtml` files, and disallow path traversal outside target directory. |
| 🚨 **CRITICAL** | **Unauthenticated Push Notification API & Debug Stack Leak** | `routes/api/guest.php:113-130` (`POST /api/development/notification/new`) | Route has no auth middleware; broadcasts FCM messages and returns `$exception->getTrace()` on error | Unauthorized spam push notifications to users; sensitive path/secret disclosure | Confirmed | Restrict route to admin middleware or remove development testing endpoint entirely. |
| 🚨 **CRITICAL** | **Non-Atomic Payment Settlement (Financial Risk)** | `app/Http/Controllers/Web/PaymentController.php:191-280` | `setPaymentAccounting()` executes accounting, gifts, sales, tickets in unmanaged loop without `DB::transaction()` | System left in inconsistent state on runtime exception during payment processing | Confirmed | Wrap entire `setPaymentAccounting` body in `DB::transaction(function() { ... })`. |
| 🟠 **HIGH** | **Capacity Race Condition (Overselling)** | `app/Models/Ticket.php:34-39`, `PaymentController.php:235` | `TicketUser::useTicket()` checks capacity without database pessimistic locking (`lockForUpdate()`) | Concurrent checkout requests can oversell limited-capacity tickets | Confirmed | Apply `lockForUpdate()` when validating and consuming ticket stock. |
| 🟠 **HIGH** | **Public Fallback De-Indexing & Misconfiguration** | `resources/views/web/default/includes/metas.blade.php:8` | Default robots meta tag outputs `NOODP, nofollow, noindex` | Search engine indexing suppressed across unconfigured routes | Confirmed | Set default robots tag to `index, follow, all`. |
| 🟡 **MEDIUM** | **Hardcoded Secrets & API Keys in `.env.example` / Code** | `.env:8-9` (`JWT_SECRET`, `API_KEY=1234`) | Default simple keys present in configuration | Potential token forgery if defaults are deployed to production | Confirmed | Enforce robust secret generation via artisan key generation and environment auditing. |
| 🟡 **MEDIUM** | **Missing Strict CSP & HSTS Headers** | Global HTTP Middleware | No Content-Security-Policy or Strict-Transport-Security headers set in Laravel middleware | Higher susceptibility to XSS and downgrade attacks | Confirmed | Add security headers middleware (HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy). |

---

## 2. In-Depth Vulnerability Technical Analysis

### 1. SQL Injection in Instructor Finder
```php
// app/Http/Controllers/Web/InstructorFinderController.php
private function handleAgeFilter($query, Request $request)
{
    $minAge = $request->get('min_age', null);
    $maxAge = $request->get('max_age', null);

    if (!empty($minAge) or !empty($maxAge)) {
        $userAgeQuery = UserMeta::where('name', 'age');

        if (!empty($minAge)) {
            $userAgeQuery->whereRaw('value >= ' . $minAge); // VULNERABLE: Direct concatenation
        }

        if (!empty($maxAge)) {
            $userAgeQuery->whereRaw('value <= ' . $maxAge); // VULNERABLE: Direct concatenation
        }

        $userIds = $userAgeQuery->pluck('user_id')->toArray();
        $query->whereIn('users.id', $userIds);
    }
    return $query;
}
```
- **Attack Vector:** An attacker sends `GET /instructors?min_age=0%20OR%201=1` or subquery payloads to extract data from the `users` table.
- **Remediation:**
```php
if (!empty($minAge)) {
    $userAgeQuery->where('value', '>=', (int) $minAge);
}
if (!empty($maxAge)) {
    $userAgeQuery->where('value', '<=', (int) $maxAge);
}
```

---

### 2. Zip Slip & Remote Code Execution in Interactive File Upload
```php
// app/Http/Controllers/Panel/FileController.php
if (!$storage->exists($extractPath)) {
    $storage->makeDirectory($extractPath);
    $filePath = public_path($path);

    $zip = new \ZipArchive();
    $res = $zip->open($filePath);

    if ($res) {
        $zip->extractTo(public_path($storageExtractPath)); // VULNERABLE: Unchecked extraction
        $zip->close();
    }
}
```
- **Attack Vector:** A registered instructor uploads an interactive archive (`storage = upload_archive`). The zip contains a file named `payload.php` or `../../../../var/www/vhosts/.../exploit.php`. When extracted into the public storage folder, the PHP file becomes directly executable via HTTP request.
- **Remediation:**
  1. Inspect each entry in the zip archive before extraction.
  2. Reject any archive containing executable file extensions (`.php`, `.phtml`, `.phar`, `.sh`, `.exe`, `.cgi`, `.htaccess`).
  3. Ensure resolved extraction paths strictly reside within the intended directory (`strpos(realpath($target), $basePath) === 0`).

---

### 3. Open Firebase Push API & Error Stack Leak
```php
// routes/api/guest.php
Route::post('/notification/new', function (){
    $title = request("title");
    $body = request("message");
    $token = request("token");

    $fcmMessage = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $token);
    $fcmMessage = $fcmMessage->withNotification([
        'title' => $title,
        'body' => $body
    ]);
    $messaging = app('firebase.messaging');
    try {
        $response = $messaging->send($fcmMessage);
        return apiResponse2(1,"retrived","",$response);
    } catch (Exception $exception){
        return apiResponse2(1,"retrived",$exception->getMessage(),$exception->getTrace());
    }
});
```
- **Attack Vector:** Any external user can make `POST /api/development/notification/new` without authentication, sending arbitrary push notifications to any Firebase FCM token, or triggering exceptions to receive the complete server directory structure, database credentials, and call stack.
- **Remediation:** Delete or protect this testing route behind `auth:api` and `admin` middleware, and ensure exception traces are never returned in API responses.
