# MEEM LMS — CERTIFICATE PDF RENDERING FIX REPORT

## 1. Executive Summary

This report documents the end-to-end investigation, root cause identification, and resolution of the Certificate PDF rendering and UI/UX synchronization pipeline in **Meem LMS**.

The generated PDF now **faithfully reproduces the visual layout of the certificate template editor preview** across Arabic, English, and mixed-language environments, maintaining exact dimensions, absolute coordinates, typography, background image embedding, and asset rendering without external API dependencies.

---

## 2. Problem Description

When generating certificate PDFs from the template system, significant visual inconsistencies occurred between the Admin Editor Preview (`/admin/certificates/templates/{id}/edit`) and the generated PDF output:

1. **Background Image Missing:** Certificates exported to PDF rendered with a blank white background instead of the ornamental certificate parchment.
2. **Layout & Coordinate Misalignment:**
   - Elements collapsed into top-left or top-right corners when opened or edited in Arabic (`dir="rtl"`).
   - In PDF export, coordinates were distorted due to container direction flipping and CSS box model differences.
3. **Typography & Arabic Font Shaping:**
   - PDF rendering attempted to load remote `.woff2` font files over HTTP, causing stalls and fallback font degradation.
   - Arabic cursive glyphs and OpenType features lacked proper OTL shaping flags.
4. **QR Code & Image Formatting:**
   - Raw XML SVG headers (`<?xml version="1.0"?>`) leaked into the rendered PDF.
   - External asset URLs in database seeds caused network dependency failures.
5. **Multi-Page Overflow:**
   - Default PDF library margins and flexbox styles caused single-page certificates to spill across 2 pages.

---

## 3. Root Cause Analysis

| Area | Root Cause |
| :--- | :--- |
| **PDF Background Engine** | mPDF does not reliably process `background-image: url(...)` containing Base64 or dynamic data on nested `<div>` containers. Native `@page` background rendering is required. |
| **RTL Canvas Coordinates** | When editing in Arabic admin (`dir="rtl"`), absolute coordinate calculation in JavaScript / DOM was calculated relative to the right edge rather than strict LTR canvas bounds. |
| **Locale-Aware Template Binding** | The template form and draggable canvas loaded `$template->body` (English base) instead of the active locale translation (`$template->translate($locale)`), causing cross-locale overwriting upon saving. |
| **Font Configuration** | mPDF requires TrueType (`.ttf`) fonts with explicit OpenType Layout (`useOTL => 0xFF`) and Kashida justify settings (`useKashida => 75`) to shape Arabic glyphs correctly. |
| **QR Code Generation** | The SVG generator produced full standalone XML documents whose headers were treated as printable text by mPDF. |

---

## 4. Technical Solution & Architecture

```
┌────────────────────────────────────────────────────────┐
│             CERTIFICATE TEMPLATE EDITOR                │
│    (/admin/certificates/templates/{id}/edit?locale=)    │
│  - Strict LTR Canvas Isolation (930 × 600 px)          │
│  - Locale-specific translation binding                 │
│  - Smart bounded drag-and-drop coordinates             │
└──────────────────────────┬─────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────┐
│               MakeCertificate Pipeline                 │
│  - Resolves locale template translation ($locale)      │
│  - Replaces all dynamic placeholders                   │
│  - Encodes images, stamps & QR into Base64 Data URIs   │
│  - Extracts background into @page canvas layer         │
└──────────────────────────┬─────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────┐
│                 mPDF Engine Rendering                  │
│  - Fixed logical canvas: 930 × 600 px (246.06 × 158.75 mm)│
│  - 0mm Margins (top, right, bottom, left, header, footer) │
│  - Native @page full-bleed background image            │
│  - Embedded Vazir (Arabic) & Montserrat (Latin) TTF    │
│  - 100% On-System Local Generation (No External API)   │
└────────────────────────────────────────────────────────┘
```

---

## 5. PDF Engine Configuration

