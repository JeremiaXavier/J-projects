<?php
require_once 'config.php';

// Only allow admin users to access registration page
// You can modify this to add a specific admin check

$currentUser = getCurrentUser();

// Optional: Check if current user is admin (you can add an is_admin field to users table)
// For now, we'll allow any logged-in user to register others
// Uncomment below to restrict to specific users
/*
$allowedAdmins = ['admin']; // Add usernames who can register users
if (!in_array($currentUser['username'], $allowedAdmins)) {
    header('Location: dashboard.php');
    exit();
}
*/

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    
    // Validation
    if (empty($username) || empty($password) || empty($fullName)) {
        $error = 'All fields are required';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, and underscores';
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            $error = 'Username already exists';
        } else {
            // Create user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name) VALUES (?, ?, ?)");
            
            try {
                $stmt->execute([$username, $hashedPassword, $fullName]);
                $success = 'User registered successfully! Username: ' . htmlspecialchars($username);
                
                // Clear form
                $_POST = array();
            } catch (PDOException $e) {
                $error = 'Error creating user. Please try again.';
            }
        }
    }
}

// Get all registered users
$stmt = $pdo->query("SELECT id, username, full_name, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New User - JProjects</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        /* Optional custom scrollbar style for the users list */
        #usersList::-webkit-scrollbar {
            width: 6px;
        }
        #usersList::-webkit-scrollbar-thumb {
            background-color: #cbd5e1; /* gray-300 */
            border-radius: 3px;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen font-sans">
    
    <header class="bg-white shadow-md border-b border-gray-100 sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-6">
                
                <a href="dashboard.php" class="flex items-center space-x-2">
                    <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-xl">
                        JP
                    </div>
                    <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">JProjects</h1>
                </a>
                
                <span class="text-gray-300 hidden sm:inline">|</span>
                <span class="text-lg font-medium text-indigo-600 hidden sm:inline">New Employee Registration</span>
            </div>
            
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-600 hidden md:inline">
                    Logged in as: <strong class="font-semibold text-gray-900"><?php echo htmlspecialchars($currentUser['full_name']); ?></strong>
                </span>
                
                <a href="dashboard.php" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-150 shadow-md">
                    Dashboard
                </a>
                
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-150">
                    Logout
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-6 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            
            <div class="lg:col-span-2 bg-white rounded-xl shadow-xl border border-gray-100 p-8 md:p-10">
                <h2 class="text-3xl font-bold text-gray-800 mb-8 border-b pb-4">Register New Employee</h2>
                
                <?php if ($success): ?>
                    <div class="bg-green-50 border-l-4 border-green-400 text-green-800 p-4 mb-6 rounded">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            <p class="font-medium"><?php echo $success; ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-400 text-red-800 p-4 mb-6 rounded">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                            <p class="font-medium"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label for="full_name" class="block text-sm font-semibold text-gray-700 mb-2">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" id="full_name" name="full_name" required
                            value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150"
                            placeholder="e.g., John Doe"
                        >
                    </div>

                    <div>
                        <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">
                            Username <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" id="username" name="username" required pattern="[a-zA-Z0-9_]+"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150"
                            placeholder="e.g., johndoe"
                        >
                        <p class="text-xs text-gray-500 mt-1">Only letters, numbers, and underscores (min 3 characters)</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="password" id="password" name="password" required minlength="6"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150"
                                placeholder="Minimum 6 characters"
                            >
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">
                                Confirm Password <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="password" id="confirm_password" name="confirm_password" required minlength="6"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150"
                                placeholder="Re-enter password"
                            >
                        </div>
                    </div>

                    <button 
                        type="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-lg transition duration-200 shadow-lg hover:shadow-xl focus:ring-4 focus:ring-indigo-500 focus:ring-opacity-50 mt-4"
                    >
                        <span class="flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                            <span>Register Employee</span>
                        </span>
                    </button>
                </form>

                <div class="mt-8 p-6 bg-indigo-50 rounded-xl border border-indigo-200">
                    <h3 class="font-extrabold text-indigo-900 mb-3 text-lg">Quick Guide</h3>
                    <ul class="text-sm text-indigo-800 space-y-2 list-disc pl-5">
                        <li>New users can **login immediately** with their credentials.</li>
                        <li>User access and project assignments are managed through the **Dashboard**.</li>
                        <li>Credentials should be securely shared with the new employee.</li>
                    </ul>
                </div>
            </div>

            <div class="lg:col-span-1 bg-white rounded-xl shadow-xl border border-gray-100 p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Registered Employees</h2>
                
                <div class="mb-5 flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-600">Total Users: <strong class="text-gray-900 text-lg"><?php echo count($users); ?></strong></span>
                    <input 
                        type="text" 
                        id="searchUsers" 
                        placeholder="Search users..." 
                        class="px-3 py-2 border border-gray-300 rounded-full text-sm focus:ring-2 focus:ring-indigo-500 transition duration-150 w-1/2"
                    >
                </div>

                <div class="overflow-y-auto max-h-[650px] space-y-3 pr-2" id="usersList">
                    <?php foreach ($users as $user): ?>
                        <div class="user-item p-4 bg-white rounded-lg border border-gray-200 shadow-sm hover:border-indigo-400 hover:shadow-md transition duration-150">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-800 leading-tight"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                                        <p class="text-xs text-gray-500 mt-0.5">@<?php echo htmlspecialchars($user['username']); ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-block px-3 py-0.5 text-xs font-bold bg-gray-100 text-gray-600 rounded-full">
                                        #<?php echo $user['id']; ?>
                                    </span>
                                    <p class="text-xs text-gray-400 mt-1">
                                        Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchUsers').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const userItems = document.querySelectorAll('.user-item');
            
            userItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    item.style.display = 'flex'; // Changed to flex to respect original layout
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match! Please check your entries.');
                // Optionally add visual feedback to the input fields here
                return false;
            }
        });
    </script>
</body>
</html>