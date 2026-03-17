<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

$token = '';

if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

$token = trim($token);

if ($token === '') {
    http_response_code(401);
    echo json_encode(["error" => "Authorization missing"]);
    exit;
}

/* AUTH */
$stmt = $conn->prepare("SELECT id FROM users WHERE api_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$userRes = $stmt->get_result();

if ($userRes->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

$user = $userRes->fetch_assoc();
$contractor_id = $user['id'];

/* INPUT */
$material_id = intval($_POST['material_id'] ?? 0);
$used_qty    = floatval($_POST['used_quantity'] ?? 0);
$project_id  = intval($_POST['project_id'] ?? 0);
$specifications = trim($_POST['specifications'] ?? '');

if ($material_id <= 0 || $used_qty <= 0 || $project_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid input"]);
    exit;
}

$conn->begin_transaction();

try {

    /* LOCK MATERIAL */
    $stmt = $conn->prepare("
        SELECT current_stock 
        FROM materials 
        WHERE id = ? AND contractor_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("ii", $material_id, $contractor_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        throw new Exception("Material not found");
    }

    $stock = $res->fetch_assoc()['current_stock'];

    if ($stock < $used_qty) {
        throw new Exception("Insufficient stock");
    }

    /* DEDUCT STOCK */
    $stmt = $conn->prepare("
        UPDATE materials 
        SET current_stock = current_stock - ?
        WHERE id = ?
    ");
    $stmt->bind_param("di", $used_qty, $material_id);
    $stmt->execute();

    /* 3. FIND OR CREATE WORK LOG */
    // We need a work_log_id to insert into work_log_materials.
    // Strategy: Look for a work log for this project today.
    // If none, create one for the current active stage.

    $today = date('Y-m-d');
    $work_log_id = 0;

    // Check for existing work log today
    $wlStmt = $conn->prepare("SELECT id FROM work_logs WHERE project_id = ? AND work_date = ? LIMIT 1");
    $wlStmt->bind_param("is", $project_id, $today);
    $wlStmt->execute();
    $wlRes = $wlStmt->get_result();

    if ($wlRes->num_rows > 0) {
        $work_log_id = $wlRes->fetch_assoc()['id'];
    } else {
        // Create new work log. Need a stage_id.
        // Get the first incomplete stage or the last stage.
        $stageStmt = $conn->prepare("
            SELECT id FROM project_stages 
            WHERE project_id = ? 
            ORDER BY progress < 100 DESC, id ASC 
            LIMIT 1
        ");
        $stageStmt->bind_param("i", $project_id);
        $stageStmt->execute();
        $stageRes = $stageStmt->get_result();
        
        if ($stageRes->num_rows === 0) {
            // Fallback: If no stages exist (rare), we can't create a valid work log easily without schema violation usually.
            // But let's assume at least one stage exists as per project creation logic.
            throw new Exception("No project stages found to link usage log.");
        }
        
        $stage_id = $stageRes->fetch_assoc()['id'];

        $newWl = $conn->prepare("
            INSERT INTO work_logs (project_id, stage_id, work_date, summary, progress_update)
            VALUES (?, ?, ?, 'Auto-generated for Material Usage', 0)
        ");
        $newWl->bind_param("iis", $project_id, $stage_id, $today);
        $newWl->execute();
        $work_log_id = $newWl->insert_id;
    }

    /* 4. LOG USAGE (WORK-LOG-LEVEL) */
    $stmt = $conn->prepare("
        INSERT INTO work_log_materials 
        (work_log_id, material_id, used_quantity, specifications)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iids", $work_log_id, $material_id, $used_qty, $specifications);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Stock updated"
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
