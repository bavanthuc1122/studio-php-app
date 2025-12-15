<?php
// Unified API router for Studio app (PHP)
// Usage: api.php?action=submit|check|update_client|login|admin_data|admin_update_ticket|admin_delete_ticket|admin_manage_label|admin_update_config

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_config.php';

function jsonResponse($success, $message = '', $data = null, $code = 200) {
    $resp = ['success' => $success];
    if ($message !== '') { $resp['message'] = $message; }
    if ($data !== null) { $resp['data'] = $data; }
    http_response_code($code);
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    exit;
}

function readJsonBody() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function requireMethod($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        jsonResponse(false, 'Phương thức không được hỗ trợ', null, 405);
    }
}

function isLabelPublic(PDO $pdo, $labelName) {
    $stmt = $pdo->prepare('SELECT is_public FROM labels WHERE name = ? LIMIT 1');
    $stmt->execute([$labelName]);
    $row = $stmt->fetch();
    if (!$row) return false; // label không tồn tại coi như nội bộ
    return (int)$row['is_public'] === 1;
}

function getAllLabelNames(PDO $pdo) {
    $labels = [];
    $stmt = $pdo->query('SELECT name FROM labels ORDER BY id ASC');
    foreach ($stmt as $row) { $labels[] = $row['name']; }
    return $labels;
}

function sanitizeStr($v, $max = 1024) {
    $v = is_string($v) ? trim($v) : '';
    if ($v === '') return '';
    if (mb_strlen($v) > $max) { $v = mb_substr($v, 0, $max); }
    return $v;
}

// --- Handlers ---
function handleSubmit(PDO $pdo) {
    requireMethod('POST');
    $b = readJsonBody();
    $customer_name = sanitizeStr($b['customer_name'] ?? '', 255);
    $shoot_date = sanitizeStr($b['shoot_date'] ?? '', 10); // YYYY-MM-DD
    $image_link = sanitizeStr($b['image_link'] ?? '', 1024);
    $note = sanitizeStr($b['note'] ?? '', 5000);

    if ($image_link === '') {
        jsonResponse(false, 'Link ảnh không được để trống!', null, 400);
    }

    $stmt = $pdo->prepare('SELECT id FROM messages WHERE image_link = ? LIMIT 1');
    $stmt->execute([$image_link]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'Link ảnh đã tồn tại!', null, 400);
    }

    $avatarId = random_int(1, 5);
    $avatarPath = '/static/avatars/' . $avatarId . '.png';

    $sql = 'INSERT INTO messages (customer_name, shoot_date, image_link, note, status, label, avatar, result_link, result_content) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $pdo->prepare($sql);
    $sd = $shoot_date !== '' ? $shoot_date : null; // DATE hoặc NULL
    try {
        $stmt->execute([$customer_name, $sd, $image_link, $note, 'new', 'Mới', $avatarPath, null, null]);
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') { jsonResponse(false, 'Link ảnh đã tồn tại!', null, 400); }
        jsonResponse(false, 'Lỗi hệ thống khi lưu dữ liệu', null, 500);
    }

    jsonResponse(true, 'Đã gửi thông tin!');
}

function handleCheck(PDO $pdo) {
    requireMethod('POST');
    $b = readJsonBody();
    $image_link = sanitizeStr($b['image_link'] ?? '', 1024);
    if ($image_link === '') { jsonResponse(false, 'Vui lòng cung cấp link ảnh', null, 400); }

    $stmt = $pdo->prepare('SELECT id, customer_name, shoot_date, image_link, note, status, label, avatar, result_link, result_content, created_at FROM messages WHERE image_link = ? LIMIT 1');
    $stmt->execute([$image_link]);
    $row = $stmt->fetch();
    if (!$row) { jsonResponse(false, 'Không tìm thấy thông tin.', null, 404); }

    $display = $row;
    if (!isLabelPublic($pdo, $row['label'])) { $display['label'] = 'Đang xử lý'; }

    jsonResponse(true, '', $display);
}

function handleUpdateClient(PDO $pdo) {
    requireMethod('POST');
    $b = readJsonBody();
    $image_link = sanitizeStr($b['image_link'] ?? '', 1024);
    $customer_name = sanitizeStr($b['customer_name'] ?? '', 255);
    $note = sanitizeStr($b['note'] ?? '', 5000);

    if ($image_link === '') { jsonResponse(false, 'Thiếu link ảnh', null, 400); }

    $stmt = $pdo->prepare('UPDATE messages SET customer_name = ?, note = ? WHERE image_link = ?');
    $stmt->execute([$customer_name, $note, $image_link]);
    if ($stmt->rowCount() > 0) { jsonResponse(true, 'Đã cập nhật thông tin!'); }
    jsonResponse(false, 'Không tìm thấy dữ liệu để cập nhật', null, 404);
}

function handleLogin() {
    requireMethod('POST');
    $b = readJsonBody();
    $u = $b['username'] ?? '';
    $p = $b['password'] ?? '';
    if ($u === 'admin' && $p === 'studio123') { jsonResponse(true); }
    jsonResponse(false, '', null, 401);
}

