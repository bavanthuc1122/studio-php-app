<?php
// Unified API router for Studio app (PHP)
// Usage: api.php?action=submit|check|update_client|login|admin_data|admin_update_ticket|admin_delete_ticket|admin_manage_label|admin_update_config

header('Content-Type: application/json; charset=utf-8');

// Simple error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// --- SIMPLIFIED DATABASE CONNECTION ---
function getPDO() {
    static $pdo = null;
    if ($pdo === null) {
        // Try to get from MYSQL_URL environment variable
        $mysqlUrl = getenv('MYSQL_URL');
        if ($mysqlUrl) {
            $parsed = parse_url($mysqlUrl);
            if ($parsed) {
                $host = $parsed['host'] ?? 'localhost';
                $port = $parsed['port'] ?? '3306';
                $user = $parsed['user'] ?? 'root';
                $pass = $parsed['pass'] ?? '';
                $name = substr($parsed['path'], 1) ?? 'railway';
                
                $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                return $pdo;
            }
        }
        
        // Fallback to individual environment variables
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $name = getenv('DB_NAME') ?: 'studio_db';
        
        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// --- Handlers ---
function handleClientSubmit() {
    requireMethod('POST');
    try {
        $pdo = getPDO();
    } catch (Exception $e) {
        jsonResponse(false, 'Không thể kết nối database: ' . $e->getMessage(), null, 500);
    }
    
    $b = readJsonBody();
    $customer_name = trim($b['customer_name'] ?? '');
    $shoot_date = trim($b['shoot_date'] ?? '');
    $image_link = trim($b['image_link'] ?? '');
    $note = trim($b['note'] ?? '');

    if (empty($image_link)) {
        jsonResponse(false, 'Link ảnh không được để trống!', null, 400);
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM messages WHERE image_link = ? LIMIT 1');
        $stmt->execute([$image_link]);
        if ($stmt->fetch()) {
            jsonResponse(false, 'Link ảnh đã tồn tại!', null, 400);
        }

        $avatarId = random_int(1, 5);
        $avatarPath = '/static/avatars/' . $avatarId . '.png';

        $sql = 'INSERT INTO messages (customer_name, shoot_date, image_link, note, status, label, avatar, result_link, result_content) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $pdo->prepare($sql);
        $sd = !empty($shoot_date) ? $shoot_date : null;
        $stmt->execute([$customer_name, $sd, $image_link, $note, 'new', 'Mới', $avatarPath, null, null]);

        jsonResponse(true, 'Đã gửi thông tin!');
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') { 
            jsonResponse(false, 'Link ảnh đã tồn tại!', null, 400); 
        }
        jsonResponse(false, 'Lỗi hệ thống khi lưu dữ liệu: ' . $ex->getMessage(), null, 500);
    }
}

function handleCheck() {
    requireMethod('POST');
    try {
        $pdo = getPDO();
    } catch (Exception $e) {
        jsonResponse(false, 'Không thể kết nối database: ' . $e->getMessage(), null, 500);
    }
    
    $b = readJsonBody();
    $image_link = trim($b['image_link'] ?? '');

    if (empty($image_link)) { 
        jsonResponse(false, 'Vui lòng cung cấp link ảnh', null, 400); 
    }

    try {
        $stmt = $pdo->prepare('SELECT id, customer_name, shoot_date, image_link, note, status, label, avatar, result_link, result_content, created_at FROM messages WHERE image_link = ? LIMIT 1');
        $stmt->execute([$image_link]);
        $row = $stmt->fetch();
        if (!$row) { 
            jsonResponse(false, 'Không tìm thấy thông tin.', null, 404); 
        }

        jsonResponse(true, '', $row);
    } catch (Exception $e) {
        jsonResponse(false, 'Lỗi khi truy vấn dữ liệu: ' . $e->getMessage(), null, 500);
    }
}

function handleUpdateClient() {
    requireMethod('POST');
    try {
        $pdo = getPDO();
    } catch (Exception $e) {
        jsonResponse(false, 'Không thể kết nối database: ' . $e->getMessage(), null, 500);
    }
    
    $b = readJsonBody();
    $image_link = trim($b['image_link'] ?? '');
    $customer_name = trim($b['customer_name'] ?? '');
    $note = trim($b['note'] ?? '');

    if (empty($image_link)) { 
        jsonResponse(false, 'Thiếu link ảnh', null, 400); 
    }

    try {
        $stmt = $pdo->prepare('UPDATE messages SET customer_name = ?, note = ? WHERE image_link = ?');
        $stmt->execute([$customer_name, $note, $image_link]);
        if ($stmt->rowCount() > 0) { 
            jsonResponse(true, 'Đã cập nhật thông tin!'); 
        }
        jsonResponse(false, 'Không tìm thấy dữ liệu để cập nhật', null, 404);
    } catch (Exception $e) {
        jsonResponse(false, 'Lỗi khi cập nhật dữ liệu: ' . $e->getMessage(), null, 500);
    }
}

function handleLogin() {
    requireMethod('POST');
    $body = readJsonBody();
    
    $u = isset($body['username']) ? trim($body['username']) : '';
    $p = isset($body['password']) ? trim($body['password']) : '';
    
    if ($u === 'admin' && $p === 'studio123') {
        jsonResponse(true, 'Đăng nhập thành công!');
    } else {
        jsonResponse(false, 'Sai tài khoản hoặc mật khẩu!', null, 401);
    }
}

function handleAdminData() {
    requireMethod('GET');
    try {
        $pdo = getPDO();
    } catch (Exception $e) {
        jsonResponse(false, 'Không thể kết nối database: ' . $e->getMessage(), null, 500);
    }
    
    try {
        $stmt = $pdo->query('SELECT id, customer_name, shoot_date, image_link, note, status, label, avatar, result_link, result_content, created_at FROM messages ORDER BY created_at ASC');
        $messages = $stmt->fetchAll();
        
        $labels = [];
        $labelStmt = $pdo->query('SELECT name FROM labels ORDER BY id ASC');
        foreach ($labelStmt as $row) { 
            $labels[] = $row['name']; 
        }
        
        $payload = ['messages' => $messages, 'labels' => $labels];
        $out = ['success' => true, 'data' => $payload, 'messages' => $messages, 'labels' => $labels];
        http_response_code(200);
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        jsonResponse(false, 'Lỗi khi lấy dữ liệu: ' . $e->getMessage(), null, 500);
    }
}

function handleAdminUpdateTicket() {
    requireMethod('POST');
    try {
        $pdo = getPDO();
    } catch (Exception $e) {
        jsonResponse(false, 'Không thể kết nối database: ' . $e->getMessage(), null, 500);
    }
    
    $b = readJsonBody();
    $image_link = trim($b['image_link'] ?? '');
    $customer_name = trim($b['customer_name'] ?? '');
    $note = trim($b['note'] ?? '');
    $label = trim($b['label'] ?? '');
    $result_link = trim($b['result_link'] ?? '');
    $result_content = trim($b['result_content'] ?? '');

    if (empty($image_link)) { 
        jsonResponse(false, 'Thiếu link ảnh', null, 400); 
    }
    if (empty($label)) { 
        jsonResponse(false, 'Thiếu label', null, 400); 
    }

    try {
        // Validate label
        $chk = $pdo->prepare('SELECT 1 FROM labels WHERE name = ? LIMIT 1');
        $chk->execute([$label]);
        if (!$chk->fetch()) { 
            jsonResponse(false, 'Label không hợp lệ', null, 400); 
        }

        $stmt = $pdo->prepare('UPDATE messages SET customer_name = ?, note = ?, label = ?, result_link = ?, result_content = ? WHERE image_link = ?');
        $stmt->execute([$customer_name, $note, $label, $result_link, $result_content, $image_link]);
        if ($stmt->rowCount() > 0) { 
            jsonResponse(true); 
        }
        jsonResponse(false, '', null, 404);
    } catch (Exception $e) {
        jsonResponse(false, 'Lỗi khi cập nhật ticket: ' . $e->getMessage(), null, 500);
    }
}

function handleAdminDeleteTicket() {
    requireMethod('POST');
    try {
        $pdo = getPDO();
    } catch (Exception $e) {
        jsonResponse(false, 'Không thể kết nối database: ' . $e->getMessage(), null, 500);
    }
    
    $b = readJsonBody();
    $image_link = trim($b['image_link'] ?? '');

    if (empty($image_link)) { 
        jsonResponse(false, 'Thiếu link ảnh', null, 400); 
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM messages WHERE image_link = ?');
        $stmt->execute([$image_link]);
        if ($stmt->rowCount() > 0) { 
            jsonResponse(true, 'Đã xóa thành công!'); 
        }
        jsonResponse(false, 'Không tìm thấy dữ liệu để xóa', null, 404);
    } catch (Exception $e) {
        jsonResponse(false, 'Lỗi khi xóa ticket: ' . $e->getMessage(), null, 500);
    }
}

function handleAdminManageLabel() {
    requireMethod('POST');
    try {
        $pdo = getPDO();
    } catch (Exception $e) {
        jsonResponse(false, 'Không thể kết nối database: ' . $e->getMessage(), null, 500);
    }
    
    $b = readJsonBody();
    $action = $b['action'] ?? '';
    $label_name = trim($b['label'] ?? '');

    if (empty($label_name)) { 
        jsonResponse(false, 'Thiếu tên label', null, 400); 
    }

    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare('SELECT id FROM labels WHERE name = ? LIMIT 1');
            $stmt->execute([$label_name]);
            if (!$stmt->fetch()) {
                $ins = $pdo->prepare('INSERT INTO labels (name, is_public) VALUES (?, 0)');
                $ins->execute([$label_name]);
            }
            
            $labels = [];
            $labelStmt = $pdo->query('SELECT name FROM labels ORDER BY id ASC');
            foreach ($labelStmt as $row) { 
                $labels[] = $row['name']; 
            }
            jsonResponse(true, '', ['labels' => $labels]);
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare('SELECT id, is_public FROM labels WHERE name = ? LIMIT 1');
            $stmt->execute([$label_name]);
            $row = $stmt->fetch();
            if (!$row) { 
                jsonResponse(false, 'Label không tồn tại'); 
            }
            if ((int)$row['is_public'] === 1) { 
                jsonResponse(false, 'Không thể xóa Label mặc định!'); 
            }
            
            $del = $pdo->prepare('DELETE FROM labels WHERE id = ?');
            $del->execute([$row['id']]);
            
            $labels = [];
            $labelStmt = $pdo->query('SELECT name FROM labels ORDER BY id ASC');
            foreach ($labelStmt as $row) { 
                $labels[] = $row['name']; 
            }
            jsonResponse(true, '', ['labels' => $labels]);
        } else {
            jsonResponse(false, 'Hành động không hợp lệ', null, 400);
        }
    } catch (Exception $e) {
        jsonResponse(false, 'Lỗi khi quản lý label: ' . $e->getMessage(), null, 500);
    }
}

