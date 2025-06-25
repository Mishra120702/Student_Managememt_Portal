<?php
// api/attendance_api.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_GET['action'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "No action specified"]);
    exit();
}

$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$recorded_by = $_SESSION['user_id'] ?? 1; // Default to 1 if not set

try {
    switch ($action) {
        case 'fetch_batches':
            echo json_encode(fetchBatches($conn));
            break;
        case 'fetch_students_and_attendance':
            echo json_encode(fetchStudentsAndAttendance($conn));
            break;
        case 'save_attendance':
            echo json_encode(saveAttendance($conn, $recorded_by));
            break;
        case 'export_attendance_csv':
            exportAttendanceCSV($conn);
            break;
        default:
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid action"]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error",
        "error" => $e->getMessage()
    ]);
}

function fetchBatches(PDO $conn) {
    $sql = "SELECT id, name FROM batches WHERE status = 'active' ORDER BY name ASC";
    $stmt = $conn->query($sql);
    return ["success" => true, "batches" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function fetchStudentsAndAttendance(PDO $conn) {
    $batch_id = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
    $date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (!$batch_id || !$date) {
        http_response_code(400);
        return ["success" => false, "message" => "Batch ID and Date are required"];
    }

    $students_map = [];
    
    // Fetch students
    $sql_students = "SELECT u.id, u.name
                     FROM users u
                     JOIN student_details sd ON u.id = sd.user_id
                     WHERE u.role = 'student' AND sd.batch_id = ?
                     ORDER BY u.name ASC";
    $stmt_students = $conn->prepare($sql_students);
    $stmt_students->execute([$batch_id]);
    
    foreach ($stmt_students->fetchAll(PDO::FETCH_ASSOC) as $student_row) {
        $students_map[$student_row['id']] = [
            'id' => $student_row['id'],
            'name' => $student_row['name'],
            'isPresent' => false,
            'cameraOn' => false,
            'notes' => ''
        ];
    }

    // Fetch attendance if students exist
    if (!empty($students_map)) {
        $placeholders = implode(',', array_fill(0, count($students_map), '?'));
        $sql_attendance = "SELECT student_id, status, camera_on, notes
                           FROM attendance
                           WHERE batch_id = ? AND date = ? AND student_id IN ($placeholders)";
        $stmt_attendance = $conn->prepare($sql_attendance);
        $params = array_merge([$batch_id, $date], array_keys($students_map));
        $stmt_attendance->execute($params);
        
        foreach ($stmt_attendance->fetchAll(PDO::FETCH_ASSOC) as $att_row) {
            if (isset($students_map[$att_row['student_id']])) {
                $students_map[$att_row['student_id']]['isPresent'] = ($att_row['status'] === 'present');
                $students_map[$att_row['student_id']]['cameraOn'] = (bool)$att_row['camera_on'];
                $students_map[$att_row['student_id']]['notes'] = $att_row['notes'] ?? '';
            }
        }
    }

    return ["success" => true, "students" => array_values($students_map)];
}

function saveAttendance(PDO $conn, int $recorded_by) {
    $json_data = file_get_contents('php://input');
    $attendance_data = json_decode($json_data, true);

    if (!is_array($attendance_data)) {
        http_response_code(400);
        return ["success" => false, "message" => "Invalid data format"];
    }

    $conn->beginTransaction();
    
    try {
        foreach ($attendance_data as $record) {
            $student_id = filter_var($record['studentId'] ?? null, FILTER_VALIDATE_INT);
            $batch_id = filter_var($record['classId'] ?? null, FILTER_VALIDATE_INT);
            $date = filter_var($record['date'] ?? null, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $is_present = (isset($record['isPresent']) && $record['isPresent']) ? 1 : 0;
            $camera_on = (isset($record['cameraOn']) && $record['cameraOn']) ? 1 : 0;
            $notes = filter_var($record['notes'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if (!$student_id || !$batch_id || !$date) {
                throw new Exception("Missing required fields");
            }

            $status_enum = $is_present ? 'present' : 'absent';

            // Check if record exists
            $stmt_check = $conn->prepare("SELECT id FROM attendance WHERE student_id = ? AND batch_id = ? AND date = ?");
            $stmt_check->execute([$student_id, $batch_id, $date]);
            
            if ($attendance_id = $stmt_check->fetchColumn()) {
                // Update existing
                $stmt_update = $conn->prepare("UPDATE attendance SET status = ?, camera_on = ?, notes = ? WHERE id = ?");
                $stmt_update->execute([$status_enum, $camera_on, $notes, $attendance_id]);
            } else {
                // Insert new
                $stmt_insert = $conn->prepare("INSERT INTO attendance (student_id, batch_id, date, status, camera_on, recorded_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_insert->execute([$student_id, $batch_id, $date, $status_enum, $camera_on, $recorded_by, $notes]);
            }
        }

        $conn->commit();
        return ["success" => true, "message" => "Attendance saved"];
    } catch (Exception $e) {
        $conn->rollBack();
        http_response_code(500);
        return ["success" => false, "message" => $e->getMessage()];
    }
}

function exportAttendanceCSV(PDO $conn) {
    $batch_id = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
    $date_param = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $sql = "SELECT u.name AS student_name, a.student_id, b.name AS batch_name, a.date, a.status, a.camera_on, a.notes
            FROM attendance a
            JOIN users u ON a.student_id = u.id
            JOIN batches b ON a.batch_id = b.id
            WHERE 1=1";
    
    $params = [];
    if ($batch_id !== null) {
        $sql .= " AND a.batch_id = ?";
        $params[] = $batch_id;
    }
    if ($date_param !== null) {
        $sql .= " AND a.date = ?";
        $params[] = $date_param;
    }
    $sql .= " ORDER BY a.date DESC, u.name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_export.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student Name', 'Student ID', 'Batch Name', 'Date', 'Status', 'Camera On', 'Notes']);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['camera_on'] = ($row['camera_on'] == 1) ? 'On' : 'Off';
        fputcsv($output, $row);
    }

    fclose($output);
    exit();
}