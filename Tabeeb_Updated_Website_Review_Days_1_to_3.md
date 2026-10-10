# Tabeeb updated website review and days 1–3 audit

Reviewed: 10 October 2026. Source: `System  eb.rar`, containing `Kurikhai-Construction`, and the three client media attachments supplied with it.

**Conclusion: the previous prompt has been partly implemented. This archive does not establish completion of days 1–3, and the lead/admin system needs corrections before it can be relied on for production enquiries.**

## Evidence and limits

I extracted 431 archive files and inspected the public pages, application JavaScript, PHP lead endpoint, data layer, authentication, mailer, admin routes, database schema, migration, Apache rules and deployment workflow. I visually inspected both JPEGs and sampled the 15-second MP4 at five points. The original archive was not modified.

`node --check app.js` passed. The referenced local CSS/JavaScript/image assets checked in the static HTML pages exist. The public form now calls `/api/leads`; the obsolete Apps Script file is not referenced by those pages.

PHP and a configured MySQL instance were unavailable in this review environment, so I did not execute the PHP application, run database migrations, test SMTP or log in to the admin. A local rendering attempt could not start because the browser executable was unavailable. Live homepage retrieval also failed through the web research tool. I therefore do not claim a live deployment, visual/mobile render, Lighthouse, authenticated Google-account or database test. The findings below are source-level findings; deterministic code defects are identified as such, without invented runtime results.

## Days 1–3: current status

| Day | Required output | Evidence found | Verdict and next action |
|---|---|---|---|
| 1 | Organic traffic, rankings, enquiries and conversion baseline | Database-derived lead counts exist in `admin/index.php`. No dated baseline report, traffic/ranking exports, defined reporting window or conversion denominator was found. No production database/report access was supplied. | **Not evidenced as complete.** Record real available data with dates, sources, definitions and exclusions. Mark unavailable historical data N/A—not zero. |
| 2 | Verified Search Console Domain property | `googlea9c88d1e38989379.html` is present. Admin treats a nonempty default identifier as “Active.” There is no proof of DNS verification or Google-account ownership status. | **Preparation exists; Domain verification unproven.** Verify `tabeebgroup.com` through DNS and retain property/status evidence. |
| 3 | Working GA4, GTM and page-view tracking | Admin can save `ga4_id` and `gtm_id`. No `gtag`/GTM loader or `dataLayer` tracking implementation was found in the public PHP/HTML/JS. No Tag Assistant/GA4 receipt evidence was supplied. | **Not implemented end to end in this archive.** Connect saved configuration to public rendering, configure/publish the real GTM container, and verify GA4 receipt. |

