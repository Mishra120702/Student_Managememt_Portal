<?php
session_start(); // Start the session to manage session variables if needed (though not used directly for AJAX responses here)
include 'config/db.php'; // Include your database connection file

// Set header for JSON response, as this file will always return JSON
header('Content-Type: application/json');

// Initialize a response array
$response = ['success' => false, 'message' => 'Invalid request method'];

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and get input data
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);

    // Basic server-side validation
    if (!$user_id) {
        $response['message'] = "User ID is missing or invalid.";
    } elseif (empty($name)) {
        $response['message'] = "Name cannot be empty.";
    } elseif (empty($email)) {
        $response['message'] = "Email cannot be empty.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // Validate email format
        $response['message'] = "Invalid email format.";
    } elseif (!$batch_id) {
        $response['message'] = "Batch ID is missing or invalid.";
    } else {
        try {
            // Check if email is already taken by another user (excluding the current user being updated)
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $response['message'] = "Email already exists for another user!";
            } else {
                // Start a transaction for atomicity (optional but good practice for related updates)
                $conn->beginTransaction();

                // Update users table: name and email
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'student'");
                $stmt->execute([$name, $email, $user_id]);

                $photo_path = null;
                // Handle profile photo upload if a new one is provided
                if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                    $targetDir = "uploads/students/";
                    // Create directory if it doesn't exist
                    if (!is_dir($targetDir)) {
                        if (!mkdir($targetDir, 0777, true)) {
                            throw new Exception("Failed to create upload directory.");
                        }
                    }

                    $tmpPath = $_FILES["profile_photo"]["tmp_name"];
                    $originalName = $_FILES["profile_photo"]["name"];
                    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                    $mimeType = mime_content_type($tmpPath); // Get actual MIME type

                    // Validate file type and size
                    if (!in_array($mimeType, $allowedTypes)) {
                        throw new Exception("Invalid file type. Only JPG, PNG, and WebP allowed.");
                    } elseif ($_FILES["profile_photo"]["size"] > 2 * 1024 * 1024) { // 2MB limit
                        throw new Exception("File too large. Maximum size is 2MB.");
                    } else {
                        // Generate a unique filename using a hash of the file content
                        $fileHash = sha1_file($tmpPath);
                        $fileName = $fileHash . '.' . $extension;
                        $targetFilePath = $targetDir . $fileName;

                        // Only move if the file doesn't already exist (to prevent duplicates)
                        if (!file_exists($targetFilePath)) {
                            if (!move_uploaded_file($tmpPath, $targetFilePath)) {
                                throw new Exception("Failed to upload new profile photo.");
                            }
                        }
                        $photo_path = $targetFilePath;
                    }
                }

                // Update student_details table: batch_id and profile_photo (if new photo provided)
                if ($photo_path) {
                    $stmt = $conn->prepare("UPDATE student_details SET batch_id = ?, profile_photo = ? WHERE user_id = ?");
                    $stmt->execute([$batch_id, $photo_path, $user_id]);
                } else {
                    // Only update batch_id if no new photo is uploaded
                    $stmt = $conn->prepare("UPDATE student_details SET batch_id = ? WHERE user_id = ?");
                    $stmt->execute([$batch_id, $user_id]);
                }

                $conn->commit(); // Commit the transaction if all updates are successful
                $response['success'] = true;
                $response['message'] = "Student updated successfully.";
            }
        } catch (PDOException $e) {
            $conn->rollBack(); // Rollback on database error
            $response['message'] = "Database error: " . $e->getMessage();
            // Log error for debugging: error_log("Update User PDO Error: " . $e->getMessage());
        } catch (Exception $e) {
            // Catch custom exceptions for file upload errors etc.
            $conn->rollBack();
            $response['message'] = "Upload error: " . $e->getMessage();
            // Log error for debugging: error_log("Update User File Upload Error: " . $e->getMessage());
        }
    }
}

// Encode the response array to JSON and output it
echo json_encode($response);
?>
