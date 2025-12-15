<?php
// import.php
// Import labels from labels.json and messages from database.txt into MySQL.
// Usage (CLI): php import.php
// Or place in a PHP-enabled web server and open in browser.

require_once __DIR__ . '/db_config.php';

function sanitizeStr(?string $s, int $maxLen = 2048): ?string {
    if ($s === null) return null;
    $s = trim($s);
    // Remove control characters
    $s = preg_replace('/[\x00-\x1F\x7F]/', '', $s);
    if (strlen($s) > $maxLen) {
        $s = substr($s, 0, $maxLen);
    }
    return $s;
}

function importLabelsFromJson(PDO $pdo, string $labelsPath): void {
    if (!file_exists($labelsPath)) {
        echo "labels.json không tồn tại: {$labelsPath}\n";
        return;
    }
    $raw = file_get_contents($labelsPath);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        echo "labels.json không phải là mảng JSON hợp lệ.\n";
        return;
    }

    $inserted = 0;
    $pdo->beginTransaction();
    try {
        $sel = $pdo->prepare('SELECT id FROM labels WHERE name = ? LIMIT 1');
        $ins = $pdo->prepare('INSERT INTO labels (name, is_public) VALUES (?, ?)');

        foreach ($data as $item) {
            if (is_string($item)) {
                $name = sanitizeStr($item, 100);
                $is_public = 0;
            } elseif (is_array($item)) {
                $name = isset($item['name']) ? sanitizeStr($item['name'], 100) : '';
                $is_public = isset($item['is_public']) ? (int)$item['is_public'] : 0;
            } else {
                continue;
            }
            if ($name === '') continue;

            $sel->execute([$name]);
            if (!$sel->fetch()) {
                $ins->execute([$name, $is_public]);
                $inserted++;
            }
        }

        $pdo->commit();
        echo "Import labels.json hoàn tất. Thêm mới: {$inserted}.\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "Lỗi import labels: " . $e->getMessage() . "\n";
    }
}

function ensureLabelExists(PDO $pdo, string $labelName): void {
    $labelName = sanitizeStr($labelName, 100) ?? '';
    if ($labelName === '') return;
    $sel = $pdo->prepare('SELECT id FROM labels WHERE name = ? LIMIT 1');
    $sel->execute([$labelName]);
    if (!$sel->fetch()) {
        $ins = $pdo->prepare('INSERT INTO labels (name, is_public) VALUES (?, 0)');
        $ins->execute([$labelName]);
    }
}

function parseDateOrNull(?string $s): ?string {
    $s = sanitizeStr($s, 50);
    if ($s === null || $s === '') return null;
    // Accept YYYY-MM-DD only
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
    return null;
}

function importMessagesFromTxt(PDO $pdo, string $dbPath): void {
    if (!file_exists($dbPath)) {
        echo "database.txt không tồn tại: {$dbPath}\n";
        return;
    }
    $raw = file_get_contents($dbPath);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        echo "database.txt không phải là mảng JSON hợp lệ.\n";
        return;
    }

    $inserted = 0;
    $skipped = 0;

    $pdo->beginTransaction();
    try {
        $check = $pdo->prepare('SELECT id FROM messages WHERE image_link = ? LIMIT 1');
        $ins = $pdo->prepare('INSERT INTO messages (customer_name, shoot_date, image_link, note, status, label, avatar, result_link, result_content) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

        foreach ($data as $item) {
            $image_link = sanitizeStr($item['image_link'] ?? '', 1024);
            if ($image_link === '') { $skipped++; continue; }

            $check->execute([$image_link]);
            if ($check->fetch()) { $skipped++; continue; }

            $customer_name = sanitizeStr($item['customer_name'] ?? null, 255);
            $shoot_date = parseDateOrNull($item['shoot_date'] ?? null);
            $note = sanitizeStr($item['note'] ?? null, 4000);
            $status = sanitizeStr($item['status'] ?? 'new', 20) ?: 'new';
            $label = sanitizeStr($item['label'] ?? 'Mới', 100) ?: 'Mới';
            $avatar = sanitizeStr($item['avatar'] ?? null, 255);
            // Allow long URLs/content
            $result_link = sanitizeStr($item['result_link'] ?? null, 4000);
            $result_content = sanitizeStr($item['result_content'] ?? null, 4000);

            // Ensure label exists (internal if new)
            ensureLabelExists($pdo, $label);

            $ins->execute([
                $customer_name,
                $shoot_date,
                $image_link,
                $note,
                $status,
                $label,
                $avatar,
                $result_link,
                $result_content
            ]);
            $inserted++;
        }

        $pdo->commit();
        echo "Import database.txt hoàn tất. Thêm mới: {$inserted}, Bỏ qua: {$skipped}.\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "Lỗi import messages: " . $e->getMessage() . "\n";
    }
}

try {
    $pdo = getPDO();
    importLabelsFromJson($pdo, __DIR__ . '/labels.json');
    importMessagesFromTxt($pdo, __DIR__ . '/database.txt');
    echo "Xong!\n";
} catch (Throwable $e) {
    echo "Không thể kết nối DB: " . $e->getMessage() . "\n";
}