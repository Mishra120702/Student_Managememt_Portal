<?php
session_start();
include 'config/db.php';

if (isset($_POST['create_student'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $batch_id = $_POST['batch_id'];
    $role = 'student';
    $photo_path = '';
    $error = '';

    // Handle profile photo upload with hash mapping
    if (!empty($_FILES['profile_photo']['name'])) {
        $targetDir = "uploads/students/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $tmpPath = $_FILES["profile_photo"]["tmp_name"];
        $originalName = $_FILES["profile_photo"]["name"];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $mimeType = mime_content_type($tmpPath);

        if (!in_array($mimeType, $allowedTypes)) {
            $error = "Invalid file type. Only JPG, PNG, and WebP allowed.";
        } elseif ($_FILES["profile_photo"]["size"] > 2 * 1024 * 1024) {
            $error = "File too large. Max size: 2MB.";
        } else {
            $fileHash = sha1_file($tmpPath);
            $fileName = $fileHash . '.' . $extension;
            $targetFilePath = $targetDir . $fileName;

            if (!file_exists($targetFilePath)) {
                if (!move_uploaded_file($tmpPath, $targetFilePath)) {
                    $error = "File upload failed.";
                }
            }

            $photo_path = $targetFilePath;
        }
    }

    // Proceed if no error
    if (!$error && $name && $email && $password && $batch_id) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hash, $role]);
            $user_id = $conn->lastInsertId();

            $stmt = $conn->prepare("INSERT INTO student_details (user_id, batch_id, profile_photo) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $batch_id, $photo_path]);
            $_SESSION['success'] = "Student created successfully.";
        }
    } elseif (!$error) {
        $error = "Please fill all fields.";
    }

    if ($error) {
        $_SESSION['error'] = $error;
    }
}

// Redirect back to students page
header("Location: index.php#student");
exit();