function handleAdminUpdateConfig() {
    requireMethod('POST');
    try {
        $pdo = getPDO();
    } catch (Exception $e) {
        jsonResponse(false, 'Không thể kết nối database: ' . $e->getMessage(), null, 500);
    }
    
    $b = readJsonBody();
    $bg = trim($b['bg_image'] ?? '');
    $txt = trim($b['text_color'] ?? '');

    try {
        $stmt = $pdo->query('SELECT id, bg_image, text_color FROM config ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch();
        if ($row) {
            $bg_to_set = !empty($bg) ? $bg : $row['bg_image'];
            $txt_to_set = !empty($txt) ? $txt : $row['text_color'];
            $upd = $pdo->prepare('UPDATE config SET bg_image = ?, text_color = ? WHERE id = ?');
            $upd->execute([$bg_to_set, $txt_to_set, $row['id']]);
        } else {
            $bg_to_set = !empty($bg) ? $bg : 'https://images.unsplash.com/photo-1557683316-973673baf926?q=80&w=2029';
            $txt_to_set = !empty($txt) ? $txt : '#000000';
            $ins = $pdo->prepare('INSERT INTO config (bg_image, text_color) VALUES (?, ?)');
            $ins->execute([$bg_to_set, $txt_to_set]);
        }
        jsonResponse(true, 'Đã cập nhật giao diện!');
    } catch (Exception $e) {
        jsonResponse(false, 'Lỗi khi cập nhật cấu hình: ' . $e->getMessage(), null, 500);
    }
}

// --- MAIN REQUEST HANDLING ---
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle login separately (doesn't need database)
if ($action === 'login') {
    handleLogin();
    exit;
}

// Handle invalid actions
if ($action === '') {
    jsonResponse(false, 'Endpoint không tồn tại', null, 404);
}

// Route to appropriate handler
switch ($action) {
    case 'submit': handleClientSubmit(); break;
    case 'check': handleCheck(); break;
    case 'update_client': handleUpdateClient(); break;
    case 'admin_data': handleAdminData(); break;
    case 'admin_update_ticket': handleAdminUpdateTicket(); break;
    case 'admin_delete_ticket': handleAdminDeleteTicket(); break;
    case 'admin_manage_label': handleAdminManageLabel(); break;
    case 'admin_update_config': handleAdminUpdateConfig(); break;
    default: jsonResponse(false, 'Endpoint không tồn tại', null, 404); break;
}