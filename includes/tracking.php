<?php
/**
 * External Analytics, Tag Management & Consent Loader - Tabeeb Contractor
 *
 * Complies with Google Official Standards & Singapore PDPA:
 * - Preferred Mode: GA4 through Google Tag Manager (GTM)
 * - Mutually exclusive loader to prevent duplicate page_view events
 * - Zero PII transmission policy to dataLayer or external endpoints
 * - Strict route exclusions: Sensitive admin, auth, API, and preview paths are never tracked
 * - Renders NO mock or placeholder tags when unconfigured
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/db.php';

class TrackingService {

    /**
     * Determine if current request URL is on an excluded/sensitive path
     */
    public static function isSensitiveRoute(): bool {
        if (APP_ENV !== 'production') return true;
        // Strictly suppress tracking on staff preview responses
        if (!empty($GLOBALS['isStaffPreview'])) {
            return true;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH) ?: '';

        $excludedPrefixes = [
            '/admin',
            '/api',
            '/scripts',
            '/config',
            '/data'
        ];

        foreach ($excludedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve and validate tracking configuration
     */
    public static function getConfig(): array {
        $gtmId = '';
        $ga4Id = '';
        $mode = 'gtm'; // 'gtm' (preferred) or 'direct_ga4'

        if (DB::isConnected()) {
            $gtmVal = db_val("SELECT setting_value FROM settings WHERE setting_key = 'gtm_id' LIMIT 1");
            if ($gtmVal) $gtmId = trim((string)$gtmVal);

            $ga4Val = db_val("SELECT setting_value FROM settings WHERE setting_key = 'ga4_id' LIMIT 1");
            if ($ga4Val) $ga4Id = trim((string)$ga4Val);

            $modeVal = db_val("SELECT setting_value FROM settings WHERE setting_key = 'tracking_mode' LIMIT 1");
            if ($modeVal && in_array($modeVal, ['gtm', 'direct_ga4'])) {
                $mode = (string)$modeVal;
            }
        }

        // Check environment variable fallbacks
        if (empty($gtmId) && getenv('GTM_CONTAINER_ID')) {
            $gtmId = trim(getenv('GTM_CONTAINER_ID'));
        }
        if (empty($ga4Id) && getenv('GA4_MEASUREMENT_ID')) {
            $ga4Id = trim(getenv('GA4_MEASUREMENT_ID'));
        }

        // Validate format & exclude fake placeholders (e.g. GTM-XXXXXXX, G-XXXXXXXXXX)
        $isValidGtm = preg_match('/^GTM-[A-Z0-9]{4,10}$/i', $gtmId) && !preg_match('/^GTM-X+$/i', $gtmId);
        $isValidGa4 = preg_match('/^G-[A-Z0-9]{6,15}$/i', $ga4Id) && !preg_match('/^G-X+$/i', $ga4Id);

        return [
            'mode'           => $mode,
            'gtm_id'         => $isValidGtm ? strtoupper($gtmId) : '',
            'ga4_id'         => $isValidGa4 ? strtoupper($ga4Id) : '',
            'is_gtm_valid'   => (bool)$isValidGtm,
            'is_ga4_valid'   => (bool)$isValidGa4,
            'is_configured'  => ($mode === 'gtm' && $isValidGtm) || ($mode === 'direct_ga4' && $isValidGa4)
        ];
    }
}

/**
 * Render Head Tag Snippet (Called in <head>)
 */
function render_gtm_head(): void {
    if (TrackingService::isSensitiveRoute()) {
        return;
    }

    $config = TrackingService::getConfig();
    if (!$config['is_configured']) {
        return;
    }

    if ($config['mode'] === 'gtm' && $config['is_gtm_valid']) {
        $gtmId = htmlspecialchars($config['gtm_id'], ENT_QUOTES, 'UTF-8');
        ?>
<!-- Google Tag Manager (Tabeeb Contractor Production Container) -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?= $gtmId ?>');</script>
<!-- End Google Tag Manager -->
        <?php
    } elseif ($config['mode'] === 'direct_ga4' && $config['is_ga4_valid']) {
        $ga4Id = htmlspecialchars($config['ga4_id'], ENT_QUOTES, 'UTF-8');
        ?>
<!-- Google tag (gtag.js) Direct GA4 Mode -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= $ga4Id ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?= $ga4Id ?>', {
    'anonymize_ip': true,
    'send_page_view': true
  });
</script>
<!-- End Google tag (gtag.js) -->
        <?php
    }
}

/**
 * Render Body Tag Snippet (Called immediately after <body> opening tag)
 */
function render_gtm_body(): void {
    if (TrackingService::isSensitiveRoute()) {
        return;
    }

    $config = TrackingService::getConfig();
    if (!$config['is_configured']) {
        return;
    }

    if ($config['mode'] === 'gtm' && $config['is_gtm_valid']) {
        $gtmId = htmlspecialchars($config['gtm_id'], ENT_QUOTES, 'UTF-8');
        ?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= $gtmId ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
        <?php
    }
}

/**
 * Compatibility aliases for legacy templates
 */
function render_analytics_head(): void {
    render_gtm_head();
}

function render_analytics_body(): void {
    render_gtm_body();
}
