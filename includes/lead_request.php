<?php
// One normalization contract shared by normal and race replays; attribution URLs are not identity fields.
function lead_request_fingerprint(array $fields): string {
    $normalized = [];
    foreach (['name','phone','email','service_requested','property_type','estimated_budget','project_description','preferred_contact_method','consent_status','source'] as $key) {
        $normalized[$key] = trim((string)($fields[$key] ?? ''));
    }
    $normalized['email'] = strtolower($normalized['email']);
    return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}
function lead_replay_response(array $existing, string $incomingHash, string $serviceSlug): never {
    $storedHash = $existing['payload_hash'] ?: lead_request_fingerprint($existing);
    if (!hash_equals($storedHash, $incomingHash)) {
        http_response_code(409); echo json_encode(['success'=>false,'code'=>'IDEMPOTENCY_CONFLICT','message'=>'This submission identifier belongs to different enquiry details. Submit again as a new request.']); exit;
    }
    http_response_code(200); echo json_encode(['success'=>true,'lead_id'=>$existing['lead_number'],'service_id'=>$serviceSlug,'is_duplicate'=>true,'message'=>'Your enquiry has already been received.']); exit;
}
