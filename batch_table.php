<?php
// session_start();
include 'config/db.php';

// Handle AJAX requests
if (isset($_GET['action']) || $_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    try {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_batch') {
            createBatch($conn);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $action === 'delete_batch') {
            deleteBatch($conn);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'fetch_batches') {
            fetchBatches($conn);
        } else {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid request method or action."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Database error in batch_table.php: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "A database error occurred."]);
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Error in batch_table.php: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
    exit();
}

// Regular page request - render the table
$status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'all';
$batches = getBatches($conn, $status_filter);

function getBatches(PDO $conn, string $status_filter = 'all'): array {
    $sql = "SELECT id, name, academic_year, course_program, start_date, end_date, 
                   batch_code, status, classroom_location, timing_schedule, remarks, created_at
            FROM batches";
    $params = [];

    if ($status_filter !== 'all' && in_array($status_filter, ['active', 'inactive', 'archived'])) {
        $sql .= " WHERE status = ?";
        $params[] = $status_filter;
    }

    $sql .= " ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createBatch(PDO $conn) {
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

    if (empty($name) || empty($start_date) || empty($end_date) || empty($status)) {
        throw new Exception("Batch Name, Start Date, End Date, and Status are required.");
    }
    if (!in_array($status, ['active', 'inactive', 'archived'])) {
        throw new Exception("Invalid status value.");
    }
    if ($start_date > $end_date) {
        throw new Exception("Start Date cannot be after End Date.");
    }

    $check_sql = "SELECT COUNT(*) FROM batches WHERE name = ?";
    $check_params = [$name];

    if (!empty($batch_code)) {
        $check_sql .= " OR batch_code = ?";
        $check_params[] = $batch_code;
    }

    $stmt = $conn->prepare($check_sql);
    $stmt->execute($check_params);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Batch Name or Batch Code already exists.");
    }

    $sql = "INSERT INTO batches (name, academic_year, course_program, start_date, end_date, 
            batch_code, status, classroom_location, timing_schedule, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $name, $academic_year, $course_program, $start_date, $end_date,
        $batch_code, $status, $classroom_location, $timing_schedule, $remarks
    ]);

    echo json_encode(["success" => true, "message" => "Batch '{$name}' created successfully!"]);
}

function fetchBatches(PDO $conn) {
    $status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'all';
    $batches = getBatches($conn, $status_filter);
    echo json_encode(["success" => true, "batches" => $batches]);
}

function deleteBatch(PDO $conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $batch_id = $data['batch_id'] ?? ($_GET['batch_id'] ?? null);
    $batch_id = filter_var($batch_id, FILTER_VALIDATE_INT);

    if (!$batch_id) {
        throw new Exception("No valid Batch ID provided for deletion.");
    }

    $sql = "DELETE FROM batches WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$batch_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Batch deleted successfully."]);
    } else {
        throw new Exception("Batch not found or could not be deleted.");
    }
}
?>

<table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-50">
        <tr>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Batch Name</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Year</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dates</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
        </tr>
    </thead>
    <tbody id="batchListBody" class="bg-white divide-y divide-gray-200">
        <?php if (empty($batches)): ?>
            <tr>
                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                    No <?= $status_filter !== 'all' ? $status_filter . ' ' : '' ?>batches found.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($batches as $batch): ?>
                <tr id="batch-row-<?= $batch['id'] ?>" class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($batch['name']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($batch['batch_code'] ?? 'N/A') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($batch['academic_year'] ?? 'N/A') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($batch['start_date']) ?> to <?= htmlspecialchars($batch['end_date']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                            <?= $batch['status'] === 'active' ? 'bg-green-100 text-green-800' :
                               ($batch['status'] === 'inactive' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                            <?= htmlspecialchars($batch['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                        <div class="flex items-center justify-center space-x-2">
                            <button type="button" data-batch-id="<?= $batch['id'] ?>"
                                class="edit-btn w-8 h-8 flex items-center justify-center bg-blue-500 text-white rounded-full hover:bg-blue-600 transition duration-200"
                                title="Edit Batch">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button type="button" data-batch-id="<?= $batch['id'] ?>"
                                class="delete-btn w-8 h-8 flex items-center justify-center bg-red-500 text-white rounded-full hover:bg-red-600 transition duration-200"
                                title="Delete Batch">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<script>
    // This script will only run when the table is loaded directly or via AJAX
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const batchId = this.dataset.batchId;
            const confirmMessage = 'Are you sure you want to delete this batch? This action cannot be undone.';
            
            // Using the parent window's showConfirmModal function
            parent.showConfirmModal(confirmMessage, (confirmed) => {
                if (confirmed) {
                    fetch('batch_table.php?action=delete_batch', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ batch_id: batchId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            parent.showMessage(data.message, 'success');
                            document.getElementById(`batch-row-${batchId}`).remove();
                            // If no more rows, show message
                            if (document.querySelectorAll('#batchListBody tr').length === 1) { // Only header row left
                                document.getElementById('batchListBody').innerHTML = 
                                    '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No batches found.</td></tr>';
                            }
                        } else {
                            parent.showMessage(`Error: ${data.message}`, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        parent.showMessage('An error occurred while deleting the batch.', 'error');
                    });
                }
            });
        });
    });

    // Edit button functionality
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const batchId = this.dataset.batchId;
            parent.showMessage(`Edit functionality for Batch ID ${batchId} is not yet implemented.`, 'info');
        });
    });
</script>