function handleAdminData(PDO $pdo) {
    requireMethod('GET');
    $stmt = $pdo->query('SELECT id, customer_name, shoot_date, image_link, note, status, label, avatar, result_link, result_content, created_at FROM messages ORDER BY created_at ASC');
    $messages = $stmt->fetchAll();
    $labels = getAllLabelNames($pdo);
    // Trả về cả data và field cũ để tương thích
    $payload = ['messages' => $messages, 'labels' => $labels];
    $out = ['success' => true, 'data' => $payload, 'messages' => $messages, 'labels' => $labels];
    http_response_code(200);
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

function handleAdminUpdateTicket(PDO $pdo) {
    requireMethod('POST');
    $b = readJsonBody();
    $image_link = sanitizeStr($b['image_link'] ?? '', 1024);
    if ($image_link === '') { jsonResponse(false, 'Thiếu link ảnh', null, 400); }
    $customer_name = sanitizeStr($b['customer_name'] ?? '', 255);
    $note = sanitizeStr($b['note'] ?? '', 5000);
    $label = sanitizeStr($b['label'] ?? '', 100);
    $result_link = sanitizeStr($b['result_link'] ?? '', 1024);
    $result_content = sanitizeStr($b['result_content'] ?? '', 5000);

    // Validate label must exist in labels table
    if ($label === '') { jsonResponse(false, 'Thiếu label', null, 400); }
    $chk = $pdo->prepare('SELECT 1 FROM labels WHERE name = ? LIMIT 1');
    $chk->execute([$label]);
    if (!$chk->fetch()) { jsonResponse(false, 'Label không hợp lệ', null, 400); }

    $stmt = $pdo->prepare('UPDATE messages SET customer_name = ?, note = ?, label = ?, result_link = ?, result_content = ? WHERE image_link = ?');
    $stmt->execute([$customer_name, $note, $label, $result_link, $result_content, $image_link]);
    if ($stmt->rowCount() > 0) { jsonResponse(true); }
    jsonResponse(false, '', null, 404);
}

function handleAdminDeleteTicket(PDO $pdo) {
    requireMethod('POST');
    $b = readJsonBody();
    $image_link = sanitizeStr($b['image_link'] ?? '', 1024);
    if ($image_link === '') { jsonResponse(false, 'Thiếu link ảnh', null, 400); }
    $stmt = $pdo->prepare('DELETE FROM messages WHERE image_link = ?');
    $stmt->execute([$image_link]);
    if ($stmt->rowCount() > 0) { jsonResponse(true, 'Đã xóa thành công!'); }
    jsonResponse(false, 'Không tìm thấy dữ liệu để xóa', null, 404);
}

function handleAdminManageLabel(PDO $pdo) {
    requireMethod('POST');
    $b = readJsonBody();
    $action = $b['action'] ?? '';
    $label_name = sanitizeStr($b['label'] ?? '', 100);
    if ($label_name === '') { jsonResponse(false, 'Thiếu tên label', null, 400); }

    if ($action === 'add') {
        $stmt = $pdo->prepare('SELECT id FROM labels WHERE name = ? LIMIT 1');
        $stmt->execute([$label_name]);
        if (!$stmt->fetch()) {
            $ins = $pdo->prepare('INSERT INTO labels (name, is_public) VALUES (?, 0)');
            $ins->execute([$label_name]);
        }
        $labels = getAllLabelNames($pdo);
        jsonResponse(true, '', ['labels' => $labels]);
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare('SELECT id, is_public FROM labels WHERE name = ? LIMIT 1');
        $stmt->execute([$label_name]);
        $row = $stmt->fetch();
        if (!$row) { jsonResponse(false, 'Label không tồn tại'); }
        if ((int)$row['is_public'] === 1) { jsonResponse(false, 'Không thể xóa Label mặc định!'); }
        $del = $pdo->prepare('DELETE FROM labels WHERE id = ?');
        $del->execute([$row['id']]);
        $labels = getAllLabelNames($pdo);
        jsonResponse(true, '', ['labels' => $labels]);
    } else {
        jsonResponse(false, 'Hành động không hợp lệ', null, 400);
    }
}

function handleAdminUpdateConfig(PDO $pdo) {
    requireMethod('POST');
    $b = readJsonBody();
    $bg = sanitizeStr($b['bg_image'] ?? '', 4000);
    $txt = sanitizeStr($b['text_color'] ?? '', 20);

    $stmt = $pdo->query('SELECT id, bg_image, text_color FROM config ORDER BY id DESC LIMIT 1');
    $row = $stmt->fetch();
    if ($row) {
        $bg_to_set = $bg !== '' ? $bg : $row['bg_image'];
        $txt_to_set = $txt !== '' ? $txt : $row['text_color'];
        $upd = $pdo->prepare('UPDATE config SET bg_image = ?, text_color = ? WHERE id = ?');
        $upd->execute([$bg_to_set, $txt_to_set, $row['id']]);
    } else {
        $bg_to_set = $bg !== '' ? $bg : 'https://images.unsplash.com/photo-1557683316-973673baf926?q=80&w=2029';
        $txt_to_set = $txt !== '' ? $txt : '#000000';
        $ins = $pdo->prepare('INSERT INTO config (bg_image, text_color) VALUES (?, ?)');
        $ins->execute([$bg_to_set, $txt_to_set]);
    }
    jsonResponse(true, 'Đã cập nhật giao diện!');
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
try { $pdo = getPDO(); } catch (Throwable $e) {
    jsonResponse(false, 'Không thể kết nối Database', null, 500);
}

switch ($action) {
    case 'submit': handleSubmit($pdo); break;
    case 'check': handleCheck($pdo); break;
    case 'update_client': handleUpdateClient($pdo); break;
    case 'login': handleLogin(); break;
    case 'admin_data': handleAdminData($pdo); break;
    case 'admin_update_ticket': handleAdminUpdateTicket($pdo); break;
    case 'admin_delete_ticket': handleAdminDeleteTicket($pdo); break;
    case 'admin_manage_label': handleAdminManageLabel($pdo); break;
    case 'admin_update_config': handleAdminUpdateConfig($pdo); break;
    default: jsonResponse(false, 'Endpoint không tồn tại', null, 404); break;
}