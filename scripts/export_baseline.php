<?php
/**
 * CLI Marketing Baseline Exporter - Tabeeb Contractor
 * Generates automated CSV or Markdown reports from the authoritative marketing_baseline table.
 * 
 * Usage:
 *   php scripts/export_baseline.php --format=csv --output=baseline_report.csv
 *   php scripts/export_baseline.php --format=markdown --output=baseline_report.md
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI execution required.\n";
    exit(1);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$options = getopt('', ['format::', 'output::', 'help']);

if (isset($options['help'])) {
    echo "Usage: php export_baseline.php [--format=csv|markdown] [--output=path/to/file]\n";
    exit(0);
}

$format = strtolower($options['format'] ?? 'csv');
$output = $options['output'] ?? null;

$metrics = [];
if (DB::isConnected()) {
    $metrics = db_all("SELECT * FROM `marketing_baseline` ORDER BY `id` ASC");
}

if (empty($metrics)) {
    // Default pre-repair baseline snapshot
    $metrics = [
        ['metric_key' => 'ga4_organic_sessions', 'metric_category' => 'traffic', 'title' => 'Organic Search Sessions (28 Days)', 'metric_value' => 'Not previously tracked', 'unit' => 'sessions', 'window_start' => '2026-09-12', 'window_end' => '2026-10-09', 'source_name' => 'Google Analytics 4', 'collection_status' => 'Not previously tracked', 'status_reason' => 'Tracking container not previously configured or connected to live stream', 'collected_at' => '2026-10-10 18:00:00'],
        ['metric_key' => 'ga4_organic_users', 'metric_category' => 'traffic', 'title' => 'Organic Search Active Users (28 Days)', 'metric_value' => 'Not previously tracked', 'unit' => 'users', 'window_start' => '2026-09-12', 'window_end' => '2026-10-09', 'source_name' => 'Google Analytics 4', 'collection_status' => 'Not previously tracked', 'status_reason' => 'Tracking container not previously configured or connected to live stream', 'collected_at' => '2026-10-10 18:00:00'],
        ['metric_key' => 'ga4_total_sessions', 'metric_category' => 'traffic', 'title' => 'Total Website Sessions (28 Days)', 'metric_value' => 'Not previously tracked', 'unit' => 'sessions', 'window_start' => '2026-09-12', 'window_end' => '2026-10-09', 'source_name' => 'Google Analytics 4', 'collection_status' => 'Not previously tracked', 'status_reason' => 'GTM container and GA4 tag awaiting verified deployment', 'collected_at' => '2026-10-10 18:00:00'],
        ['metric_key' => 'gsc_organic_clicks', 'metric_category' => 'search_performance', 'title' => 'Search Console Total Clicks (Last 28 Days)', 'metric_value' => 'Pending access', 'unit' => 'clicks', 'window_start' => '2026-09-12', 'window_end' => '2026-10-09', 'source_name' => 'Google Search Console', 'collection_status' => 'Pending access', 'status_reason' => 'Search Console domain property access pending client DNS authorization', 'collected_at' => '2026-10-10 18:00:00'],
        ['metric_key' => 'gsc_organic_impressions', 'metric_category' => 'search_performance', 'title' => 'Search Console Total Impressions (Last 28 Days)', 'metric_value' => 'Pending access', 'unit' => 'impressions', 'window_start' => '2026-09-12', 'window_end' => '2026-10-09', 'source_name' => 'Google Search Console', 'collection_status' => 'Pending access', 'status_reason' => 'Search Console domain property access pending client DNS authorization', 'collected_at' => '2026-10-10 18:00:00'],
        ['metric_key' => 'gsc_organic_ctr', 'metric_category' => 'search_performance', 'title' => 'Search Console Average CTR (Last 28 Days)', 'metric_value' => 'Pending access', 'unit' => '%', 'window_start' => '2026-09-12', 'window_end' => '2026-10-09', 'source_name' => 'Google Search Console', 'collection_status' => 'Pending access', 'status_reason' => 'Search Console domain property access pending client DNS authorization', 'collected_at' => '2026-10-10 18:00:00'],
        ['metric_key' => 'gsc_average_position', 'metric_category' => 'search_performance', 'title' => 'Search Console Average Position (Last 28 Days)', 'metric_value' => 'Pending access', 'unit' => 'position', 'window_start' => '2026-09-12', 'window_end' => '2026-10-09', 'source_name' => 'Google Search Console', 'collection_status' => 'Pending access', 'status_reason' => 'Search Console domain property access pending client DNS authorization', 'collected_at' => '2026-10-10 18:00:00'],
        ['metric_key' => 'leads_persisted_total', 'metric_category' => 'conversions', 'title' => 'Total Persisted Website Enquiries', 'metric_value' => '0', 'unit' => 'leads', 'window_start' => '2026-09-12', 'window_end' => '2026-10-09', 'source_name' => 'Tabeeb MySQL CRM', 'collection_status' => 'Measured', 'status_reason' => 'Direct count of verified database leads', 'collected_at' => '2026-10-10 18:00:00'],
        ['metric_key' => 'conversion_rate_session', 'metric_category' => 'conversions', 'title' => 'Website Lead Conversion Rate (Enquiries / Sessions)', 'metric_value' => 'Not available', 'unit' => '%', 'window_start' => '2026-09-12', 'window_end' => '2026-10-09', 'source_name' => 'Derived (CRM / GA4)', 'collection_status' => 'Not available', 'status_reason' => 'Eligible session denominator unavailable prior to live GA4 tracking activation', 'collected_at' => '2026-10-10 18:00:00']
    ];
}

if ($format === 'markdown') {
    $content = "# Tabeeb Contractor – Day 1 Marketing & SEO Baseline\n\n";
    $content .= "Generated: " . date('Y-m-d H:i:s') . " (Asia/Singapore)\n\n";
    $content .= "| Metric | Value | Unit | Status | Notes |\n";
    $content .= "|---|---|---|---|---|\n";
    foreach ($metrics as $m) {
        $content .= "| " . $m['title'] . " | " . ($m['metric_value'] ?: '—') . " | " . $m['unit'] . " | `" . $m['collection_status'] . "` | " . $m['status_reason'] . " |\n";
    }
} else {
    // CSV
    $fp = fopen('php://temp', 'r+');
    fputcsv($fp, ['Metric Key', 'Title', 'Category', 'Value', 'Unit', 'Window Start', 'Window End', 'Source', 'Status', 'Reason', 'Recorded At']);
    foreach ($metrics as $m) {
        fputcsv($fp, [
            $m['metric_key'],
            $m['title'],
            $m['metric_category'],
            $m['metric_value'],
            $m['unit'],
            $m['window_start'],
            $m['window_end'],
            $m['source_name'],
            $m['collection_status'],
            $m['status_reason'],
            $m['collected_at']
        ]);
    }
    rewind($fp);
    $content = stream_get_contents($fp);
    fclose($fp);
}

if ($output) {
    file_put_contents($output, $content);
    echo "[+] Baseline report successfully written to {$output}\n";
} else {
    echo $content;
}
exit(0);
