<?php
include 'config/db.php';
// include 'auth.php'; // Uncomment if you have an authentication check file

// Count students
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'student'");
$stmt->execute();
$totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Count teachers
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'teacher'");
$stmt->execute();
$totalTeachers = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Count active batches
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM batches WHERE status = 'active'");
$stmt->execute();
$totalBatches = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>
<div class="flex min-h-screen">
    <!-- Main content -->
    <main class="flex-1 p-6">
      <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-semibold">Dashboard Overview</h1>
        <div class="flex items-center space-x-4">
          <i class="far fa-comment-dots text-gray-600 text-xl"></i>
          <i class="far fa-bell text-gray-600 text-xl"></i>
          <span class="font-medium text-gray-800">Administrator</span>
        </div>
      </div>

      <!-- Stats cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
          <div class="flex items-center space-x-4">
            <i class="fas fa-user-graduate text-blue-500 text-3xl"></i>
            <div>
              <p class="text-xl font-bold text-gray-800"><?= htmlspecialchars($totalStudents) ?></p>
              <p class="text-sm text-gray-500">Students</p>
            </div>
          </div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
          <div class="flex items-center space-x-4">
            <i class="fas fa-chalkboard-teacher text-green-500 text-3xl"></i>
            <div>
              <p class="text-xl font-bold text-gray-800"><?= htmlspecialchars($totalTeachers) ?></p>
              <p class="text-sm text-gray-500">Teachers</p>
            </div>
          </div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
          <div class="flex items-center space-x-4">
            <i class="fas fa-briefcase text-yellow-500 text-3xl"></i>
            <div>
              <p class="text-xl font-bold text-gray-800"><?= htmlspecialchars($totalBatches) ?></p>
              <p class="text-sm text-gray-500">Active Batches</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Attendance chart placeholder -->
      <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <p class="text-lg font-semibold mb-4 text-gray-800">Attendance Overview</p>
        <div class="h-48 bg-gray-50 flex items-center justify-center text-gray-400 border border-gray-200 rounded-md">
          <p> (Attendance Chart Placeholder - Integrate Chart.js or similar)</p>
        </div>
      </div>

      <!-- Recent Announcements -->
      <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex justify-between items-center mb-4">
          <p class="font-semibold text-lg text-gray-800">Recent Announcements</p>
          <a href="#announcements" class="text-blue-600 hover:underline text-sm">View All</a>
        </div>
        <ul class="space-y-4">
          <li class="border-b border-gray-200 pb-3 last:border-b-0">
            <h4 class="font-medium text-gray-700">Important: Holiday Schedule Update</h4>
            <p class="text-sm text-gray-500">Classes will be suspended from Dec 24th to Jan 2nd.</p>
            <span class="text-xs text-gray-400">Posted: 2024-12-15</span>
          </li>
          <li class="border-b border-gray-200 pb-3 last:border-b-0">
            <h4 class="font-medium text-gray-700">New Batch Enrollment Opens Soon</h4>
            <p class="text-sm text-gray-500">Keep an eye on the announcements for the new batch details.</p>
            <span class="text-xs text-gray-400">Posted: 2024-12-10</span>
          </li>
          <li class="border-b border-gray-200 pb-3 last:border-b-0">
            <h4 class="font-medium text-gray-700">Exam Preparation Tips Session</h4>
            <p class="text-sm text-gray-500">Join our online session on effective exam preparation techniques.</p>
            <span class="text-xs text-gray-400">Posted: 2024-12-05</span>
          </li>
        </ul>
      </div>
    </main>
</div>

