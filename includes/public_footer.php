<?php
/**
 * Authoritative Public Website Footer Component - Tabeeb Contractor
 * 
 * Powered by BusinessTracker: single-source brand, contact facts, and maps links.
 */

if (!function_exists('get_company_info')) {
    require_once __DIR__ . '/data.php';
}
if (!class_exists('BusinessTracker')) {
    require_once __DIR__ . '/business_tracker.php';
}

$company = get_company_info();
?>
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <img src="/images/logo-horizontal.png?v=3.4" alt="<?= htmlspecialchars($company['brand_name']) ?>" class="footer-logo" width="220" height="52">
                    <p class="footer-desc">
                        <?= htmlspecialchars($company['brand_name']) ?> is Singapore's dedicated residential and commercial renovation contractor. Providing direct trade solutions in hacking, masonry tiling, vinyl, waterproofing, plumbing, electrical, and commercial reinstatement.
                    </p>
                    <div class="footer-uen-badge" style="font-size: 13px; color: #94a3b8; margin-top: 10px;">
                        UEN: <?= htmlspecialchars($company['uen']) ?>
                    </div>
                </div>

                <div class="footer-col">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="/">Home</a></li>
                        <li><a href="/services">All Services</a></li>
                        <li><a href="/projects">Case Studies & Projects</a></li>
                        <li><a href="/about">About Us</a></li>
                        <li><a href="/blog">Construction Guides & Blog</a></li>
                        <li><a href="/contact">Contact Office</a></li>
                        <li><a href="/admin">Staff Admin Portal</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h3>Specialist Services</h3>
                    <ul>
                        <li><a href="/renovation-contractor-singapore">Renovation Contractor SG</a></li>
                        <li><a href="/hdb-renovation">HDB Renovation Contractor</a></li>
                        <li><a href="/tiling-works">Tiling Works & Screeding</a></li>
                        <li><a href="/vinyl-flooring">Vinyl Flooring Supply & Lay</a></li>
                        <li><a href="/hacking-demolition">Hacking & Demolition</a></li>
                        <li><a href="/painting-works">Painting & Plastering</a></li>
                        <li><a href="/waterproofing">Waterproofing Solutions</a></li>
                        <li><a href="/plumbing">Plumbing & Sanitary Works</a></li>
                        <li><a href="/electrical">Electrical Wiring & Lighting</a></li>
                        <li><a href="/ceiling-partition">False Ceiling & Partitions</a></li>
                        <li><a href="/reinstatement">Commercial Reinstatement</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h3>Office & Contact</h3>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">📍</span>
                        <span class="footer-contact-text">
                            <a href="<?= htmlspecialchars($company['google_maps_url']) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;" id="footer-maps-link">
                                <?= htmlspecialchars($company['address']) ?>
                            </a>
                        </span>
                    </div>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">📞</span>
                        <span class="footer-contact-text">
                            <a href="tel:<?= htmlspecialchars($company['phone_raw']) ?>" style="color: inherit;" id="footer-phone-link"><?= htmlspecialchars($company['phone']) ?></a>
                        </span>
                    </div>
                    <div class="footer-contact-item">
                        <span class="footer-contact-icon">✉️</span>
                        <span class="footer-contact-text">
                            <a href="mailto:<?= htmlspecialchars($company['email']) ?>" style="color: inherit;" id="footer-email-link"><?= htmlspecialchars($company['email']) ?></a>
                        </span>
                    </div>
                    <?php if (!empty($company['opening_hours'])): ?>
                        <div class="footer-contact-item">
                            <span class="footer-contact-icon">🕒</span>
                            <span class="footer-contact-text"><?= htmlspecialchars($company['opening_hours']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="footer-contact-item" style="margin-top: 14px;">
                        <a href="<?= htmlspecialchars($company['google_maps_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-white" style="font-size: 12px; padding: 6px 14px; display: inline-flex; align-items: center; gap: 6px;" id="footer-directions-btn">
                            🗺️ Open in Google Maps
                        </a>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p class="footer-copyright">&copy; <?= date('Y') ?> <?= htmlspecialchars($company['brand_name']) ?>. All rights reserved. UEN: <?= htmlspecialchars($company['uen']) ?>.</p>
                <div class="footer-legal-links">
                    <a href="/privacy">Privacy Policy</a>
                    <a href="/terms">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Quote Popup Modal -->
    <div class="modal" id="quoteModal">
        <div class="modal-content" style="max-width: 600px; text-align: left; position: relative;">
            <button class="modal-close-btn" id="closeQuoteModalBtn" aria-label="Close modal">&times;</button>
            <h3 style="font-size: 24px; margin-bottom: 10px; font-family: var(--font-heading); color: var(--color-text-dark);">Request a Project Quotation</h3>
            <p style="color: var(--color-text-muted); font-size: 14px; margin-bottom: 25px;">Provide details regarding your project scope and our contracting team will review your specifications.</p>
            <form id="popupQuoteForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="form_loaded_ts" value="<?= generate_form_token() ?>">
                <input type="hidden" name="consent_mechanism" value="submission_notice_consent">
                <input type="hidden" name="source" value="website_modal_quote">
                <div class="form-group-row">
                    <div class="form-group">
                        <label for="popup-name">Your Name *</label>
                        <input type="text" id="popup-name" name="name" class="form-control" placeholder="John Doe" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="popup-phone">Phone Number (Singapore) *</label>
                        <input type="tel" id="popup-phone" name="phone" class="form-control" placeholder="+65 8648 4883" required maxlength="30">
                    </div>
                </div>
                <div class="form-group">
                    <label for="popup-email">Email Address</label>
                    <input type="email" id="popup-email" name="email" class="form-control" placeholder="john@example.com" maxlength="150">
                </div>
                <div class="form-group">
                    <label for="popup-service">Select Required Service *</label>
                    <select id="popup-service" name="service" class="form-control" required>
                        <option value="" disabled selected>-- Select a service --</option>
                        <option value="renovation-contractor-singapore">Renovation Contractor in Singapore</option>
                        <option value="hdb-renovation">HDB Renovation Contractor</option>
                        <option value="tiling-works">Tiling Works & Screeding</option>
                        <option value="vinyl-flooring">Vinyl Flooring Works</option>
                        <option value="hacking-demolition">Hacking & Demolition Works</option>
                        <option value="waterproofing">Waterproofing Works</option>
                        <option value="plumbing">Plumbing Works</option>
                        <option value="electrical">Electrical Works</option>
                        <option value="painting-works">Painting & Plastering Works</option>
                        <option value="ceiling-partition">Ceiling & Partition Works</option>
                        <option value="reinstatement">Commercial Reinstatement Works</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="popup-message">Project Description & Requirements *</label>
                    <textarea id="popup-message" name="message" class="form-control" placeholder="Describe the scope of works, property type, and requirements..." required rows="4" maxlength="3000"></textarea>
                </div>
                <!-- Anti-bot honeypot -->
                <input type="text" name="website_url_hp" style="display:none !important;" tabindex="-1" autocomplete="off">
                <button type="submit" class="btn btn-primary" style="width: 100%;" id="popup-submit-btn">Submit Quotation Request</button>
                <p style="font-size: 12px; color: #64748b; margin-top: 10px; line-height: 1.4; text-align: center;">
                    By submitting, you consent to Tabeeb Contractor contacting you regarding your project enquiry under the Singapore Personal Data Protection Act (PDPA). View our <a href="/privacy" style="color: #0284c7; text-decoration: underline;">Privacy Policy</a>.
                </p>
            </form>
        </div>
    </div>

    <!-- Unified Accessible Success Modal -->
    <div class="modal" id="successModal" role="dialog" aria-modal="true" aria-labelledby="successModalTitle">
        <div class="modal-content" style="max-width: 520px; text-align: center; position: relative;">
            <button class="modal-close-btn" id="closeSuccessModalX" aria-label="Close dialog">&times;</button>
            <div class="modal-icon" style="font-size: 48px; color: #16a34a; margin-bottom: 12px;">✓</div>
            <h3 id="successModalTitle" style="font-size: 22px; margin-bottom: 10px; font-family: var(--font-heading); color: var(--color-text-dark);">Quotation Request Received</h3>
            <p style="color: var(--color-text-muted); font-size: 14px; margin-bottom: 20px; line-height: 1.6;" id="successModalMsg">
                Thank you! We have received your inquiry. Reference: <strong id="leadRefNumber" class="modal-lead-ref"></strong>. Our project coordinator will review your scope and contact you shortly.
            </p>
            <button class="btn btn-primary" id="closeSuccessModalBtn" style="width: 100%;" type="button">Close</button>
        </div>
    </div>

    <!-- Floating WhatsApp Direct Button -->
    <a href="https://wa.me/<?= htmlspecialchars($company['whatsapp_raw']) ?>?text=<?= urlencode('Hello Tabeeb Contractor, I would like to enquire about a renovation project.') ?>" class="floating-whatsapp" target="_blank" rel="noopener noreferrer" aria-label="Chat with Tabeeb Contractor on WhatsApp" id="floating-wa-btn">
        <div class="whatsapp-pulse"></div>
        <svg width="32" height="32" viewBox="0 0 24 24" fill="white"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2m.01 1.67c2.2 0 4.26.86 5.82 2.42a8.225 8.225 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.196 8.196 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24m4.52 11.66c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.02-1.24-.75-.67-1.26-1.49-1.4-1.74-.15-.25-.02-.39.11-.51.11-.11.25-.29.37-.43.13-.15.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.44 1.03 2.61.13.17 1.77 2.7 4.29 3.78.6.26 1.07.41 1.44.53.61.19 1.16.17 1.6.1.49-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.07-.1-.23-.17-.48-.29z"/></svg>
        <span class="whatsapp-tooltip">Chat with us on WhatsApp</span>
    </a>

    <script>
        window.TABEEB_CONTACT = {
            phone: <?= json_encode($company['phone']) ?>,
            phoneRaw: <?= json_encode($company['phone_raw']) ?>,
            whatsappRaw: <?= json_encode($company['whatsapp_raw']) ?>
        };
    </script>
    <!-- Application Script -->
    <script src="/app.js?v=3.5"></script>
