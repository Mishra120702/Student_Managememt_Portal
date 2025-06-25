<?php
session_start();
include 'config/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $academic_year = trim(filter_input(INPUT_POST, 'academic_year', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $course_program = trim(filter_input(INPUT_POST, 'course_program', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $start_date = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $end_date = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $batch_code = trim(filter_input(INPUT_POST, 'batch_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $classroom_location = trim(filter_input(INPUT_POST, 'classroom_location', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $timing_schedule = trim(filter_input(INPUT_POST, 'timing_schedule', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $remarks = trim(filter_input(INPUT_POST, 'remarks', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

    // Validate required fields
    if (empty($name)) {
        throw new Exception("Batch name is required.");
    }
    if (empty($start_date)) {
        throw new Exception("Start date is required.");
    }
    if (empty($end_date)) {
        throw new Exception("End date is required.");
    }

    $sql = "INSERT INTO batches (name, academic_year, course_program, start_date, end_date, batch_code, status, classroom_location, timing_schedule, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $name, $academic_year, $course_program, $start_date, $end_date,
        $batch_code, $status, $classroom_location, $timing_schedule, $remarks
    ]);

    echo json_encode(["success" => true, "message" => "Batch '{$name}' created successfully!"]);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in create_batch.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "A database error occurred. Please try again later."]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
header("location: index.php#batches");