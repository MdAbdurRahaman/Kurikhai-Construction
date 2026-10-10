<?php
/**
 * Contact & Quotation Request Page - Tabeeb Contractor Singapore
 * URL: https://tabeebgroup.com/contact
 */

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/business_tracker.php';
require_once __DIR__ . '/includes/tracking.php';

$company = get_company_info();
$baseUrl = get_base_url();
$canonicalUrl = $baseUrl . '/contact';
$pageTitle = 'Contact ' . htmlspecialchars($company['brand_name']) . ' | Request Project Quote';
$pageDesc = "Request an on-site consultation and quote for your renovation or reinstatement project in Singapore. Visit our Kaki Bukit office or contact " . $company['email'] . " / " . $company['phone'] . ".";

$preselectedService = trim($_GET['service'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
    
    <!-- Geo Targeting Singapore -->
    <meta name="geo.region" content="SG">
    <meta name="geo.placename" content="Singapore">
    <meta name="geo.position" content="1.3364;103.9066">
    <meta name="ICBM" content="1.3364, 103.9066">

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="64x64" href="/images/favicon.png">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= $canonicalUrl ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:image" content="<?= $baseUrl ?>/images/hero-bg.webp">
    <meta property="og:site_name" content="<?= htmlspecialchars($company['brand_name']) ?>">
    <meta property="og:locale" content="en_SG">

    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "ContactPage",
          "@id": "https://www.tabeebgroup.com/contact#webpage",
          "url": "https://www.tabeebgroup.com/contact",
          "name": <?= json_encode($pageTitle) ?>,
          "isPartOf": {
            "@type": "WebSite",
            "name": <?= json_encode($company['brand_name']) ?>,
            "url": "https://www.tabeebgroup.com/"
          }
        },
        {
          "@type": ["GeneralContractor", "HomeAndConstructionBusiness"],
          "@id": "https://www.tabeebgroup.com/#organization",
          "name": <?= json_encode($company['brand_name']) ?>,
          "telephone": <?= json_encode($company['phone']) ?>,
          "email": <?= json_encode($company['email']) ?>,
          "address": {
            "@type": "PostalAddress",
            "streetAddress": "61 Kaki Bukit Ave 1, #03-34 Shun Li Industrial Park",
            "addressLocality": "Singapore",
            "postalCode": "417943",
            "addressCountry": "SG"
          },
          "sameAs": [
            <?= json_encode($company['google_maps_url']) ?>
          ]
        },
        {
          "@type": "BreadcrumbList",
          "itemListElement": [
            {
              "@type": "ListItem",
              "position": 1,
              "name": "Home",
              "item": "https://www.tabeebgroup.com/"
            },
            {
              "@type": "ListItem",
              "position": 2,
              "name": "Contact Us",
              "item": "https://www.tabeebgroup.com/contact"
            }
          ]
        }
      ]
    }
    </script>
    
    <link rel="stylesheet" href="/style.css?v=3.5">
    <?php render_gtm_head(); ?>