An HTML verification file can support a Search Console **URL-prefix** property, but cannot verify a **Domain** property. The latter requires domain-provider verification. A nonempty field does not prove that Google accepted verification. [Google: verify site ownership](https://support.google.com/webmasters/answer/9008080?hl=en).

GTM requires its website installation plus tag configuration. GA4 receipt should be checked in Realtime/DebugView, rather than inferred from an ID entered in admin. [Google: install a web container](https://support.google.com/tagmanager/answer/14847097?hl=en), [Google: confirm data collection](https://support.google.com/analytics/answer/9333790?hl=en).

## What has improved

- MySQL schema, PDO wrapper and migration utility have been added.
- A same-origin lead API replaces the public form’s Apps Script request.
- The frontend checks HTTP success and `result.success`, preserves form inputs on failure, and offers contact alternatives.
- Lead listing, details, assignment, notes, status changes and database-derived dashboard queries exist.
- Business Information, notification-recipient, settings and audit-log admin pages exist.
- Password hashing, login throttling, session settings, CSRF helpers, MIME checks and CSV-cell escaping have been added.
- Privacy/terms pages, canonical-host redirects, protected data/config routes and a dynamic sitemap exist.

These are implementation progress, not proof that every feature works or that the earlier acceptance criteria have passed.

## Required corrections, in priority order

| Priority | Finding and source evidence | Correction |
|---|---|---|
| Critical | `DB::query()` catches SQL errors and returns null in production; `DB::execute()` then returns zero. `api/leads.php` ignores the insert result, reads `lastInsertId()` and sets success true. Its PDOException catch does not catch a suppressed exception. | Throw/propagate write failures, validate insertion and commit a transaction before responding with success. Test a connected database with a deliberately failed INSERT. |
| Critical | Lead JSON fallback calls `read_json_file()`/`write_json_file()`, defined in `includes/data.php`, which is absent from the endpoint dependency chain. It also ignores the write result and has no visible queue replay worker. | Remove the automatic lead JSON fallback for the required MySQL flow. Return an honest unavailable response; preserve/import any existing queued records through a tested private migration. |
| Critical | README still publishes reusable default admin/editor passwords. `data/users.json` remains, authentication falls back to those users when DB is unavailable, and imported forced-password-change flags are not enforced. | Rotate exposed credentials, remove defaults and production JSON authentication, enforce password-change/session-revocation controls and fail closed during database outages. |
| High | `admin/index.php` requires login but exposes recent lead names/phones to every logged-in role. `admin/posts.php` allows status changes/deletion with login+CSRF but no content permission. | Enforce server-side permissions before data access, rendering and mutation. Seed/test all roles. The schema currently seeds role-permission mappings only for Super Admin. |
| High | `save_user()` edits can deactivate/demote an administrator without the final-active-super-admin safeguard used by deletion. Sessions keep cached account/role data without active-status revalidation. `user.updated` sends the entire existing user record, including its password hash, into audit logging. | Guard edits and deletes transactionally; revalidate/revoke sessions; redact secrets/hashes from audit records. |
| High | SMTP test calls `Mailer::sendTestEmail()` in `admin/notifications.php`, but none of its loaded dependencies includes the mailer. The native mailer sends every recipient individually as To and ignores recipient type/source rules. No retry worker is present. It also falls back to a mailbox when no active recipients remain. | Load dependencies correctly; use a maintained mailer and a durable notification outbox; honour routing and disabled states; implement retries and duplicate suppression. |
| High | Lead validation accepts phones based on minimum length instead of the normalizer’s validity flag, accepts arbitrary service/source strings, coerces insufficiently checked input types, and hard-codes consent true. No public request-origin/CSRF enforcement or server Turnstile verification is present. | Validate field types, lengths and allowed values; choose explicit phone rules; validate real notice/consent evidence and appropriate same-origin/CSRF controls. Keep anti-spam independent of consent. |
| High | HTML sanitization uses regex/`strip_tags`, leaving unsafe URL/attribute cases. Uploads check MIME and dimensions but move the original file without re-encoding or pixel limits. | Replace with a maintained parser-based allowlist sanitizer; decode/re-encode uploads with size/pixel limits and safe output handling. |
| High | Client-IP code trusts forwarded headers from arbitrary callers and hashes with a predictable monthly salt. | Trust only configured proxies and use a protected rotating HMAC key. Make rate-limit updates concurrency safe. |
| High | GA4/GTM values are stored only in admin; Search Console is marked Active from a default string. `analytics_events` is schema only, with no event writer. | Implement real tag output and verified status/evidence. Keep internal operational metrics separate from external visitor analytics. |
| High | Static homepage/about/contact/services, and several PHP pages, hard-code business details. The Business Information tracker is not a working single public render source. Its hidden-value lookup can fall back to old defaults. | Use shared server-rendered templates/settings while preserving public URLs; separate missing configuration from explicitly hidden content; test draft/publish/hide on actual pages. |
| High | Public guarantees, in-house-team claims, free assessments, response promises, operating hours and price ranges remain unsupported by these attachments. Blog posts contain specific regulatory/technical claims without cited verification. | Remove unsupported claims throughout visible copy, FAQs, metadata and JSON-LD. Verify necessary technical/regulatory content with primary sources before publication. |
| Medium | An unknown service slug renders the first service with HTTP 200. JSON fallback can republish a service hidden in MySQL. Legacy service routes are rewritten rather than redirected to one canonical address. | Return a genuine 404 for unknown/unpublished content; stop fallback resurrection; add explicit relevant 301 mappings and consistent canonicals/sitemap links. |
| Medium | `get_post_by_slug()` checks status but not future publication time, unlike the blog list. | Apply publication/scheduling checks consistently on direct detail routes and sitemap. |
| Medium | CSV export runs after `admin/header.php` has already emitted HTML, so the download can contain HTML and headers may fail. | Authorize/export before output; test clean CSV bytes and formula escaping. |
| Medium | Generic Facebook/Instagram/LinkedIn homepage links remain on index/contact. Privacy page asserts 365-day disposal; admin retention value has no cleanup job and is not used by the policy. | Hide missing genuine profiles; implement approved retention behaviour and render the factual policy from actual settings. |
| Medium | Logout accepts GET and ignores invalid POST CSRF. Several restricted admin pages check permissions after the shared header emits output. | Require POST+valid token and enforce authorization before HTML/redirect output. |
| Medium | Deployment workflow uses an ephemeral web extractor and `curl -k`, does not reliably fail on application-level upload/extraction errors, and packages legacy data files. Documentation claims native UAPI extraction while the YAML generates a PHP extractor. | Prefer authenticated nonpublic extraction/deployment with verified TLS, explicit failure handling, correct package exclusions and staging/rollback. Never overwrite runtime data with repository copies. |
| Medium | Large assets remain: poster about 2.72 MiB and hero PNG about 2.35 MiB. There is no evidence of before/after Lighthouse testing. | Resize/convert actual referenced media, use dimensions/srcset, and measure the deployed public pages. This is not a completed day-7/8 speed audit. |

The deployment YAML triggers production deployment on pushes to two branches. A development agent must check this before pushing code: pushing a fix may also publish it.

## Client media: accurate use

| Attachment | What is visible | Appropriate website use |
|---|---|---|
| `WhatsApp Image 2026-10-09 at 7.11.04 PM.jpeg` | A branded project board labelled “Project Site – Blk 234 Serangoon Avenue 3,” with Before, Work in Progress and Completed photographs. | One flooring case study, a homepage project card and a relevant tiling-page example. Keep the board legible in a full-size viewer. |
| `WhatsApp Video 2026-10-09 at 7.11.03 PM.mp4` | 15-second, 1920×1080 MP4; five sampled frames show the same project board. No audio stream is present. | Optional user-controlled media on that project page. It is not additional on-site process footage or a separate completed project. |
| `WhatsApp Image 2026-10-09 at 8.44.11 PM.jpeg` | A tall branded renovation collage with 23 photo panels showing removal, preparation, screeding/tiling stages and finished areas. Some identifiers are blurred. | A supporting work gallery. Do not assume every panel is the Serangoon project or a separate project; identities/stage labels need confirmation where unclear. Keep existing blurs. |

Contact details legible on the first board:

- **Office:** 61 Kaki Bukit Ave 1, #03-34 Shun Li Industrial Park, Singapore 417943.
- **Telephone:** +65 8648 4883.
- **Email:** info@tabeebgroup.com.
- **Website shown in artwork:** www.tabeebgroup.com. Public links can use the existing preferred origin https://tabeebgroup.com.

Suggested factual case-study copy:

> Flooring works at Serangoon Avenue 3. Client-supplied photographs show the existing floor condition, work in progress and the completed tiled finish.

The site label supports identifying this project, but does not supply the completion date, price, duration, tile specification, customer testimonial, permit status or warranty. Keep those unspecified. Before public use, record publication approval/privacy choices for the actual materials. Use broad location wording if permission for the block-level label is not established.

The artwork says “Tabeeb Contractor Pte Ltd.” That is a client-supplied brand label, not an updated ACRA document. Do not automatically change verified legal identity or legalName schema on that basis. Preserve the client’s supplied media rather than silently editing its logo.

## Baseline definitions to use

Use a fixed pre-change window, preferably the last 28 complete days, and an equal preceding window if available. Record the extraction date, actual date bounds and reporting timezone for every source. Google tools may have different reporting timezone conventions; do not silently mix their calendar boundaries.

| Metric | Source / definition | If unavailable |
|---|---|---|
| Organic sessions and users | GA4, with the relevant organic traffic filter and date bounds | N/A; tracking not present or export/access unavailable. |
| Search clicks, impressions, CTR, average position | Search Console, with query/page/country/device filters and date bounds | N/A; historical data/access not available. |
| Target query rankings | GSC average position is an aggregated metric; record a separately scoped rank check only if actually measured | N/A; do not invent a fixed Google rank. |
| Unique website enquiries | Persisted, deduplicated production lead records; exclude tests/spam by stated rule | N/A until DB/export is available. |
| Calls, email and WhatsApp | Separate click counts from received calls/messages/enquiries | No historical tracking; N/A. |
| Website lead conversion rate | Prefer sessions with at least one successful enquiry ÷ measured eligible sessions, using the same window and scope | N/A when numerator cannot be matched to denominator. |

Later implementation cannot recreate uncollected historical GA4 visits. An empty new database also does not prove zero earlier business enquiries.

## What to give the next AI agent

Provide the updated archive, all three media attachments, and `Tabeeb_Days_1_to_3_Corrections_AI_Prompt.txt`. The prompt requires it to repair the current implementation, capture available real baseline data, prepare/complete Google setup according to actual access, integrate the supplied project content and provide an evidence-based handover.

Actual completion still needs the client’s Google accounts/property/container permissions, DNS access or a DNS administrator, real GA4 Measurement ID/GTM container ID and production reporting evidence. These are account/setup dependencies, not tasks solved by adding dashboard labels.
