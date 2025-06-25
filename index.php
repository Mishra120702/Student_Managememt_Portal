<?php
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header("Location: login.php");
//     exit;
// }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ASD Academy SPA</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://unpkg.com/@fortawesome/fontawesome-free/css/all.min.css">
  <style>
    /* CSS for fade effect */
    #main-content.fade {
      opacity: 0;
      transition: opacity 0.3s ease-in-out;
    }
    #main-content.fade.visible {
      opacity: 1;
    }
    /* Simple hover effect for sidebar links */
    .sidebar-link {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        border-radius: 0.375rem;
        color: #4a5568; /* gray-700 */
        transition: background-color 0.2s ease, color 0.2s ease;
    }
    .sidebar-link:hover,
    .sidebar-link.active {
        background-color: #ebf4ff; /* blue-100 */
        color: #2563eb; /* blue-600 */
    }
    .sidebar-link .icon {
        margin-right: 0.75rem;
        font-size: 1.125rem; /* text-lg */
    }
  </style>
</head>
<body class="bg-gray-100 font-sans min-h-screen flex">

  <!-- Sidebar -->
  <aside class="w-64 bg-white shadow-md p-6">
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-blue-600">ASD Academy</h2>
      <p class="text-sm text-gray-500">Smart Portal</p>
    </div>
    <nav class="space-y-3">
      <a href="#dashboard" class="sidebar-link active" data-page="dashboard">
        <i class="fas fa-th-large icon"></i>
        <span>Dashboard</span>
      </a>
      <a href="#students" class="sidebar-link" data-page="student">
        <i class="fas fa-user-graduate icon"></i>
        <span>Students</span>
      </a>
      <a href="#teachers" class="sidebar-link" data-page="teachers">
        <i class="fas fa-chalkboard-teacher icon"></i>
        <span>Teachers</span>
      </a>
      <a href="#batches" class="sidebar-link" data-page="batches">
        <i class="fas fa-briefcase icon"></i>
        <span>Batches</span>
      </a>
      <a href="#attendance" class="sidebar-link" data-page="attendance">
        <i class="fas fa-clipboard-check icon"></i>
        <span>Attendance</span>
      </a>
      <a href="#announcements" class="sidebar-link" data-page="announcements">
        <i class="fas fa-bullhorn icon"></i>
        <span>Announcements</span>
      </a>
      <a href="logout.php" class="sidebar-link" data-page="logout">
        <i class="fas fa-sign-out-alt icon"></i>
        <span>Logout</span>
      </a>
    </nav>
  </aside>

  <!-- Main content area -->
  <main id="main-content" class="flex-1 p-6 fade visible">
    <!-- Content will be loaded here by JavaScript -->
  </main>

<script>
// Select all sidebar links
const sidebarLinks = document.querySelectorAll('.sidebar-link');
const mainContent = document.getElementById('main-content');

// Add click event listeners to sidebar links for SPA navigation
sidebarLinks.forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault(); // Prevent default link behavior
    const page = link.getAttribute('data-page'); // Get the data-page attribute value
    window.location.hash = page; // Update the URL hash to trigger hashchange event
  });
});

// Function to load page content via AJAX
async function loadPage(page) {
  // Add fade-out effect
  mainContent.classList.remove('visible');

  // Small delay to allow fade-out animation to be noticeable
  await new Promise(resolve => setTimeout(resolve, 200));

  try {
    const res = await fetch(page + '.php'); // Fetch content from the corresponding PHP file
    if (!res.ok) { // Check if the HTTP response was successful
        // If not successful, handle specific HTTP errors or general failure
        if (res.status === 403) {
            throw new Error('Access Denied. You do not have permission to view this page.');
        } else {
            throw new Error(`Failed to load page: ${res.status} ${res.statusText}`);
        }
    }
    const html = await res.text(); // Get the response as text (HTML content)
    mainContent.innerHTML = html; // Insert the HTML into the main content area

    // Add fade-in effect
    mainContent.classList.add('visible');

    // Update active state of sidebar links
    sidebarLinks.forEach(link => {
      if (link.getAttribute('data-page') === page) {
        link.classList.add('active');
      } else {
        link.classList.remove('active');
      }
    });

  } catch (err) {
    // Display error message if page loading fails
    mainContent.innerHTML = `<div class="text-center p-6 bg-white rounded shadow max-w-md mx-auto text-red-600">
                                <p class="font-bold text-lg mb-2">Error Loading Page</p>
                                <p>${err.message}</p>
                             </div>`;
    mainContent.classList.add('visible'); // Ensure content is visible even on error
    console.error('Page load error:', err);
  }
}

// Function to handle URL hash changes
function handleHashChange() {
  const page = window.location.hash.slice(1) || 'dashboard'; // Get page name from hash, default to 'dashboard'
  loadPage(page); // Load the corresponding page
}

// Initial page load based on current hash or default to dashboard
window.addEventListener('DOMContentLoaded', function() {
    // If no hash, set default and load
    if (!window.location.hash) {
        window.location.hash = 'dashboard';
    } else {
        // If hash exists, load the page immediately
        handleHashChange();
    }
});

// Handle back/forward navigation using hashchange event
window.addEventListener('hashchange', handleHashChange);
</script>
</body>
</html>
