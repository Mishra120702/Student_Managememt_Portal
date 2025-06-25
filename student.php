<?php
session_start();
include 'config/db.php';

// Fetch batches for dropdown
$batches = $conn->query("SELECT id, name FROM batches WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);

// Pagination
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Get total count of students
$totalStudents = $conn->query("SELECT COUNT(*) FROM users u JOIN student_details sd ON u.id = sd.user_id WHERE u.role = 'student'")->fetchColumn();

// Fetch students with pagination
$query = "SELECT u.id, u.name, u.email, u.created_at, sd.batch_id, sd.profile_photo, b.name as batch_name
          FROM users u
          JOIN student_details sd ON u.id = sd.user_id
          LEFT JOIN batches b ON sd.batch_id = b.id
          WHERE u.role = 'student'
          ORDER BY u.created_at DESC
          LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($query);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPages = ceil($totalStudents / $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .popup-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-7xl mx-auto bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold mb-6">Student Management</h1>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php elseif (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 text-green-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Create Student Form -->
            <div>
                <h2 class="text-xl font-semibold mb-4">Add New Student</h2>
                <form method="POST" action="create_student.php" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input name="name" placeholder="Full Name" required class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input name="email" type="email" placeholder="Email" required class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input name="password" type="password" placeholder="Password" required class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Batch</label>
                        <select name="batch_id" required class="w-full border rounded px-3 py-2">
                            <option value="">Select Batch</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?= $batch['id'] ?>"><?= htmlspecialchars($batch['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Profile Photo</label>
                        <input type="file" name="profile_photo" accept="image/*" class="w-full border px-3 py-2 rounded">
                    </div>
                    <button type="submit" name="create_student" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add Student</button>
                </form>
            </div>

            <!-- Students Table -->
            <div class="overflow-x-auto">
                <h2 class="text-xl font-semibold mb-4">Student List</h2>
                <div class="bg-white rounded shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead>
                                <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                                    <th class="py-3 px-6 text-left">Name</th>
                                    <th class="py-3 px-6 text-left">Email</th>
                                    <th class="py-3 px-6 text-left">Batch</th>
                                    <th class="py-3 px-6 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 text-sm">
                                <?php foreach ($students as $student): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-100" id="student-row-<?= $student['id'] ?>">
                                        <td class="py-3 px-6 text-left whitespace-nowrap">
                                            <div class="flex items-center">
                                                <img src="<?= $student['profile_photo'] ?: 'https://via.placeholder.com/40' ?>" 
                                                     class="w-8 h-8 rounded-full mr-3" alt="<?= htmlspecialchars($student['name']) ?>">
                                                <?= htmlspecialchars($student['name']) ?>
                                            </div>
                                        </td>
                                        <td class="py-3 px-6 text-left"><?= htmlspecialchars($student['email']) ?></td>
                                        <td class="py-3 px-6 text-left"><?= htmlspecialchars($student['batch_name'] ?? 'N/A') ?></td>
                                        <td class="py-3 px-6 text-center">
    <div class="flex item-center justify-center space-x-2">
        <!-- Edit Button -->
        <button onclick="window.open('edit_user.php?user_id=<?= $student['id'] ?>', 'EditStudent', 'width=600,height=600,resizable=yes,scrollbars=yes')" 
                class="w-8 h-8 flex items-center justify-center bg-blue-500 text-white rounded-full hover:bg-blue-600 transition duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
        </button>
        
        <!-- Delete Button -->
        <button onclick="if(confirm('Are you sure you want to delete this student?')) { 
            fetch('delete_user.php?user_id=<?= $student['id'] ?>', {method: 'DELETE'})
                .then(response => response.json())
                .then(data => { 
                    if(data.success) { 
                        document.getElementById('student-row-<?= $student['id'] ?>').remove(); 
                        alert('Student deleted successfully'); 
                    } else { 
                        alert('Error: ' + (data.message || 'Failed to delete student')); 
                    } 
                }); 
            }" 
            class="w-8 h-8 flex items-center justify-center bg-red-500 text-white rounded-full hover:bg-red-600 transition duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
        </button>
    </div>
</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="bg-gray-50 px-4 py-3 flex items-center justify-between border-t border-gray-200">
                        <div class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?= ($offset + 1) ?></span> to <span class="font-medium"><?= min($offset + $perPage, $totalStudents) ?></span> of <span class="font-medium"><?= $totalStudents ?></span> students
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 border rounded text-sm bg-white hover:bg-gray-50">Previous</a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 border rounded text-sm bg-white hover:bg-gray-50">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
<!-- In students.php, replace the entire modal section and JavaScript with this: -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit buttons
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            window.open(`edit_user.php?user_id=${userId}`, 'EditStudent', 
                'width=600,height=600,resizable=yes,scrollbars=yes');
        });
    });

    // Delete buttons
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            if (confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
                fetch(`delete_user.php?user_id=${userId}`, {
                    method: 'DELETE'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const row = document.getElementById(`student-row-${userId}`);
                        if (row) row.remove();
                        alert('Student deleted successfully');
                    } else {
                        alert('Error: ' + (data.message || 'Failed to delete student'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting student');
                });
            }
        });
    });
});
</script>
</body>
</html>