<?php
session_start(); // Start the session to access $_SESSION['user_id']
include 'config/db.php'; // Include your PDO database connection file

// --- 3. Request Handling for AJAX calls ---
// This block will execute ONLY when the page is requested with an 'action' query parameter (i.e., AJAX calls from JS)
if (isset($_GET['action'])) {
    // Set CORS headers for AJAX requests - generally, restrict 'Access-Control-Allow-Origin' in production
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    // Handle preflight OPTIONS requests, which browsers send before actual POST/PUT requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit(); // Exit immediately for OPTIONS requests
    }

    // Set content type for JSON responses
    header('Content-Type: application/json');

    $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS); // Sanitize the action parameter

    // Get the ID of the user performing the action (e.g., admin or teacher)
    // IMPORTANT: Ensure your authentication system correctly sets $_SESSION['user_id']
    $recorded_by = $_SESSION['user_id'] ?? 1; // Default to 1 if not set (for testing or if user role allows anonymous marking)

    // Route actions to appropriate PHP functions
    switch ($action) {
        case 'fetch_batches':
            fetchBatches($conn);
            break;
        case 'fetch_students_and_attendance':
            fetchStudentsAndAttendance($conn);
            break;
        case 'save_attendance':
            saveAttendance($conn, $recorded_by);
            break;
        case 'export_attendance_csv':
            exportAttendanceCSV($conn);
            break;
        default:
            http_response_code(400); // Bad Request
            echo json_encode(["success" => false, "message" => "Invalid action specified."]);
            break;
    }

    // No need to close PDO connection explicitly, it will close when script ends
    exit();
}

// --- 4. PHP Functions for Database Interaction ---

/**
 * Fetches all active batches from the 'batches' table.
 * @param PDO $conn The PDO database connection object.
 */
function fetchBatches(PDO $conn) {
    try {
        $batches = [];
        $sql = "SELECT id, name FROM batches WHERE status = 'active' ORDER BY name ASC";
        $stmt = $conn->query($sql); // Use query() for simple selects without parameters
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "batches" => $batches]);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Error fetching batches: " . $e->getMessage()); // Log detailed error
        echo json_encode(["success" => false, "message" => "Failed to fetch batches due to an internal server error."]);
    }
}

/**
 * Fetches students for a specific batch and their attendance status for a given date.
 * @param PDO $conn The PDO database connection object.
 */
function fetchStudentsAndAttendance(PDO $conn) {
    $batch_id = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
    $date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Validate required parameters
    if (!$batch_id || !$date) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Batch ID and Date are required."]);
        exit();
    }

    $students_map = [];

    try {
        // Step 1: Fetch students belonging to the specified batch
        $sql_students = "SELECT u.id, u.name
                         FROM users u
                         JOIN student_details sd ON u.id = sd.user_id
                         WHERE u.role = 'student' AND sd.batch_id = ?
                         ORDER BY u.name ASC";
        $stmt_students = $conn->prepare($sql_students);
        $stmt_students->execute([$batch_id]);
        $students_result = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

        foreach ($students_result as $student_row) {
            $students_map[$student_row['id']] = [
                'id' => $student_row['id'],
                'name' => $student_row['name'],
                'isPresent' => false, // Default to false (absent)
                'cameraOn' => false,  // Default to false (camera off)
                'notes' => ''         // Default empty notes
            ];
        }

        // Step 2: Fetch existing attendance records for these students on the given date
        if (!empty($students_map)) {
            // Create a comma-separated list of placeholders for the IN clause
            $placeholders = implode(',', array_fill(0, count($students_map), '?'));
            $sql_attendance = "SELECT student_id, status, camera_on, notes
                               FROM attendance
                               WHERE batch_id = ? AND date = ? AND student_id IN ($placeholders)";
            $stmt_attendance = $conn->prepare($sql_attendance);

            // Dynamically bind parameters for batch_id, date, and then all student IDs
            $params = array_merge([$batch_id, $date], array_keys($students_map));
            $stmt_attendance->execute($params);
            $attendance_result = $stmt_attendance->fetchAll(PDO::FETCH_ASSOC);

            foreach ($attendance_result as $att_row) {
                $student_id = $att_row['student_id'];
                if (isset($students_map[$student_id])) {
                    // Update the defaults with actual attendance data
                    $students_map[$student_id]['isPresent'] = ($att_row['status'] === 'present');
                    $students_map[$student_id]['cameraOn'] = (bool)$att_row['camera_on']; // Cast 0/1 to boolean
                    $students_map[$student_id]['notes'] = $att_row['notes'] ?? '';
                }
            }
        }

        // Convert the associative array (map) back to an indexed array for JSON output
        $students_with_attendance = array_values($students_map);

        echo json_encode(["success" => true, "students" => $students_with_attendance]);

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Error fetching students and attendance: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Failed to load students and attendance due to an internal server error."]);
    }
}