</head>
<body>
    <?php render_gtm_body(); ?>
    <?php $activeNav = 'contact'; require_once __DIR__ . '/includes/public_header.php'; ?>

    <!-- Subpage Hero Header -->
    <section class="subpage-hero">
        <div class="container">
            <h1 class="fade-in-up">Contact Tabeeb Contractor | Request Quotation</h1>
            <div class="breadcrumbs fade-in-up">
                <a href="/">Home</a> &gt; <span>Contact Us</span>
            </div>
        </div>
    </section>

    <!-- Main Contact Section -->
    <section class="section" id="contact-wrapper">
        <div class="container">
            <div class="contact-split">
                
                <!-- Left Column: Official Contact Details -->
                <div class="contact-info-panel fade-in-up">
                    <span class="section-tagline">Direct Assistance</span>
                    <h2>Speak with Our Estimators & Project Coordinators</h2>
                    <p style="color: var(--color-text-muted); font-size: 15px; margin-bottom: 30px;">
                        Whether you are planning a commercial office reinstatement, residential HDB hacking and tiling, or complete turnkey renovation, our team is ready to evaluate your layout plans and provide transparent quotations.
                    </p>

                    <div class="contact-methods">
                        <div class="contact-card">
                            <div class="contact-card-icon">📍</div>
                            <div class="contact-card-text">
                                <h4>Headquarters & Operating Office</h4>
                                <p><?= htmlspecialchars($company['address']) ?></p>
                            </div>
                        </div>

                        <div class="contact-card">
                            <div class="contact-card-icon">📞</div>
                            <div class="contact-card-text">
                                <h4>Hotline & Inquiries</h4>
                                <p><a href="tel:<?= htmlspecialchars($company['phone_raw']) ?>" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($company['phone']) ?></a></p>
                            </div>
                        </div>

                        <div class="contact-card">
                            <div class="contact-card-icon">✉️</div>
                            <div class="contact-card-text">
                                <h4>Email Inquiries</h4>
                                <p><a href="mailto:<?= htmlspecialchars($company['email']) ?>" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($company['email']) ?></a></p>
                            </div>
                        </div>

                        <div class="contact-card">
                            <div class="contact-card-icon">💬</div>
                            <div class="contact-card-text">
                                <h4>WhatsApp Hotline</h4>
                                <p><a href="https://wa.me/<?= htmlspecialchars($company['phone_raw']) ?>?text=<?= urlencode('Hello Tabeeb Contractor, I would like to enquire about a renovation project.') ?>" target="_blank" rel="noopener noreferrer" style="color: #25d366; font-weight: 600;">Message Us Directly on WhatsApp</a></p>
                            </div>
                        </div>

                        <?php if (!empty($company['opening_hours'])): ?>
                            <div class="contact-card">
                                <div class="contact-card-icon">🕒</div>
                                <div class="contact-card-text">
                                    <h4>Operating Hours</h4>
                                    <p><?= htmlspecialchars($company['opening_hours']) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column: Detail Form -->
                <div class="contact-form-panel fade-in-up">
                    <form id="leadForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <!-- Anti-Spam Honeypot Field -->
                        <input type="text" name="website_url_hp" style="display:none !important;" tabindex="-1" autocomplete="off" aria-hidden="true">
                        
                        <div class="form-group-row">
                            <div class="form-group">
                                <label for="name">Your Name *</label>
                                <input type="text" id="name" name="name" class="form-control" placeholder="John Doe" required maxlength="100">
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number (Singapore) *</label>
                                <input type="tel" id="phone" name="phone" class="form-control" placeholder="+65 8648 4883" required maxlength="30">
                            </div>
                        </div>

                        <div class="form-group-row">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" class="form-control" placeholder="john@example.com" maxlength="150">
                            </div>
                            <div class="form-group">
                                <label for="budget">Estimated Budget Range</label>
                                <select id="budget" name="budget" class="form-control">
                                    <option value="" selected>-- Select a budget range (Optional) --</option>
                                    <option value="Under $5,000">Under $5,000 (Minor Repairs/Hacking)</option>
                                    <option value="$5,000 - $20,000">$5,000 - $20,000 (Standard Renovation)</option>
                                    <option value="$20,000 - $50,000">$20,000 - $50,000 (Whole House / Large Office)</option>
                                    <option value="Above $50,000">Above $50,000 (Extensions / Complex Works)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="service">Select Primary Service *</label>
                            <select id="service" name="service" class="form-control" required>
                                <option value="" disabled <?= empty($preselectedService) ? 'selected' : '' ?>>-- Select a service --</option>
                                <option value="renovation-contractor-singapore" <?= $preselectedService === 'renovation-contractor-singapore' ? 'selected' : '' ?>>Renovation Contractor Singapore (General / Commercial)</option>
                                <option value="hdb-renovation" <?= $preselectedService === 'hdb-renovation' ? 'selected' : '' ?>>HDB Renovation Works</option>
                                <option value="hacking-demolition" <?= $preselectedService === 'hacking-demolition' ? 'selected' : '' ?>>1. Hacking & Demolition Works</option>
                                <option value="tiling-works" <?= $preselectedService === 'tiling-works' ? 'selected' : '' ?>>2. Tiles Flooring Works & Screeding</option>
                                <option value="vinyl-flooring" <?= $preselectedService === 'vinyl-flooring' ? 'selected' : '' ?>>3. Vinyl Flooring Works</option>
                                <option value="waterproofing" <?= $preselectedService === 'waterproofing' ? 'selected' : '' ?>>4. Waterproofing Works</option>
                                <option value="plumbing" <?= $preselectedService === 'plumbing' ? 'selected' : '' ?>>5. Plumbing Works</option>
                                <option value="electrical" <?= $preselectedService === 'electrical' ? 'selected' : '' ?>>6. Electrical Works</option>
                                <option value="painting-works" <?= $preselectedService === 'painting-works' ? 'selected' : '' ?>>7. Painting Works</option>
                                <option value="plastering" <?= $preselectedService === 'plastering' ? 'selected' : '' ?>>8. Plastering & Skim Coating Works</option>
                                <option value="ceiling-partition" <?= $preselectedService === 'ceiling-partition' ? 'selected' : '' ?>>9. Ceiling & Partition Works</option>
                                <option value="reinstatement" <?= $preselectedService === 'reinstatement' ? 'selected' : '' ?>>10. Reinstatement Works</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="message">Detailed Scope of Work *</label>
                            <textarea id="message" name="message" class="form-control" rows="4" placeholder="Please specify the property type (HDB, Condo, Landed, Office, Industrial), floor area, and scope..." required maxlength="3000"></textarea>
                        </div>
                        
                        <!-- PDPA and Compliance awareness check -->
                        <div class="form-group" style="display: flex; align-items: flex-start; gap: 10px; margin-top: 25px;">
                            <input type="checkbox" id="consent" name="consent" style="margin-top: 5px; cursor: pointer;" required>
                            <label for="consent" style="font-weight: 500; font-size: 13px; color: var(--color-text-muted); cursor: pointer; line-height: 1.4;">
                                I consent to Tabeeb Contractor collecting and using my contact details to respond to this quotation inquiry in accordance with the Singapore Personal Data Protection Act (PDPA) and the <a href="/privacy" style="color: var(--color-accent); text-decoration: underline;" target="_blank">Privacy Policy</a>. *
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 15px;" id="lead-submit-btn">Send Quote Request</button>
                    </form>
                </div>

            </div>
        </div>
    </section>

    <!-- Map & Location Section -->
    <section class="section section-bg-light" style="padding-top: 20px; padding-bottom: 60px;">
        <div class="container">
            <div style="background: var(--color-bg-white); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-sm); border: 1px solid rgba(0,0,0,0.06);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h3 style="font-size: 20px; color: var(--color-primary); font-family: var(--font-heading);">Our Office Location</h3>
                        <p style="color: var(--color-text-muted); font-size: 14px;"><?= htmlspecialchars($company['address']) ?></p>
                    </div>
                    <a href="<?= htmlspecialchars($company['google_maps_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary" style="padding: 8px 18px; font-size: 13px;">
                        Open in Google Maps
                    </a>
                </div>
                <div style="width: 100%; height: 380px; border-radius: var(--radius-sm); overflow: hidden; border: 1px solid #e2e8f0;">
                    <iframe 
                        title="Tabeeb Contractor Office Location"
                        src="https://maps.google.com/maps?q=61+Kaki+Bukit+Ave+1,+Shun+Li+Industrial+Park,+Singapore+417943&t=&z=15&ie=UTF8&iwloc=&output=embed" 
                        width="100%" 
                        height="100%" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>
        </div>
    </section>

    <!-- Success Modal Popup -->
    <div class="modal" id="successModal">
        <div class="modal-content">
            <div class="modal-icon">✓</div>
            <h3 style="font-size: 24px; margin-bottom: 10px; font-family: var(--font-heading); color: var(--color-text-dark);">Quotation Request Received</h3>
            <p style="color: var(--color-text-muted); font-size: 14px; margin-bottom: 25px; line-height: 1.5;" id="successModalMsg">
                Thank you. We have received your inquiry. Reference: <strong id="leadRefNumber"></strong>. Our project coordinator will review your specifications and contact you shortly.
            </p>
            <button class="btn btn-primary" id="closeSuccessModalBtn" style="width: 100%;">Close</button>
        </div>
    </div>

    <?php require_once __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
