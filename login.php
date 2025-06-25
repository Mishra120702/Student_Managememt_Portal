<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Login - ASD Academy</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Optional: Add some subtle transitions for better UX */
    input, button {
      transition: all 0.2s ease-in-out;
    }
    button:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen font-sans">

  <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
    <h1 class="text-3xl font-bold mb-6 text-center text-blue-600">ASD Academy Login</h1>

    <!-- Error message display area -->
    <div id="error-msg" class="mb-4 p-3 bg-red-100 text-red-700 border border-red-200 rounded hidden" role="alert">
      <!-- Error messages will be inserted here -->
    </div>

    <form id="loginForm" class="space-y-5">
      <div>
        <label class="block mb-2 font-medium text-gray-700" for="email">Email</label>
        <input id="email" name="email" type="email" placeholder="your.email@example.com" required
               class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" />
      </div>
      <div>
        <label class="block mb-2 font-medium text-gray-700" for="password">Password</label>
        <input id="password" name="password" type="password" placeholder="••••••••" required
               class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" />
      </div>
      <button type="submit"
              class="w-full bg-blue-600 text-white py-3 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 text-lg font-semibold transition duration-200 ease-in-out">
        Login
      </button>
    </form>
  </div>

<script>
document.getElementById('loginForm').addEventListener('submit', async function(e) {
  e.preventDefault(); // Prevent default form submission

  const errorMsg = document.getElementById('error-msg');
  errorMsg.classList.add('hidden'); // Hide any previous error messages
  errorMsg.textContent = ''; // Clear previous error text

  const formData = new FormData(this); // Get form data

  try {
    const res = await fetch('login_ajax.php', { // Assuming login_ajax.php handles the actual login logic
      method: 'POST',
      body: formData
    });

    const data = await res.json(); // Parse the JSON response

    if (data.success) {
      // If login is successful, redirect to the dashboard (index.php#dashboard)
      window.location.href = 'index.php#dashboard';
    } else {
      // If login fails, display the error message
      errorMsg.textContent = data.message || 'An unknown error occurred.';
      errorMsg.classList.remove('hidden');
    }
  } catch (err) {
    // Handle network errors or other unexpected issues
    console.error('Fetch error:', err);
    errorMsg.textContent = 'A network error occurred. Please try again.';
    errorMsg.classList.remove('hidden');
  }
});
</script>
</body>
</html>
