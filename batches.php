<?php
session_start();
include 'config/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/@fortawesome/fontawesome-free/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: '#4F46E5', // Indigo 600
                        secondary: '#6EE7B7', // Teal 300
                        success: '#10B981', // Green 500
                        error: '#EF4444',   // Red 500
                    }
                }
            }
        }
    </script>
    <style>
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
        .message-box.success { background-color: #d1fae5; color: #065f46; }
        .message-box.error { background-color: #fee2e2; color: #991b1b; }

        .confirm-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .confirm-modal-content {
            background: white;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            max-width: 400px;
            text-align: center;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased p-6">
    <div class="max-w-7xl mx-auto bg-white rounded-xl shadow-lg p-8">
        <h1 class="text-3xl font-extrabold text-gray-900 text-center mb-8">Batch Management</h1>

        <div id="messageContainer" class="message-container"></div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Add New Batch Form -->
            <div>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Batch</h2>
                <form id="createBatchForm" class="space-y-4">
                    <div>
                        <label for="batchName" class="block text-sm font-medium text-gray-700 mb-1">Batch Name <span class="text-red-500">*</span></label>
                        <input type="text" id="batchName" name="name" placeholder="e.g., Web Dev 2025" required
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-primary focus:ring-primary py-2 px-3">
                    </div>
                    <div>
                        <label for="academicYear" class="block text-sm font-medium text-gray-700 mb-1">Academic Year / Session</label>
                        <input type="text" id="academicYear" name="academic_year" placeholder="e.g., 2025-2026"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-primary focus:ring-primary py-2 px-3">
                    </div>
                    <div>
                        <label for="courseProgram" class="block text-sm font-medium text-gray-700 mb-1">Course / Program</label>
                        <input type="text" id="courseProgram" name="course_program" placeholder="e.g., B.Sc Computer Science"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-primary focus:ring-primary py-2 px-3">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="startDate" class="block text-sm font-medium text-gray-700 mb-1">Start Date <span class="text-red-500">*</span></label>
                            <input type="date" id="startDate" name="start_date" required
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:border-primary focus:ring-primary py-2 px-3">
                        </div>
                        <div>
                            <label for="endDate" class="block text-sm font-medium text-gray-700 mb-1">End Date <span class="text-red-500">*</span></label>
                            <input type="date" id="endDate" name="end_date" required
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:border-primary focus:ring-primary py-2 px-3">
                        </div>
                    </div>
                    <div>
                        <label for="batchCode" class="block text-sm font-medium text-gray-700 mb-1">Batch Code / Unique ID</label>
                        <input type="text" id="batchCode" name="batch_code" placeholder="e.g., CS2025-A"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-primary focus:ring-primary py-2 px-3">
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                        <select id="status" name="status" required
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-primary focus:ring-primary py-2 px-3">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div>
                        <label for="classroomLocation" class="block text-sm font-medium text-gray-700 mb-1">Classroom / Location (Optional)</label>
                        <input type="text" id="classroomLocation" name="classroom_location" placeholder="e.g., Room 301 / Online"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-primary focus:ring-primary py-2 px-3">
                    </div>
                    <div>
                        <label for="timingSchedule" class="block text-sm font-medium text-gray-700 mb-1">Timing / Schedule</label>
                        <input type="text" id="timingSchedule" name="timing_schedule" placeholder="e.g., Mon-Fri, 10 AM to 1 PM"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-primary focus:ring-primary py-2 px-3">
                    </div>
                    <div>
                        <label for="remarks" class="block text-sm font-medium text-gray-700 mb-1">Remarks / Notes</label>
                        <textarea id="remarks" name="remarks" rows="3" placeholder="Any special notes for this batch..."
                                  class="w-full border-gray-300 rounded-md shadow-sm focus:border-primary focus:ring-primary py-2 px-3"></textarea>
                    </div>
                    <p class="text-sm text-gray-500 italic">
                        Note: Assigning students to batches is typically managed through a separate student editing interface.
                        This form creates the batch structure.
                    </p>
                    <button type="submit" class="w-full bg-primary text-white font-semibold py-2 px-4 rounded-md shadow-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition duration-150 ease-in-out">
                        Add Batch
                    </button>
                </form>
            </div>

            <!-- Batch List Table Container -->
            <div class="overflow-x-auto">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Batch List</h2>
                    <div>
                        <label for="batchStatusFilter" class="sr-only">Filter by Status</label>
                        <select id="batchStatusFilter" class="py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary">
                            <option value="all">All Batches</option>
                            <option value="active" selected>Active Batches</option>
                            <option value="inactive">Inactive Batches</option>
                            <option value="archived">Archived Batches</option>
                        </select>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <!-- This div will be populated by batch_table.php -->
                    <div id="batchTableContainer">
                        <?php include 'api/batch_table.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModalOverlay" class="confirm-modal-overlay">
        <div class="confirm-modal-content">
            <h3 class="text-lg font-semibold mb-4" id="confirmModalTitle">Confirm Action</h3>
            <p class="mb-6" id="confirmModalMessage">Are you sure you want to proceed?</p>
            <div class="flex justify-center space-x-4">
                <button id="confirmNo" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">Cancel</button>
                <button id="confirmYes" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        const PHP_BACKEND_URL = 'api/batch_table.php';
        const createBatchForm = document.getElementById('createBatchForm');
        const messageContainer = document.getElementById('messageContainer');
        const batchStatusFilter = document.getElementById('batchStatusFilter');
        const batchTableContainer = document.getElementById('batchTableContainer');

        const confirmModalOverlay = document.getElementById('confirmModalOverlay');
        const confirmModalTitle = document.getElementById('confirmModalTitle');
        const confirmModalMessage = document.getElementById('confirmModalMessage');
        const confirmYesBtn = document.getElementById('confirmYes');
        const confirmNoBtn = document.getElementById('confirmNo');

        function showMessage(message, type = 'success') {
            const messageBox = document.createElement('div');
            messageBox.className = `message-box ${type}`;
            messageBox.textContent = message;
            messageContainer.appendChild(messageBox);

            void messageBox.offsetWidth;
            messageBox.classList.add('show');

            setTimeout(() => {
                messageBox.classList.remove('show');
                messageBox.addEventListener('transitionend', () => messageBox.remove(), { once: true });
            }, 3000);
        }

        function showConfirmModal(message, callback) {
            confirmModalMessage.textContent = message;
            confirmModalOverlay.style.display = 'flex';

            confirmYesBtn.onclick = () => {
                confirmModalOverlay.style.display = 'none';
                callback(true);
            };

            confirmNoBtn.onclick = () => {
                confirmModalOverlay.style.display = 'none';
                callback(false);
            };
        }

        function loadBatchTable() {
            const selectedStatus = batchStatusFilter.value;
            fetch(`${PHP_BACKEND_URL}?status=${selectedStatus}`)
                .then(response => response.text())
                .then(html => {
                    batchTableContainer.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading batch table:', error);
                    showMessage('Failed to load batches. Please try again.', 'error');
                });
        }

        // Form submission
        createBatchForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(createBatchForm);

            try {
                const response = await fetch(`${PHP_BACKEND_URL}?action=create_batch`, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showMessage(result.message, 'success');
                    createBatchForm.reset();
                    loadBatchTable();
                } else {
                    showMessage(`Error: ${result.message}`, 'error');
                }
            } catch (error) {
                console.error('Error creating batch:', error);
                showMessage('An unexpected error occurred while creating the batch.', 'error');
            }
        });

        // Filter change
        batchStatusFilter.addEventListener('change', loadBatchTable);

        // Initial load
        document.addEventListener('DOMContentLoaded', loadBatchTable);
    </script>
</body>
</html>