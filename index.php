<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Please enter both username and password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JProjects - Secure Login</title>
     <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16x16.png">
    <link rel="manifest" href="assets/site.webmanifest">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">

    <div
        class="bg-white rounded-xl shadow-2xl overflow-hidden w-full max-w-4xl transform transition-transform duration-300">

        <div class="grid grid-cols-1 lg:grid-cols-5">

            <div class="lg:col-span-2 bg-indigo-700 p-8 flex flex-col justify-between text-white">

                <div>
                    <div class="flex items-center space-x-3 mb-4">
                        <img src="./assets/logo-no-background.png" alt="JeremiaXavier Logo"
                            class=" h-6 object-contain ">
                    </div>

                    <h2 class="text-4xl font-extrabold mt-6 leading-tight">
                        Unlock Your <br>
                        Management Potential
                    </h2>
                    <p class="mt-4 text-indigo-200">
                        Access the JProjects platform for seamless resource planning, task management, and team
                        collaboration.
                    </p>
                </div>

                <div class="mt-8">
                    <div
                        class="h-32 w-full rounded-lg bg-indigo-600/70 flex items-center justify-center text-sm font-medium text-indigo-100 p-4">



                    </div>
                    <p class="text-xs mt-3 text-indigo-300">
                        Secure connection 
                    </p>
                </div>

            </div>

            <div class="lg:col-span-3 p-8 md:p-10 flex flex-col justify-center">

                <div class="text-center mb-8">
                    <div
                        class="inline-block w-16 h-16 bg-indigo-600 text-white rounded-xl flex items-center justify-center font-extrabold text-2xl shadow-lg">
                        <img src="./assets/android-chrome-512x512.png" alt="">
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800 mt-3">Sign In</h1>
                </div>

                <form method="POST" action="" class="space-y-6">

                    <?php if ($error): ?>
                        <div class="bg-red-50 border-l-4 border-red-400 text-red-800 p-3 rounded">
                            <p class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Username
                        </label>
                        <input type="text" id="username" name="username" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150"
                            placeholder="Enter your username">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password
                        </label>
                        <input type="password" id="password" name="password" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150"
                            placeholder="Enter your password">
                    </div>

                    <div class="flex justify-between items-center text-sm">
                        <div class="flex items-center">
                            <input type="checkbox" id="remember" name="remember"
                                class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            <label for="remember" class="ml-2 text-gray-600">Remember Me</label>
                        </div>
                        <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500 transition duration-150">
                            Forgot Password?
                        </a>
                    </div>

                    <button type="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-lg transition duration-200 shadow-md hover:shadow-lg focus:ring-4 focus:ring-indigo-500 focus:ring-opacity-50">
                        <span class="flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1">
                                </path>
                            </svg>
                            <span>Sign In</span>
                        </span>
                    </button>
                </form>

                <div class="mt-8 text-center border-t pt-6 text-xs text-gray-500">
                    <p>For selected developers only. Need access? Contact Admin.</p>
                    <p class="mt-2 text-gray-400">&copy; <?php echo date("Y"); ?> JeremiaXavier Softwares. All rights
                        reserved.</p>
                </div>
            </div>
        </div>

    </div>
</body>

</html>