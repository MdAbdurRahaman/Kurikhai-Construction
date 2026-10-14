<?php
header('Content-Type: application/json');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
require_once dirname(__DIR__).'/includes/measurement.php';
try {
    if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);header('Allow: POST');exit;}
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>4096) {http_response_code(413);exit;}
    if (!verify_same_origin()) {http_response_code(403);exit;}
    $raw=file_get_contents('php://input',false,null,0,4097);
    if (strlen($raw)>4096) {http_response_code(413);exit;}
    $p=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
    if (!is_array($p) || !verify_csrf_token($p['csrf_token']??null) || ($p['analytics_consent']??null)!==true) {http_response_code(403);exit;}
    if (APP_ENV!=='production' && APP_ENV!=='testing') {http_response_code(403);exit;}
    if (!empty($_SESSION['tabeeb_admin_user']) || analytics_setting('first_party_analytics','enabled')!=='enabled') {http_response_code(204);exit;}
    if (!check_rate_limit('analytics_ingestion',180,60)['allowed']) {http_response_code(429);exit;}
    $event=normalize_analytics_event($p);
    // Raw browser measurements are retained for 90 days; purge incrementally with a bounded indexed delete.
    if (random_int(1,100)===1) purge_site_measurements();
    store_analytics_event($event);http_response_code(204);
} catch (InvalidArgumentException|JsonException $e) {http_response_code(422);echo json_encode(['error'=>'Invalid measurement payload.']);}
catch (Throwable $e) {http_response_code(503);echo json_encode(['error'=>'Measurement temporarily unavailable.']);}
