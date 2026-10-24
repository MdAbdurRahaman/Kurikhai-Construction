<?php
/** Server-side bulk lead operations. Caller must verify login and CSRF first. */
function lead_bulk_action(array $ids, string $action, string $value, bool $trash): int {
    foreach ($ids as $id) {
        if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id < 1) throw new InvalidArgumentException('Invalid lead selection.');
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
    if (!$ids || count($ids) > 200) throw new InvalidArgumentException('Select between 1 and 200 leads.');
    if (!has_permission('leads.view') && !has_permission('leads.view_assigned')) throw new RuntimeException('Access denied.');
    $super = is_super_admin();
    if ($trash && !$super) throw new RuntimeException('Only super administrators can access the recycle bin.');
    $column = null;
    if (in_array($action, ['restore', 'purge'], true)) {
        if (!$super || !$trash) throw new RuntimeException('Only super administrators can restore or permanently delete recycled leads.');
    } elseif ($action === 'trash') {
        if ($trash || !has_permission('leads.delete')) throw new RuntimeException('You do not have permission to move leads to the recycle bin.');
    } elseif (in_array($action, ['status', 'priority'], true)) {
        if ($trash || (!has_permission('leads.edit') && !has_permission('leads.edit_assigned'))) throw new RuntimeException('You do not have permission to edit leads.');
        $allowed = $action === 'status' ? ['new','unreviewed','contacted','qualified','site_visit_scheduled','site_visit_completed','quotation_preparing','quotation_sent','negotiation','won','lost','spam','archived'] : ['low','medium','high','urgent'];
        if (!in_array($value, $allowed, true)) throw new InvalidArgumentException('Choose a valid status or priority.');
        $column = $action;
    } elseif ($action === 'assign') {
        if ($trash || !has_permission('leads.assign')) throw new RuntimeException('You do not have permission to assign leads.');
        if ($value !== '0' && (!ctype_digit($value) || !db_val("SELECT id FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [(int)$value]))) throw new InvalidArgumentException('Choose an active staff member.');
        $column = 'assigned_user_id';
    } else throw new InvalidArgumentException('Choose a bulk action.');

    return DB::transaction(function () use ($ids, $action, $value, $trash, $column) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $params = $ids;
        $scope = 'id IN (' . $marks . ') AND deleted_at IS ' . ($trash ? 'NOT NULL' : 'NULL');
        // Apply both visibility and edit scope, including users with view-all/edit-assigned.
        if (!has_permission('leads.view') || (in_array($action, ['status','priority'], true) && !has_permission('leads.edit'))) {
            $scope .= ' AND assigned_user_id = ?';
            $params[] = (int)current_user()['id'];
        }
        $rows = db_all("SELECT id, status, priority, assigned_user_id, deleted_at FROM leads WHERE $scope FOR UPDATE", $params);
        if (count($rows) !== count($ids)) throw new RuntimeException('Some selected leads are unavailable or outside your access. Refresh and try again.');
        if ($action === 'purge') db_exec("DELETE FROM leads WHERE $scope", $params);
        elseif ($action === 'trash') db_exec("UPDATE leads SET deleted_at = NOW() WHERE $scope", $params);
        elseif ($action === 'restore') db_exec("UPDATE leads SET deleted_at = NULL WHERE $scope", $params);
        else {
            $newValue = $action === 'assign' ? ($value === '0' ? null : (int)$value) : $value;
            db_exec("UPDATE leads SET `$column` = ? WHERE $scope", array_merge([$newValue], $params));
        }
        foreach ($rows as $row) audit_log('lead.bulk.' . $action, 'lead', (int)$row['id'], $row, ['value' => $value]);
        return count($rows);
    });
}
