<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

$rawInput = file_get_contents("php://input");
$jsonData = json_decode($rawInput, true);

if (is_array($jsonData)) {
    $_POST = array_merge($_POST, $jsonData);
}


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["status"=>"error","message"=>"Only POST allowed"]);
    exit;
}

$project_id = intval($_POST["project_id"] ?? 0);
$stage_id   = intval($_POST["stage_id"] ?? 0);
$summary    = trim($_POST["summary"] ?? "");
$progress   = intval($_POST["progress_update"] ?? 0);

// Date handling
$work_date = $_POST["work_date"] ?? null;
if (empty($work_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $work_date)) {
    $work_date = date("Y-m-d");
}

if ($project_id <= 0 || $stage_id <= 0) {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"Invalid input"]);
    exit;
}

/*
EXPECTED MATERIAL FORMAT (POST):
materials[0][material_id]=1
materials[0][used_quantity]=5
materials[1][material_id]=2
materials[1][used_quantity]=10
*/
$materials = $_POST["materials"] ?? [];

$conn->begin_transaction();

try {

    /* 1️⃣ INSERT WORK LOG */
    $stmt = $conn->prepare("
        INSERT INTO work_logs (project_id, stage_id, work_date, summary, progress_update)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iissi", $project_id, $stage_id, $work_date, $summary, $progress);
    $stmt->execute();

    $work_log_id = $stmt->insert_id;

    file_put_contents(
    __DIR__ . "/materials_debug.log",
    print_r($materials, true)
);


    /* 2️⃣ HANDLE MATERIAL USAGE (🔥 THIS WAS MISSING 🔥) */
    foreach ($materials as $m) {

        $material_id   = intval($m["material_id"] ?? 0);
        $used_quantity = floatval($m["used_quantity"] ?? 0);

        if ($material_id <= 0 || $used_quantity <= 0) {
            throw new Exception("Invalid material data");
        }

        // 🔍 Check stock
        $stockStmt = $conn->prepare("
            SELECT current_stock FROM materials WHERE id = ? FOR UPDATE
        ");
        $stockStmt->bind_param("i", $material_id);
        $stockStmt->execute();
        $stock = $stockStmt->get_result()->fetch_assoc();

        if (!$stock) {
            throw new Exception("Material not found");
        }

        if ($stock["current_stock"] < $used_quantity) {
            throw new Exception("Insufficient stock for material ID $material_id");
        }

        // ➕ Insert into work_log_materials
        $usageStmt = $conn->prepare("
            INSERT INTO work_log_materials (work_log_id, material_id, used_quantity)
            VALUES (?, ?, ?)
        ");
        $usageStmt->bind_param("iid", $work_log_id, $material_id, $used_quantity);
        $usageStmt->execute();

        // ➖ Deduct stock
        $updateStock = $conn->prepare("
            UPDATE materials
            SET current_stock = current_stock - ?
            WHERE id = ?
        ");
        $updateStock->bind_param("di", $used_quantity, $material_id);
        $updateStock->execute();
    }

    /* 3️⃣ HANDLE IMAGE UPLOADS */
    if (!empty($_FILES['media']['tmp_name'])) {

        $uploadDir = __DIR__ . "/uploads/work_logs/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $files = $_FILES['media'];
        $count = is_array($files['tmp_name']) ? count($files['tmp_name']) : 1;

        for ($i = 0; $i < $count; $i++) {
            $tmpName  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $origName = is_array($files['name']) ? $files['name'][$i] : $files['name'];

            if (!is_uploaded_file($tmpName)) continue;

            $ext = pathinfo($origName, PATHINFO_EXTENSION) ?: "jpg";
            $filename = uniqid("wl_", true) . "." . $ext;
            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($tmpName, $targetPath)) {
                $dbPath = "uploads/work_logs/" . $filename;

                $mediaStmt = $conn->prepare("
                    INSERT INTO project_media (project_id, work_log_id, file_path, media_type)
                    VALUES (?, ?, ?, 'photo')
                ");
                $mediaStmt->bind_param("iis", $project_id, $work_log_id, $dbPath);
                $mediaStmt->execute();
            }
        }
    }

    /* 4️⃣ UPDATE STAGE PROGRESS */
    $updStage = $conn->prepare("
        UPDATE project_stages
        SET progress = LEAST(GREATEST(progress + ?, 0), 100)
        WHERE id = ? AND project_id = ?
    ");
    $updStage->bind_param("iii", $progress, $stage_id, $project_id);
    $updStage->execute();

    /* 5️⃣ RECALCULATE PROJECT PROGRESS */
    $calc = $conn->prepare("
        SELECT ROUND(SUM(weight * progress) / SUM(weight)) AS overall
        FROM project_stages
        WHERE project_id = ?
    ");
    $calc->bind_param("i", $project_id);
    $calc->execute();
    $overall = (int)($calc->get_result()->fetch_assoc()["overall"] ?? 0);

    $updProj = $conn->prepare("
        UPDATE projects SET overall_progress = ? WHERE id = ?
    ");
    $updProj->bind_param("ii", $overall, $project_id);
    $updProj->execute();

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "work_log_id" => $work_log_id,
        "overall_progress" => $overall
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
}
