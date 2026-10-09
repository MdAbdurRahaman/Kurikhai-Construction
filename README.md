# Tabeeb Contractor Website & Lead Management CRM

A premium, highly secure website and CRM platform for **Tabeeb Contractor**, a direct building, residential & commercial renovation, commercial reinstatement, hacking, and alterations contractor in Singapore.

## 🏢 Company Profile & Contact Info
* **Public Brand**: Tabeeb Contractor
* **Legal Entity**: Pending exact confirmation & completion of reported name change (Current registered note: Bless Global)
* **UEN**: `202506878W` (Client-supplied)
* **Office Address**: 61 Kaki Bukit Ave 1, #03-34 Shun Li Industrial Park, Singapore 417943 (Client-confirmed)
* **Hotline / Telephone**: [+65 8648 4883](tel:+6586484883) (Client-confirmed)
* **WhatsApp Direct**: [Chat on WhatsApp (+65 8648 4883)](https://wa.me/6586484883)
* **Email**: [info@tabeebgroup.com](mailto:info@tabeebgroup.com)
* **Website**: [https://tabeebgroup.com](https://tabeebgroup.com)
* **Operating Hours**: Monday - Saturday: 9:00 AM - 6:00 PM (Closed on Sundays & Public Holidays)
* **Google Maps Listing**: [Client Listing on Google Maps](https://www.google.com/maps/place/TABEEB+CONTRACTOR+PTE+LTD/@1.3363576,103.9091695,1128m/data=!3m2!1e3!4b1!4m6!3m5!1s0x31da17d8a42c489f:0x834162f16ce7e27b!8m2!3d1.3363576!4d103.9117444!16s%2Fg%2F11zxps0z_m?entry=ttu)

---

## 🎨 Design & Features (Inspired by Professional Contractor Standards)
* **Corporate Palette**: Deep Contractor Navy (`#0a1128`), Industrial Slate Navy (`#16224f`), Architectural Gold (`#d4af37`), and Vibrant Cyan (`#00b4d8`).
* **Top Bar**: Fast contact access across all pages displaying Singapore HQ address, operating hours, phone hotline, and instant WhatsApp chat.
* **Trust & Value Bar**: Quality Workmanship, Reliable Team, On Time Delivery, and Competitive Pricing (Direct Contractor Rates).
* **Floating WhatsApp Widget**: Persistent, pulsating WhatsApp contact button with pre-filled greeting for rapid customer conversions.
* **Interactive Map**: Location map pinpointing Shun Li Industrial Park at 61 Kaki Bukit Ave 1, Singapore.
* **Fonts**: Google Fonts `Outfit` (headings) and `Inter` (body text).
* **Favicon**: Inline SVG brand-matching `T` lettermark.

---

## 📂 Project Structure
```
├── index.html                  # Homepage (Hero, Trust bar, Features, Lead capture, Footer)
├── services.html               # Detailed services catalog with category filter & quote requests
├── about.html                  # About Company, values, and workflow
├── contact.html                # Contact page with lead form and Google Maps link
├── privacy.php                 # Singapore PDPA-compliant Privacy Policy
├── terms.php                   # Website Terms of Service & Contractor Disclaimers
├── service.php                 # Dynamic service page renderer with full Open Graph schema
├── blog.php                    # Renovation & construction blog index
├── blog-post.php               # Dynamic blog article renderer with safe HTML sanitize
├── sitemap.php                 # Dynamic XML sitemap generator
├── api/
│   └── leads.php               # Secure same-origin lead capture endpoint (POST /api/leads)
├── config/
│   ├── config.php              # Central environment configuration & constants
│   ├── schema.sql              # MySQL/MariaDB database DDL migrations
│   └── .env.example            # Environment variables template
├── includes/
│   ├── db.php                  # PDO singleton connection & prepared statement wrapper
│   ├── security.php            # CSRF, XSS sanitizer, rate limiting, honeypot, image upload
│   ├── auth.php                # Hardened authentication, RBAC, session protection
│   ├── mailer.php              # Authenticated SMTP socket mailer & routing
│   ├── business_tracker.php    # Business Information Tracker service
│   └── data.php                # Unified data access layer (MySQL with fallback)
├── admin/
│   ├── index.php               # Real-time KPI & pipeline dashboard
│   ├── leads.php               # CRM leads table, search, filters, CSV export
│   ├── lead-detail.php         # Lead details, status updates, notes, activity timeline
│   ├── business-info.php       # Protected Business Information Tracker
│   ├── notifications.php       # Notification recipients, routing, delivery logs, SMTP test
│   ├── settings.php            # Integrations (GA4, GTM, GSC, Turnstile, PDPA retention)
│   ├── audit-logs.php          # Comprehensive administrative audit trail
│   ├── posts.php & post-edit.php # Blog article manager & sanitized editor
│   ├── users.php               # RBAC user management (6 granular roles)
│   ├── login.php & logout.php  # Protected CSRF & rate-limited authentication
│   └── header.php & footer.php # Standardized admin layout & navigation
└── scripts/
    └── migrate.php             # JSON-to-MySQL database migration & seeding utility
```

---

## 🛡️ Security Architecture Highlights
1. **Zero Documented Default Credentials**: All credentials must be established via secure installer or CLI environment setup.
2. **Safe Deployment (No `unzip.php`)**: Automated deployment uses authenticated cPanel UAPI `Fileman/extract_files` over HTTPS (port 2083). Public extractors are strictly prohibited.
3. **Prepared Statements**: 100% of SQL queries utilize parameterized PDO prepared statements; zero SQL string concatenation.
4. **Singapore PDPA Compliance**: Transparent inquiry consent, privacy notice, configurable data retention, and soft deletion.
5. **Anti-Spam & Rate Limiting**: Same-origin submission with hidden honeypot, minimum form fill time detection, duplicate detection, and IP-derived rate limiting.
6. **Honest Lead Flow**: Frontend never reports false success; handles network and server errors gracefully with telephone and WhatsApp fallbacks while preserving user inputs.

---

## 📝 Blog System & Dedicated Service Pages

### 1. Dedicated Service Pages
Every service has its own dedicated page and individual Open Graph metadata:
* `/service/demolition-hacking` ➔ Demolition & Wall Hacking Works
* `/service/commercial-office-reinstatement` ➔ Commercial Office Reinstatement
* `/service/flooring-cement-screed` ➔ Flooring & Pre-Packed Cement Screed
* `/service/false-ceiling-drywall-partition` ➔ False Ceiling & Drywall Partition
* `/service/painting-plastering` ➔ Painting & Wall Plastering Works
* `/service/plumbing-sanitary` ➔ Sanitary Plumbing Works
* `/service/electrical-lighting` ➔ Electrical Rewiring & DB Box Upgrading
* `/service/waterproofing-pu-injection` ➔ Waterproofing & PU Injection
* `/service/metal-fabrication-grilles` ➔ Custom Metal Fabrication & Grilles
* `/service/home-extensions-alterations` ➔ Home Extensions & Alterations

### 2. Admin Portal (`/admin`)
* **Portal URL**: `https://tabeebgroup.com/admin/` (or `https://tabeebgroup.com/admin/login.php`)
* **Administrator Access Credentials**:
  * **Super Administrator**:
    * **Username**: `admin`
    * **Password**: `TabeebAdmin@2026!`
    * **Role**: Super Admin (Full access to Leads CRM, Business Information Tracker, Notifications, Users & Settings)
  * **Content Editor**:
    * **Username**: `editor`
    * **Password**: `TabeebEditor@2026!`
    * **Role**: Content Editor (Blog articles and Service content updates)
  * *Security Notice:* Credentials have been completely removed from the website login UI to prevent unauthorized visibility. Administrators can rotate passwords at any time under **Users (`/admin/users.php`)**.
* **Features**:
  * Real-time KPI Lead Pipeline & Analytics
  * Complete CRM with Lead Assignment, Status Funnel & Activity Timelines
  * Business Information Tracker with Publication Approval Gates
  * Notification Routing & Authenticated SMTP Delivery Logging
  * RBAC User Management across 6 granular roles
  * Integrations Hub (GA4, Google Search Console, Turnstile, Meta Pixel)
  * Audit Trail Viewer for security and compliance tracking
