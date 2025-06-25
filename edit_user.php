<?php
session_start();
include 'config/db.php';

if (isset($_GET['user_id'])) {
    $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

    // Fetch user data
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.email, sd.batch_id, sd.profile_photo
        FROM users u
        JOIN student_details sd ON u.id = sd.user_id
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Fetch active batches for dropdown
        $batches = $conn->query("SELECT id, name FROM batches WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);

        // Output the edit form
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Edit Student</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <style>
                /* Custom message display for this popup */
                .popup-message-container {
                    position: fixed;
                    top: 1rem;
                    right: 1rem;
                    z-index: 100;
                    width: calc(100% - 2rem); /* Adjust based on padding */
                    max-width: 400px; /* Max width for readability */
                }
                .popup-message-box {
                    padding: 0.75rem 1.25rem;
                    border-radius: 0.375rem;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                    margin-bottom: 0.5rem;
                    display: flex;
                    align-items: center;
                    opacity: 0;
                    transform: translateY(-20px);
                    transition: opacity 0.3s ease-out, transform 0.3s ease-out;
                }
                .popup-message-box.show {
                    opacity: 1;
                    transform: translateY(0);
                }
                .popup-message-box.success { background-color: #d1fae5; color: #065f46; }
                .popup-message-box.error { background-color: #fee2e2; color: #991b1b; }
            </style>
        </head>
        <body class="p-6 bg-gray-100">
            <!-- Message Container for custom toast messages in this popup -->
            <div id="popupMessageContainer" class="popup-message-container"></div>

            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Edit Student</h2>
                <button type="button" onclick="window.close()" class="text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="editStudentForm" method="POST" action="update_user.php" enctype="multipart/form-data">
                <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">

                <div class="mb-4">
                    <label for="edit_name" class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                    <input type="text" id="edit_name" name="name" value="<?= htmlspecialchars($user['name']) ?>"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>

                <div class="mb-4">
                    <label for="edit_email" class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                    <input type="email" id="edit_email" name="email" value="<?= htmlspecialchars($user['email']) ?>"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>

                <div class="mb-4">
                    <label for="edit_batch_id" class="block text-gray-700 text-sm font-bold mb-2">Batch</label>
                    <select id="edit_batch_id" name="batch_id"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?= htmlspecialchars($batch['id']) ?>" <?= $batch['id'] == $user['batch_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($batch['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="edit_profile_photo" class="block text-gray-700 text-sm font-bold mb-2">Profile Photo</label>
                    <input type="file" id="edit_profile_photo" name="profile_photo"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <?php if ($user['profile_photo']): ?>
                        <div class="mt-2">
                            <img src="<?= htmlspecialchars($user['profile_photo']) ?>" class="h-20 w-20 rounded-full object-cover" alt="Current Photo">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="window.close()"
                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition">Save Changes</button>
                </div>
            </form>

            <script>
            // Function to display custom toast messages in this popup
            function showPopupMessage(message, type = 'success') {
                const messageContainer = document.getElementById('popupMessageContainer');
                const messageBox = document.createElement('div');
                messageBox.className = `popup-message-box ${type}`;
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

            document.getElementById('editStudentForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);

                fetch('update_user.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showPopupMessage('Student updated successfully!', 'success');
                        // Call a function in the opener window to refresh and show message
                        if (window.opener && typeof window.opener.refreshParentAndShowMessage === 'function') {
                            window.opener.refreshParentAndShowMessage('Student updated successfully!', 'success');
                        }
                        // Close the popup after a short delay
                        setTimeout(() => window.close(), 1000);
                    } else {
                        showPopupMessage('Error: ' + (data.message || 'Failed to update student.'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showPopupMessage('An error occurred while updating student.', 'error');
                });
            });
            </script>
        </body>
        </html>
        <?php
    } else {
        echo "<p class='text-red-500 p-6'>Student not found.</p>";
    }
} else {
    echo "<p class='text-red-500 p-6'>No student ID provided.</p>";
}
?>