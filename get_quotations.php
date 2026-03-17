<?php
include 'db.php';
header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);
$contractor_id = intval($_GET['contractor_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| CASE 1: SINGLE QUOTATION (DETAIL SCREEN)
|--------------------------------------------------------------------------
*/
if ($id > 0) {

    $stmt = $conn->prepare("
        SELECT 
            q.id,
            q.title,
            q.description,
            q.amount,
            q.status,
            q.created_at,

            p.project_name,
            p.location AS project_location,

            c.client_name,
            c.phone AS client_phone,
            c.email AS client_email

        FROM quotations q
        JOIN projects p ON p.id = q.project_id
        JOIN clients c ON c.id = p.client_id
        WHERE q.id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode([]);
    }
    exit;
}

/*
|--------------------------------------------------------------------------
| CASE 2: LIST ALL QUOTATIONS (LIST SCREEN)
|--------------------------------------------------------------------------
*/
if ($contractor_id > 0) {

    $stmt = $conn->prepare("
        SELECT 
            q.id,
            q.title,
            q.amount,
            q.status,
            q.created_at,
            p.project_name,
            c.client_name
        FROM quotations q
        JOIN projects p ON p.id = q.project_id
        JOIN clients c ON c.id = p.client_id
        WHERE q.contractor_id = ?
        ORDER BY q.created_at DESC
    ");

    $stmt->bind_param("i", $contractor_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode($data);
    exit;
}

echo json_encode([]);
