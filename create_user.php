<?php
session_start(); // Start the session
include 'config/db.php'; // Include your database connection file

// Set header for JSON response
header('Content-Type: application/json');

// Initialize a response array
$response = ['success' => false, 'message' => ''];

// Check if form data is set
if (isset($_POST['name'], $_POST['email'], $_POST['password'], $_POST['batch_id'])) {
    // Sanitize and retrieve POST data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password']; // Password will be hashed
    $batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
    $role = 'student'; // Default role for new users
    $photo_path = ''; // Initialize photo path

    // Server-side validation
    if (empty($name)) {
        $response['message'] = "Full Name is required.";
    } elseif (empty($email)) {
        $response['message'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // Validate email format
        $response['message'] = "Invalid email format.";
    } elseif (empty($password)) {
        $response['message'] = "Password is required.";
    } elseif (strlen($password) < 6) { // Example: enforce minimum password length
        $response['message'] = "Password must be at least 6 characters long.";
    } elseif (!$batch_id) {
        $response['message'] = "Batch must be selected.";
    } else {
        try {
            // Start a transaction for atomicity
            $conn->beginTransaction();

            // Handle profile photo upload
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
                            throw new Exception("File upload failed.");
                        }
                    }
                    $photo_path = $targetFilePath;
                }
            }

            // Check if email already exists in the users table
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $response['message'] = "Email already exists!";
            } else {
                // Hash the password
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // Insert into users table
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $hash, $role]);
                $user_id = $conn->lastInsertId(); // Get the ID of the newly inserted user

                // Insert into student_details table, linking to the new user
                $stmt = $conn->prepare("INSERT INTO student_details (user_id, batch_id, profile_photo) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $batch_id, $photo_path]);

                $conn->commit(); // Commit the transaction if all inserts are successful
                $response['success'] = true;
                $response['message'] = "Student created successfully.";
            }
        } catch (PDOException $e) {
            $conn->rollBack(); // Rollback on database error
            $response['message'] = "Database error: " . $e->getMessage();
            // Log error for debugging: error_log("Create Student PDO Error: " . $e->getMessage());
        } catch (Exception $e) {
            // Catch custom exceptions for file upload errors etc.
            $conn->rollBack();
            $response['message'] = "Error: " . $e->getMessage();
            // Log error for debugging: error_log("Create Student Error: " . $e->getMessage());
        }
    }
} else {
    $response['message'] = "Invalid request data. All required fields must be submitted.";
}

// Encode the response array to JSON and output it
echo json_encode($response);
?>