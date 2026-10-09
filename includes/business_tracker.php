<?php
/**
 * Business Information Tracker Service - Tabeeb Contractor
 * 
 * Manages client information verification lifecycle, publication approval gates,
 * history audits, and single-source public rendering.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

class BusinessTracker {

    /**
     * Seed initial tracker items from client brief
     */
    public static function seedInitialItems(): void {
        if (!DB::isConnected()) {
            return;
        }

        $items = [
            // Company Identity
            [
                'item_key'          => 'brand_name',
                'category'          => 'company_identity',
                'title'             => 'Public Brand Name',
                'current_value'     => 'Tabeeb Contractor',
                'draft_value'       => 'Tabeeb Contractor',
                'status'            => 'client_confirmed',
                'evidence_ref'      => 'Client brief 9 Oct 2026',
                'publication_state' => 'published',
                'next_action'       => 'Use brand without legal suffix until legal change completes.'
            ],
            [
                'item_key'          => 'uen_number',
                'category'          => 'company_identity',
                'title'             => 'UEN (Unique Entity Number)',
                'current_value'     => '202506878W',
                'draft_value'       => '202506878W',
                'status'            => 'client_confirmed',
                'evidence_ref'      => 'Client supplied in brief 9 Oct 2026',
                'publication_state' => 'published',
                'next_action'       => 'Display as supplied UEN; do not add ACRA badge until verified.'
            ],
            [
                'item_key'          => 'registered_legal_name',
                'category'          => 'company_identity',
                'title'             => 'Current Registered Legal Name',
                'current_value'     => 'Bless Global (Exact suffix pending)',
                'draft_value'       => 'Bless Global',
                'status'            => 'received_needs_clarification',
                'evidence_ref'      => 'Client note',
                'publication_state' => 'hidden',
                'internal_notes'    => 'Reported as Bless Global. Keep private; do not publish unverified operator claims.',
                'next_action'       => 'Confirm exact registered name from ACRA profile.'
            ],
            [
                'item_key'          => 'proposed_name_change',
                'category'          => 'company_identity',
                'title'             => 'Proposed Legal Name Change',
                'current_value'     => 'Tabeeb (Pending ACRA filing)',
                'draft_value'       => 'Tabeeb Contractor Pte Ltd',
                'status'            => 'pending',
                'evidence_ref'      => 'Client notification',
                'publication_state' => 'hidden',
                'next_action'       => 'Obtain updated ACRA Business Profile upon completion.'
            ],
            [
                'item_key'          => 'business_email',
                'category'          => 'contacts',
                'title'             => 'Public Business Email',
                'current_value'     => 'info@tabeebgroup.com',
                'draft_value'       => 'info@tabeebgroup.com',
                'status'            => 'client_confirmed',
                'evidence_ref'      => 'Client supplied',
                'publication_state' => 'published',
                'next_action'       => 'Use in public contact links and verify SMTP deliverability.'
            ],
            [
                'item_key'          => 'business_address',
                'category'          => 'contacts',
                'title'             => 'Operating Business Address',
                'current_value'     => '61 Kaki Bukit Ave 1, #03-34 Shun Li Industrial Park, Singapore 417943',
                'draft_value'       => '61 Kaki Bukit Ave 1, #03-34 Shun Li Industrial Park, Singapore 417943',
                'status'            => 'client_confirmed',
                'evidence_ref'      => 'Confirmed by client in source review',
                'publication_state' => 'published',
                'next_action'       => 'Maintain consistency across contact, footer, and schema.'
            ],
            [
                'item_key'          => 'main_phone',
                'category'          => 'contacts',
                'title'             => 'Main Hotline Telephone',
                'current_value'     => '+65 8648 4883',
                'draft_value'       => '+65 8648 4883',
                'status'            => 'client_confirmed',
                'evidence_ref'      => 'Client confirmed hotline',
                'publication_state' => 'published',
                'next_action'       => 'Maintain consistency across header, footer, call links and schema.'
            ],
            [
                'item_key'          => 'whatsapp_number',
                'category'          => 'contacts',
                'title'             => 'WhatsApp Business Availability',
                'current_value'     => '+65 8648 4883',
                'draft_value'       => '+65 8648 4883',
                'status'            => 'client_confirmed',
                'evidence_ref'      => 'Floating widget & communication channel',
                'publication_state' => 'published',
                'next_action'       => 'Ensure continuous mobile response availability.'
            ],
            [
                'item_key'          => 'website_reference',
                'category'          => 'marketing',
                'title'             => 'Client Website Reference',
                'current_value'     => 'https://www.leongyik.com.sg/',
                'draft_value'       => 'https://www.leongyik.com.sg/',
                'status'            => 'client_confirmed',
                'evidence_ref'      => 'Received from client 9 Oct 2026',
                'publication_state' => 'hidden',
                'internal_notes'    => 'Structure inspiration only. Do not copy assets, claims, or text.',
                'next_action'       => 'Reference contractor layout standards for original Tabeeb content.'
            ],
            [
                'item_key'          => 'google_maps_url',
                'category'          => 'contacts',
                'title'             => 'Google Maps / Business Profile URL',
                'current_value'     => 'https://www.google.com/maps/place/TABEEB+CONTRACTOR+PTE+LTD/@1.3363576,103.9091695,1128m/data=!3m2!1e3!4b1!4m6!3m5!1s0x31da17d8a42c489f:0x834162f16ce7e27b!8m2!3d1.3363576!4d103.9117444!16s%2Fg%2F11zxps0z_m?entry=ttu&g_ep=EgoyMDI2MTAwNS4wIKXMDSoASAFQAw%3D%3D',
                'draft_value'       => 'https://www.google.com/maps/place/TABEEB+CONTRACTOR+PTE+LTD/@1.3363576,103.9091695,1128m/data=!3m2!1e3!4b1!4m6!3m5!1s0x31da17d8a42c489f:0x834162f16ce7e27b!8m2!3d1.3363576!4d103.9117444!16s%2Fg%2F11zxps0z_m?entry=ttu&g_ep=EgoyMDI2MTAwNS4wIKXMDSoASAFQAw%3D%3D',
                'status'            => 'client_confirmed',
                'evidence_ref'      => 'Supplied by client 9 Oct 2026',
                'publication_state' => 'published',
                'next_action'       => 'Use for Open in Google Maps directions link.'
            ],
            // Pending Items Tracking
            [
                'item_key'          => 'acra_profile_document',
                'category'          => 'company_identity',
                'title'             => 'Updated ACRA Business Profile',
                'current_value'     => null,
                'draft_value'       => null,
                'status'            => 'pending',
                'publication_state' => 'hidden',
                'next_action'       => 'Request ACRA PDF upon name change completion.'
            ],
            [
                'item_key'          => 'gst_status',
                'category'          => 'company_identity',
                'title'             => 'GST Registration Status',
                'current_value'     => 'Not GST Registered (Prices are Net)',
                'draft_value'       => null,
                'status'            => 'pending',
                'publication_state' => 'hidden',
                'next_action'       => 'Confirm GST status and quotation tax wording.'
            ],
            [
                'item_key'          => 'bca_registration',
                'category'          => 'credentials',
                'title'             => 'BCA Builder Registration',
                'current_value'     => null,
                'draft_value'       => null,
                'status'            => 'pending',
                'publication_state' => 'hidden',
                'next_action'       => 'Obtain license category certificate before advertising BCA claims.'
            ],
            [
                'item_key'          => 'bizsafe_certification',
                'category'          => 'credentials',
                'title'             => 'bizSAFE Level Certification',
                'current_value'     => null,
                'draft_value'       => null,
                'status'            => 'pending',
                'publication_state' => 'hidden',
                'next_action'       => 'Obtain WSHC bizSAFE certificate before publishing badge.'
            ],
            [
                'item_key'          => 'pub_plumber_license',
                'category'          => 'credentials',
                'title'             => 'PUB Licensed Plumber License',
                'current_value'     => null,
                'draft_value'       => null,
                'status'            => 'pending',
                'publication_state' => 'hidden',
                'next_action'       => 'Record license number and holder relationship.'
            ],
            [
                'item_key'          => 'ema_electrical_license',
                'category'          => 'credentials',
                'title'             => 'EMA Licensed Electrical Worker Details',
                'current_value'     => null,
                'draft_value'       => null,
                'status'            => 'pending',
                'publication_state' => 'hidden',
                'next_action'       => 'Record licensed worker name and grade.'
            ],
            [
                'item_key'          => 'genuine_projects',
                'category'          => 'projects',
                'title'             => 'Completed Case Studies Portfolio',
                'current_value'     => null,
                'draft_value'       => null,
                'status'            => 'pending',
                'publication_state' => 'hidden',
                'next_action'       => 'Collect authentic project photos, property types, and scope.'
            ],
            [
                'item_key'          => 'genuine_reviews',
                'category'          => 'reviews',
                'title'             => 'Customer Testimonials & Reviews',
                'current_value'     => null,
                'draft_value'       => null,
                'status'            => 'pending',
                'publication_state' => 'hidden',
                'next_action'       => 'Collect approved client reviews with publication permission.'
            ]
        ];

        foreach ($items as $item) {
            $exists = db_val("SELECT `id` FROM `business_information` WHERE `item_key` = ? LIMIT 1", [$item['item_key']]);
            if (!$exists) {
                db_exec(
                    "INSERT INTO `business_information` 
                     (`item_key`, `category`, `title`, `current_value`, `draft_value`, `status`, `evidence_ref`, `publication_state`, `internal_notes`, `next_action`, `created_at`) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $item['item_key'],
                        $item['category'],
                        $item['title'],
                        $item['current_value'] ?? null,
                        $item['draft_value'] ?? null,
                        $item['status'],
                        $item['evidence_ref'] ?? null,
                        $item['publication_state'] ?? 'hidden',
                        $item['internal_notes'] ?? null,
                        $item['next_action'] ?? null
                    ]
                );
            }
        }
    }

    /**
     * Get single published value for public website rendering
     */
    public static function getPublicValue(string $itemKey, string $default = ''): string {
        if (!DB::isConnected()) {
            return $default;
        }

        $val = db_val(
            "SELECT `current_value` FROM `business_information` 
             WHERE `item_key` = ? AND `publication_state` = 'published' LIMIT 1",
            [$itemKey]
        );

        return ($val !== false && $val !== null) ? (string)$val : $default;
    }

    /**
     * Get summary metrics for the Business Tracker
     */
    public static function getMetrics(): array {
        if (!DB::isConnected()) {
            return ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'published' => 0, 'hidden' => 0];
        }

        $total = (int)db_val("SELECT COUNT(*) FROM `business_information`");
        $confirmed = (int)db_val("SELECT COUNT(*) FROM `business_information` WHERE `status` IN ('client_confirmed', 'document_verified')");
        $pending = (int)db_val("SELECT COUNT(*) FROM `business_information` WHERE `status` IN ('pending', 'received_needs_clarification')");
        $published = (int)db_val("SELECT COUNT(*) FROM `business_information` WHERE `publication_state` = 'published'");
        $hidden = (int)db_val("SELECT COUNT(*) FROM `business_information` WHERE `publication_state` = 'hidden'");

        return [
            'total'     => $total,
            'confirmed' => $confirmed,
            'pending'   => $pending,
            'published' => $published,
            'hidden'    => $hidden
        ];
    }
}