/**
 * Saves or updates attendance records for multiple students in a transaction.
 * Expects a JSON payload from the frontend with an array of attendance data.
 * @param PDO $conn The PDO database connection object.
 * @param int $recorded_by The ID of the user recording the attendance.
 */
function saveAttendance(PDO $conn, int $recorded_by) {
    // Get the raw POST data (JSON string)
    $json_data = file_get_contents('php://input');
    // Decode JSON string to a PHP associative array
    $attendance_data = json_decode($json_data, true);

    // Basic validation of incoming data
    if (!is_array($attendance_data) || empty($attendance_data)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid attendance data received."]);
        exit();
    }

    $conn->beginTransaction(); // Start a database transaction for atomicity

    try {
        foreach ($attendance_data as $record) {
            // Validate and sanitize each record's data
            $student_id = filter_var($record['studentId'] ?? null, FILTER_VALIDATE_INT);
            $batch_id = filter_var($record['classId'] ?? null, FILTER_VALIDATE_INT); // 'classId' from JS maps to 'batch_id' in DB
            $date = filter_var($record['date'] ?? null, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $is_present = (isset($record['isPresent']) && $record['isPresent']) ? 1 : 0; // Convert boolean to 1 (true) or 0 (false)
            $camera_on = (isset($record['cameraOn']) && $record['cameraOn']) ? 1 : 0;   // Convert boolean to 1 (true) or 0 (false)
            $notes = filter_var($record['notes'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS); // Sanitize notes

            // Validate essential fields for the current record
            if (!$student_id || !$batch_id || !$date) {
                throw new Exception("Missing or invalid required attendance fields for one or more records.");
            }

            // Determine status ENUM value
            $status_enum = $is_present ? 'present' : 'absent';

            // Check if an attendance record already exists for this student, batch, and date
            $check_sql = "SELECT id FROM attendance WHERE student_id = ? AND batch_id = ? AND date = ?";
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->execute([$student_id, $batch_id, $date]);
            $attendance_id = $stmt_check->fetchColumn(); // Get the ID if exists

            if ($attendance_id) {
                // If record exists, UPDATE it
                $update_sql = "UPDATE attendance SET status = ?, camera_on = ?, notes = ?, recorded_at = CURRENT_TIMESTAMP WHERE id = ?";
                $stmt_update = $conn->prepare($update_sql);
                $stmt_update->execute([$status_enum, $camera_on, $notes, $attendance_id]);
            } else {
                // If no record exists, INSERT a new one
                $insert_sql = "INSERT INTO attendance (student_id, batch_id, date, status, camera_on, recorded_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($insert_sql);
                $stmt_insert->execute([$student_id, $batch_id, $date, $status_enum, $camera_on, $recorded_by, $notes]);
            }
        }

        $conn->commit(); // Commit the transaction if all operations were successful
        echo json_encode(["success" => true, "message" => "Attendance saved successfully!"]);

    } catch (Exception $e) {
        $conn->rollBack(); // Rollback transaction on any exception
        http_response_code(500);
        error_log("Error saving attendance: " . $e->getMessage()); // Log detailed error
        echo json_encode(["success" => false, "message" => "Failed to save attendance: " . $e->getMessage()]);
    }
}

/**
 * Exports attendance data as a CSV file.
 * Data can be filtered by batch_id and/or date.
 * @param PDO $conn The PDO database connection object.
 */
function exportAttendanceCSV(PDO $conn) {
    $batch_id = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
    $date_param = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    try {
        $sql = "SELECT u.name AS student_name, a.student_id, b.name AS batch_name, a.date, a.status, a.camera_on, a.notes
                FROM attendance a
                JOIN users u ON a.student_id = u.id
                JOIN batches b ON a.batch_id = b.id
                WHERE 1=1"; // Start with a true condition for easy appending of WHERE clauses

        $params = [];

        // Add filters if parameters are provided
        if ($batch_id !== null) {
            $sql .= " AND a.batch_id = ?";
            $params[] = $batch_id;
        }
        if ($date_param !== null) {
            $sql .= " AND a.date = ?";
            $params[] = $date_param;
        }
        $sql .= " ORDER BY a.date DESC, u.name ASC"; // Order by date and student name

        $stmt = $conn->prepare($sql);
        $stmt->execute($params); // Pass parameters directly to execute for PDO

        // Set headers to force download of a CSV file
        header('Content-Type: text/csv');
        // Filename is now suggested by client-side JS based on parameters
        header('Content-Disposition: attachment; filename="attendance_export.csv"');

        // Open a file handle to php://output, which writes directly to the HTTP response body
        $output = fopen('php://output', 'w');

        // Output CSV header row
        fputcsv($output, ['Student Name', 'Student ID', 'Batch Name', 'Date', 'Status', 'Camera On', 'Notes']);

        // Output data rows from the query result
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Convert 'camera_on' from 0/1 to 'Off'/'On' for better readability in the CSV
            $row['camera_on'] = ($row['camera_on'] == 1) ? 'On' : 'Off';
            fputcsv($output, $row);
        }

        fclose($output); // Close the file handle
        // No echo json_encode here, as we are outputting a CSV file directly

    } catch (PDOException $e) {
        // For CSV export, if an error occurs, it's harder to send JSON
        // A simple error message might be outputted or log and frontend handle timeout/failure
        http_response_code(500);
        error_log("Error exporting attendance CSV: " . $e->getMessage());
        echo "Error generating CSV: An internal server error occurred."; // Simple error for client
    }
}

?>

<!-- HTML, CSS, and JavaScript for the frontend -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASD Academy Attendance Portal</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'], // Set Inter as the default font
                    },
                    colors: {
                        primary: '#4F46E5', // Example primary color (Indigo 600)
                        secondary: '#6EE7B7', // Example secondary color (Teal 300)
                        success: '#10B981', // Green 500
                        error: '#EF4444',   // Red 500
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom styles for toggle switch - Tailwind doesn't have a direct toggle component */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px; /* Increased width for better touch target */
            height: 24px; /* Increased height */
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            -webkit-transition: .4s;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px; /* Adjusted size for the circle */
            width: 16px; /* Adjusted size */
            left: 4px; /* Adjusted position */
            bottom: 4px; /* Adjusted position */
            background-color: white;
            -webkit-transition: .4s;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #22C55E; /* Green for checked */
        }

        input:focus + .slider {
            box-shadow: 0 0 1px #22C55E;
        }

        input:checked + .slider:before {
            -webkit-transform: translateX(20px); /* Adjusted translation */
            -ms-transform: translateX(20px);
            transform: translateX(20px);
        }

        /* Loading indicator styles - HIDDEN BY DEFAULT NOW */
        .loading-overlay {
            display: none; /* Changed to display: none; */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.7);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border-left-color: #4F46E5; /* Primary color */
            animation: spin 1s ease infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        /* Custom message display (toast-like) */
        .message-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 2000;
        }
        .message-box {
            padding: 0.75rem 1.25rem;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
            min-width: 250px;
        }
        .message-box.show {
            opacity: 1;
            transform: translateY(0);
        }
        .message-box.success { background-color: #d1fae5; color: #065f46; } /* Green-100, Green-800 */
        .message-box.error { background-color: #fee2e2; color: #991b1b; }   /* Red-100, Red-800 */
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">

    <div class="min-h-screen flex flex-col items-center py-10 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-4xl bg-white p-8 rounded-xl shadow-lg">
            <h1 class="text-3xl font-extrabold text-gray-900 text-center mb-6">
                ASD Academy Attendance Portal
            </h1>
            <p class="text-center text-gray-600 mb-8">Mark attendance for students and track camera status.</p>

            <!-- Admin Controls: Class and Date Selection -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 p-6 bg-blue-50 rounded-lg shadow-inner">
                <div>
                    <label for="classSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Class (Batch):</label>
                    <select id="classSelect" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md shadow-sm">
                        <!-- Default option changed, as loading indicator is removed -->
                        <option value="">-- Select a Batch --</option>
                    </select>
                </div>
                <div>
                    <label for="attendanceDate" class="block text-sm font-medium text-gray-700 mb-2">Attendance Date:</label>
                    <input type="date" id="attendanceDate" class="mt-1 block w-full pl-3 pr-3 py-2 text-base border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary sm:text-sm">
                </div>
            </div>

            <!-- Attendance Table -->
            <div class="overflow-x-auto rounded-lg shadow-md mb-8">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Student Name
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Student ID
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Attendance
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Camera On
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Notes
                            </th>
                        </tr>
                    </thead>
                    <tbody id="studentAttendanceBody" class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">Please select a class and date to load students.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                <button id="saveAttendanceBtn" class="bg-primary text-white font-semibold py-3 px-6 rounded-lg shadow-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition duration-150 ease-in-out w-full sm:w-auto">
                    Save Attendance
                </button>
                <button id="exportDataBtn" class="bg-green-500 text-white font-semibold py-3 px-6 rounded-lg shadow-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition duration-150 ease-in-out w-full sm:w-auto">
                    Export Attendance (CSV)
                </button>
            </div>

            <!-- Message Container for User Feedback (toast-like) -->
            <div id="messageContainer" class="message-container"></div>
        </div>
    </div>

    <!-- Loading Overlay - No longer displayed by default -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
    </div>

    <script>
        // --- 1. Global Variables & DOM Elements ---
        const PHP_BACKEND_URL = window.location.href.split('?')[0];

        const classSelect = document.getElementById('classSelect');
        const attendanceDateInput = document.getElementById('attendanceDate');
        const studentAttendanceBody = document.getElementById('studentAttendanceBody');
        const saveAttendanceBtn = document.getElementById('saveAttendanceBtn');
        const exportDataBtn = document.getElementById('exportDataBtn');
        // Removed direct references to messageBox and messageText as they are created dynamically
        // and loadingOverlay/spinner related calls are removed from general flow.

        let studentsData = []; // To store fetched students and their current attendance/camera status

        // --- 2. Helper Functions ---

        /**
         * Displays a message to the user in a styled box (toast-like).
         * @param {string} message The message to display.
         * @param {'success' | 'error'} type The type of message (determines color).
         */
        function showMessage(message, type = 'success') {
            const messageBox = document.createElement('div');
            messageBox.className = `message-box ${type}`;
            messageBox.textContent = message;
            messageContainer.appendChild(messageBox);

            // Trigger reflow to ensure transition works
            void messageBox.offsetWidth;
            messageBox.classList.add('show');

            setTimeout(() => {
                messageBox.classList.remove('show');
                messageBox.addEventListener('transitionend', () => messageBox.remove(), { once: true });
            }, 3000); // Message disappears after 3 seconds
        }

        // Removed showLoading() and hideLoading() functions entirely

        // --- 3. Data Fetching Functions (Interacting with PHP Backend) ---

        /**
         * Fetches batches from the database and populates the dropdown.
         */
        async function fetchBatches() {
            // Removed showLoading()
            try {
                const response = await fetch(`${PHP_BACKEND_URL}?action=fetch_batches`);
                const result = await response.json();

                if (result.success) {
                    classSelect.innerHTML = '<option value="">-- Select a Batch --</option>';
                    if (result.batches.length > 0) {
                        result.batches.forEach(batch => {
                            const option = document.createElement('option');
                            option.value = batch.id;
                            option.textContent = batch.name;
                            classSelect.appendChild(option);
                        });
                        // Select the first batch by default if any exist
                        classSelect.value = result.batches[0].id;
                    } else {
                        classSelect.innerHTML = '<option value="">No active batches found</option>';
                    }
                    // After populating batches, immediately fetch students for the initially selected batch/date
                    fetchStudentsAndAttendance();
                } else {
                    showMessage(`Error fetching batches: ${result.message}`, 'error');
                    classSelect.innerHTML = '<option value="">Error loading batches</option>';
                }
            } catch (error) {
                console.error('Error fetching batches:', error);
                showMessage('An error occurred while fetching batches. Please check console.', 'error');
                classSelect.innerHTML = '<option value="">Error loading batches</option>';
            } finally {
                // Removed hideLoading()
            }
        }


        /**
         * Fetches students for the selected batch and their attendance for the selected date.
         */
        async function fetchStudentsAndAttendance() {
            const batchId = classSelect.value;
            const attendanceDate = attendanceDateInput.value;

            // If no batch or date selected, clear table and return
            if (!batchId || !attendanceDate) {
                studentAttendanceBody.innerHTML = `<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Please select a class and date to load students.</td></tr>`;
                studentsData = []; // Clear current student data
                return;
            }

            // Removed showLoading()

            try {
                const response = await fetch(`${PHP_BACKEND_URL}?action=fetch_students_and_attendance&batch_id=${batchId}&date=${attendanceDate}`);
                const result = await response.json();

                if (!result.success) {
                    showMessage(`Error fetching students and attendance: ${result.message}`, 'error');
                    studentAttendanceBody.innerHTML = `<tr><td colspan="5" class="px-6 py-4 text-center text-red-500">${result.message}</td></tr>`;
                    studentsData = []; // Clear current student data on error
                    return;
                }

                studentsData = result.students; // Store fetched students with their attendance status
                renderStudentRows();

            } catch (error) {
                console.error('Error fetching data:', error);
                showMessage('An error occurred while fetching students and attendance. Please check console.', 'error');
                studentAttendanceBody.innerHTML = `<tr><td colspan="5" class="px-6 py-4 text-center text-red-500">Error loading students.</td></tr>`;
            } finally {
                // Removed hideLoading()
            }
        }

        /**
         * Renders the student rows in the attendance table based on `studentsData`.
         */
        function renderStudentRows() {
            studentAttendanceBody.innerHTML = ''; // Clear previous rows
            if (studentsData.length === 0) {
                studentAttendanceBody.innerHTML = `<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No students found for this class.</td></tr>`;
                return;
            }

            studentsData.forEach(student => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50'; // Hover effect for rows

                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${student.name}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${student.id}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <label class="toggle-switch">
                            <input type="checkbox" data-student-id="${student.id}" data-type="attendance" ${student.isPresent ? 'checked' : ''}>
                            <span class="slider"></span>
                        </label>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <label class="toggle-switch">
                            <input type="checkbox" data-student-id="${student.id}" data-type="camera" ${student.cameraOn ? 'checked' : ''}>
                            <span class="slider"></span>
                        </label>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <input type="text" data-student-id="${student.id}" data-type="notes" placeholder="Add notes..." value="${student.notes}" class="p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary w-full text-sm">
                    </td>
                `;
                studentAttendanceBody.appendChild(row);
            });
        }

        // --- 4. Event Listeners ---

        // Event listeners for class/date change to reload students and attendance
        classSelect.addEventListener('change', fetchStudentsAndAttendance);
        attendanceDateInput.addEventListener('change', fetchStudentsAndAttendance);


        // Event listener for Save Attendance button
        saveAttendanceBtn.addEventListener('click', async () => {
            const batchId = classSelect.value;
            const attendanceDate = attendanceDateInput.value;

            if (!batchId || !attendanceDate) {
                showMessage('Please select a class and date before saving attendance.', 'error');
                return;
            }
            if (studentsData.length === 0) {
                showMessage('No students to save attendance for. Please select a valid class and date.', 'error');
                return;
            }

            const attendanceRecordsToSave = [];
            // Iterate over the DOM elements to get the current state of toggles and notes
            studentsData.forEach(student => {
                const attendanceToggle = studentAttendanceBody.querySelector(`input[data-student-id="${student.id}"][data-type="attendance"]`);
                const cameraToggle = studentAttendanceBody.querySelector(`input[data-student-id="${student.id}"][data-type="camera"]`);
                const notesInput = studentAttendanceBody.querySelector(`input[data-student-id="${student.id}"][data-type="notes"]`);

                attendanceRecordsToSave.push({
                    studentId: student.id,
                    isPresent: attendanceToggle.checked,
                    cameraOn: cameraToggle.checked,
                    notes: notesInput.value.trim(),
                    classId: batchId, // Use batchId here to match PHP parameter
                    date: attendanceDate
                });
            });

            // Removed showLoading()

            try {
                const response = await fetch(`${PHP_BACKEND_URL}?action=save_attendance`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(attendanceRecordsToSave),
                });
                const result = await response.json();

                if (result.success) {
                    showMessage('Attendance saved successfully!', 'success');
                    // Re-fetch students and attendance to reflect saved state, which handles updates/inserts
                    fetchStudentsAndAttendance();
                } else {
                    showMessage(`Failed to save attendance: ${result.message}`, 'error');
                }
            } catch (error) {
                console.error('Error saving attendance:', error);
                showMessage('An error occurred while saving attendance. Please check console.', 'error');
            } finally {
                // Removed hideLoading()
            }
        });

        // Event listener for Export to CSV button
        exportDataBtn.addEventListener('click', async () => {
            const batchId = classSelect.value;
            const attendanceDate = attendanceDateInput.value;

            // Removed showLoading()

            try {
                // Construct URL with parameters for filtering export
                let exportUrl = `${PHP_BACKEND_URL}?action=export_attendance_csv`;
                if (batchId) {
                    exportUrl += `&batch_id=${batchId}`;
                }
                if (attendanceDate) {
                    exportUrl += `&date=${attendanceDate}`;
                }

                const response = await fetch(exportUrl);

                if (!response.ok) {
                    const errorText = await response.text(); // Get potential error message from server
                    throw new Error(`Server responded with status ${response.status}: ${errorText || 'Unknown error during export.'}`);
                }

                // Create a blob from the response and trigger download
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                // Suggest a filename
                let fileName = 'attendance_export.csv';
                if (batchId && attendanceDate) {
                    fileName = `attendance_batch_${batchId}_${attendanceDate}.csv`;
                } else if (batchId) {
                    fileName = `attendance_batch_${batchId}_all_dates.csv`;
                } else if (attendanceDate) {
                    fileName = `attendance_all_batches_${attendanceDate}.csv`;
                }
                a.download = fileName;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();

                showMessage('Attendance data exported successfully as CSV!', 'success');

            } catch (error) {
                console.error('Error exporting data:', error);
                showMessage(`An error occurred during export: ${error.message}`, 'error');
            } finally {
                // Removed hideLoading()
            }
        });

        // --- 5. Initial Page Load ---
        window.onload = function() {
            // Set today's date in the input field
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            attendanceDateInput.value = `${yyyy}-${mm}-${dd}`;

            // Fetch and populate batches/classes dropdown, which then triggers student load
            fetchBatches();
        };
    </script>
</body>
</html>