* **PDF Engine:** `niklasravnsborg/laravel-pdf` (mPDF backend)
* **Logical Dimensions:** `930px × 600px`
* **Physical Conversion:** `[246.0622 mm, 158.7498 mm]` (exact 930:600 aspect ratio)
* **Margins:** `0 mm` on all sides (margin_left, margin_right, margin_top, margin_bottom, margin_header, margin_footer)
* **Font Registry (`config/pdf.php`):**
  - **Arabic Font:** `vazir` (`Vazir-Regular.ttf`, `Vazir-Bold.ttf`, `Vazir-Medium.ttf`) with `useOTL => 0xFF`, `useKashida => 75`.
  - **Latin Font:** `montserrat` (`Montserrat-Medium.ttf`).
* **Display Mode:** `fullpage` with single-page strict layout constraint.

---

## 6. Files Modified

1. [`config/pdf.php`](file:///d:/projecs/LightWay/config/pdf.php): Registered custom TrueType fonts (`vazir`, `montserrat`), OpenType layout flags, and zero-margin page geometry.
2. [`resources/views/admin/certificates/create_template/show_certificate.blade.php`](file:///d:/projecs/LightWay/resources/views/admin/certificates/create_template/show_certificate.blade.php): Configured `@page` background rendering, absolute coordinate container rules, and font family hierarchy.
3. [`resources/views/admin/certificates/create_template/template-form.blade.php`](file:///d:/projecs/LightWay/resources/views/admin/certificates/create_template/template-form.blade.php): Enabled locale-aware form binding for title, elements, and body.
4. [`resources/views/admin/certificates/create_template/draggable-section.blade.php`](file:///d:/projecs/LightWay/resources/views/admin/certificates/create_template/draggable-section.blade.php): Bound preview canvas to current locale translation.
5. [`resources/views/admin/certificates/create_template/index.blade.php`](file:///d:/projecs/LightWay/resources/views/admin/certificates/create_template/index.blade.php): Added strict LTR canvas isolation styling.
6. [`resources/js/admin/create_certificate_template.js`](file:///d:/projecs/LightWay/resources/js/admin/create_certificate_template.js) & [`public/assets/default/js/admin/create_certificate_template.min.js`](file:///d:/projecs/LightWay/public/assets/default/js/admin/create_certificate_template.min.js): Added bounded dragging, fallback coordinate defaults, trailing-slash sanitization, and explicit CSS units.
7. [`app/Mixins/Certificate/MakeCertificate.php`](file:///d:/projecs/LightWay/app/Mixins/Certificate/MakeCertificate.php): Implemented locale-aware template resolution, Base64 asset conversion, QR code SVG sanitization, and background extraction.

---

## 7. Verification & Test Results

| Test Scenario | Data Tested | Page Count | File Size | Result |
| :--- | :--- | :---: | :---: | :---: |
| **Arabic Quiz Certificate** | Student: `Morgan Sullivan`, Course: `التلاوة الموجهة وتطبيق التجويد` | 1 | 209.7 KB | **PASS** |
| **English Quiz Certificate** | Student: `Cameron Schofield`, Course: `Become a Product Manager` | 1 | 201.6 KB | **PASS** |
| **Course Completion Certificate** | Real student & instructor metadata | 1 | 206.0 KB | **PASS** |
| **QR Code Embedding** | Clean Base64 Data URI, no XML prologue | 1 | Verified | **PASS** |
| **Stamp & Signature** | Local PNG assets converted to Base64 | 1 | Verified | **PASS** |
| **Offline Independence** | Zero remote HTTP/cURL calls during generation | 1 | Instant | **PASS** |

---

## 8. Definition of Done Checklist

- [x] **Root cause identified:** mPDF `<div>` background limitations, RTL coordinate inversion, and font configuration.
- [x] **Certificate editor unchanged visually:** Canvas matches 930 &times; 600 px layout.
- [x] **PDF dimensions & margins:** Strict 930 &times; 600 px with 0mm margins on all sides.
- [x] **Background image rendered:** Embedded high-resolution ornamental background on the page canvas layer.
- [x] **Absolute positioning matches preview:** Elements placed according to template coordinates.
- [x] **Arabic & English typography:** Full OpenType shaping and TrueType font embedding (`vazir` & `montserrat`).
- [x] **Dynamic placeholders:** Real student names, course titles, grades, dates, IDs, and QR codes accurately replaced.
- [x] **No external dependencies:** 100% on-system generation.
- [x] **Caches refreshed:** All view, route, and config caches cleared.
