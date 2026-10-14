# Tabeeb analytics and performance setup

The website is a PHP application. Tags are installed in its shared tracking component; no WordPress code-manager plugin is needed.

## What this release adds

- Consent-based traffic and page measurement, anonymous browser identifiers, browser-tab sessions, acquisition channels, campaigns, device categories, popular pages and daily traffic.
- Confirmed form-success events, form starts/errors and completion rates. A successful non-duplicate website response emits `generate_lead`; the separate CRM report remains the authoritative count of stored enquiries.
- Phone, WhatsApp, email and Maps click intent. These do not prove a completed call, conversation or qualified lead.
- Scroll-depth milestones and visible engagement sampled every 15 seconds.
- Google's pinned web-vitals 5.0.3 library for LCP, INP, CLS, TTFB and FCP. The performance report shows P75 of the latest 5,000 samples for each metric in the selected period. Browser support and consent affect coverage.
- Role-controlled Traffic & Performance dashboard with date filters and optional authenticated GA4 reports.
- Disabled-by-default advertising storage and advertising personalization; no enquiry form values or customer identifiers are sent to Google.
- Staging, staff, admin and preview traffic exclusions; replay-safe event storage; same-origin, CSRF and rate-limit checks.
- Raw first-party measurements expire after 90 days. The existing notification worker removes expired records in bounded batches each minute; ingestion also performs occasional cleanup. Confirm the minute worker remains installed.

First-party figures are labelled separately from GA4. Browser-tab sessions and random browser identifiers are not the same as GA4 users/sessions. Date ranges use the Singapore server timezone; Google reports use the GA4 property timezone. No historical visitor data is invented or backfilled.

## 1. Deploy

Apply the previous multi-role access patch first, then this tracking patch. Push to main and confirm the staging-and-production deployment step passes. The additive migration creates separate measurement/report tables; the legacy analytics table is preserved. Open `/admin/analytics.php` as the portal owner.

## 2. Create or select GA4

Open https://analytics.google.com/ and create/select a GA4 property for Tabeeb. Set Singapore timezone and SGD currency, then create/select the web data stream for `https://tabeebgroup.com/`.

Copy the stream's **Measurement ID** (`G-…`) and the property's separate **numeric Property ID**. Enter them in Admin → Website Settings.

Choose **Direct GA4** for the shortest setup. This loads one Google tag after analytics consent and sends the site's explicit page/event measurements. You do not also need a GTM container in this mode.

Disable automatic form interactions, outbound-click measurement and history-based pageviews in the stream's Enhanced Measurement options when using these explicit events. This prevents duplicate conversions/pageviews and prevents automatic collection of enquiry/contact link URLs. Review other Enhanced Measurement options before enabling them.

Mark `generate_lead` as a **key event** in GA4. Keep contact clicks as intent events, separate from genuine enquiries. Check the Realtime report with an incognito visit after allowing analytics. Confirm exactly one page_view per page and one generate_lead for a successful, non-duplicate test enquiry. Never put personal data in UTM campaign labels.

Existing Universal Analytics historical data is not converted into new GA4 observations. Archive any available old reports separately and record the GA4 collection start date.

## 3. If you prefer GTM

Create/select the website container at https://tagmanager.google.com/ and enter its `GTM-…` ID in Website Settings. Select **GTM** mode; the site will not also load a direct GA4 tag.

In the container:

1. Create Data Layer Variables for `page_location`, `page_path`, `contact_type`, `error_type`, `metric_name`, `value`, `traffic_source`, `traffic_medium` and `campaign_name`.
2. Create one Google tag for the real Measurement ID. Set `send_page_view` to false, `page_location` to the clean data-layer value, `page_referrer` to an empty string, `allow_google_signals` to false and `allow_ad_personalization_signals` to false. Use Initialization → All Pages; the container itself only loads after consent.
3. Create a GA4 Event tag using `{{Event}}` as the event name. Trigger it with Custom Event regular expression:
   `^(page_view|form_start|form_error|generate_lead|contact_click|scroll_depth|engagement|web_vital|page_error)$`
4. Pass only the listed clean variables. Do not use Form Element, form values, full Page URL, Click URL, customer/lead IDs, names, phone numbers or email addresses.
5. Require `analytics_storage` consent on analytics tags. Do not add advertising or unrelated third-party tags without an explicit separate consent design.
6. Use Preview/Tag Assistant to check consent, event names and duplicates, then publish the container. Disable conflicting GA4 Enhanced Measurement as described above.

A saved ID alone does not prove Google received data. Verification requires Tag Assistant/Realtime and dated evidence from the real Google property.

## 4. Optional: authenticated GA4 reports inside the admin portal

The first-party dashboard works without this step. To also show real Google audience, channels, popular pages and event reports:

1. Enable **Google Analytics Data API** in the Google Cloud project.
2. Create a dedicated service account and grant its email **Viewer** access to the GA4 property. It does not need account-owner access or domain-wide delegation.
3. Download its JSON key privately. Place it outside `public_html`, outside every release directory, for example `/home/tabegomk/tabeeb-production/shared/ga4-reporting.json`, with permissions `600`. Never commit or upload the key to GitHub, and never share it in chat.
4. In the private production environment file `/home/tabegomk/tabeeb-production/shared/.env`, add:
   `GA4_SERVICE_ACCOUNT_FILE=/home/tabegomk/tabeeb-production/shared/ga4-reporting.json`
5. Save the numeric Property ID in Website Settings.
6. Open Traffic & Performance, select the reporting dates and click **Refresh authenticated GA4 reports**. Successful provider responses are cached with a retrieval timestamp. Failed refreshes retain the previous evidence; no replacement figures are invented.

Account access and this private server file are required before Google reports can be verified. API credentials remain on the host through routine Git deployments. Staging should not receive production reporting credentials.

## 5. Final checks

- Decline analytics: no initial Google scripts or first-party measurement requests; enquiry forms still work.
- Allow analytics: page_view, form_start, form_error and successful generate_lead appear as expected.
- Click call/WhatsApp/email: the event contains a contact type, never the destination details.
- Withdraw consent: first-party measurement stops, identifiers are cleared and Google consent is updated to denied.
- Inspect local and GA4 reports after consented visits. Expect reporting delays and incomplete coverage from opt-outs, blockers and browser limitations.
- Confirm LCP/CLS/INP samples arrive after real visits/interactions; do not interpret missing samples as a passing score.
- Confirm new pages and APIs remain excluded from public indexing and staff role controls.
- Confirm stored CRM test enquiry and email notifications separately.

Google references:
- https://developers.google.com/analytics/devguides/collection/ga4/events
- https://developers.google.com/tag-platform/security/guides/consent
- https://developers.google.com/analytics/devguides/reporting/data/v1/basics
- https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/properties/batchRunReports
- https://developers.google.com/identity/protocols/oauth2/service-account
- https://github.com/GoogleChrome/web-vitals
