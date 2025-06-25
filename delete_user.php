<?php
session_start();
include 'config/db.php';

// Set header for JSON response
header('Content-Type: application/json');

// Initialize a response array
$response = ['success' => false, 'message' => 'Invalid request method'];

// Check if the request method is DELETE
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Get user_id from raw input (for direct AJAX DELETE) or GET parameter (for compatibility)
    // Preference is to use raw input for DELETE requests body
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? ($_GET['user_id'] ?? null);

    // Validate user ID
    $user_id = filter_var($user_id, FILTER_VALIDATE_INT);

    if ($user_id) {
        try {
            $conn->beginTransaction(); // Start transaction

            // First delete from student_details (child table)
            // This is crucial to satisfy foreign key constraints if they exist
            $stmt = $conn->prepare("DELETE FROM student_details WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // Then delete from users (parent table)
            // Ensure only student role users can be deleted this way
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
            $stmt->execute([$user_id]);

            $conn->commit(); // Commit transaction if both deletions succeed

            $response['success'] = true;
            $response['message'] = "Student deleted successfully.";

        } catch (PDOException $e) {
            $conn->rollBack(); // Rollback on database error
            $response['message'] = "Database error: " . $e->getMessage();
            // Log the error for debugging: error_log("Delete User PDO Error: " . $e->getMessage());
        }
    } else {
        $response['message'] = 'No valid user ID provided.';
    }
}

// Encode the response array to JSON and output it
echo json_encode($response);
?>
