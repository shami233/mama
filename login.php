<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

// Handle Login
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid username/email or password";
        }
    }
}

// Handle Registration
if (isset($_POST['register'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($full_name) || empty($email) || empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        // Check if username or email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = "Username or email already exists";
        } else {
            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, username, password) VALUES (?, ?, ?, ?)");
            
            if ($stmt->execute([$full_name, $email, $username, $hashed_password])) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Register - My Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        .header i {
            font-size: 60px;
            margin-bottom: 15px;
            background: rgba(255, 255, 255, 0.2);
            width: 100px;
            height: 100px;
            line-height: 100px;
            border-radius: 50%;
            display: inline-block;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .tab-container {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .tab {
            flex: 1;
            text-align: center;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            color: #6c757d;
            position: relative;
        }

        .tab i {
            margin-right: 8px;
        }

        .tab.active {
            color: #667eea;
            background: white;
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .form-container {
            padding: 40px;
            background: white;
        }

        .form {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .form.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }

        .input-group {
            display: flex;
            align-items: center;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
            background: white;
        }

        .input-group:focus-within {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .input-group i {
            padding: 14px 18px;
            background: #f8f9fa;
            color: #667eea;
            font-size: 16px;
            width: 50px;
            text-align: center;
            border-right: 2px solid #e9ecef;
        }

        .input-group input {
            flex: 1;
            padding: 14px 18px;
            border: none;
            outline: none;
            font-size: 15px;
            background: transparent;
        }

        .input-group input::placeholder {
            color: #adb5bd;
        }

        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-register {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: #fee;
            color: #c00;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .alert i {
            font-size: 18px;
        }

        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
            font-size: 14px;
        }

        .footer-text a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }

        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s;
        }

        .strength-weak {
            background: #dc3545;
            width: 33.33%;
        }

        .strength-medium {
            background: #ffc107;
            width: 66.66%;
        }

        .strength-strong {
            background: #28a745;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <i class="fas fa-store"></i>
            <h1>Welcome Back!</h1>
            <p>Manage your shop efficiently with our system</p>
        </div>
        
        <div class="tab-container">
            <div class="tab active" onclick="showForm('login')" id="loginTab">
                <i class="fas fa-sign-in-alt"></i> Login
            </div>
            <div class="tab" onclick="showForm('register')" id="registerTab">
                <i class="fas fa-user-plus"></i> Register
            </div>
        </div>
        
        <div class="form-container">
            <!-- Login Form -->
            <div class="form active" id="loginForm">
                <?php if ($error && isset($_POST['login'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginFormElement">
                    <div class="form-group">
                        <label>Username or Email</label>
                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" name="username" placeholder="Enter your username or email" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" placeholder="Enter your password" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login to Dashboard
                    </button>
                </form>
                
                <div class="footer-text">
                    Don't have an account? <a onclick="showForm('register')">Register here</a>
                </div>
            </div>
            
            <!-- Register Form -->
            <div class="form" id="registerForm">
                <?php if ($error && isset($_POST['register'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="registerFormElement">
                    <div class="form-group">
                        <label>Full Name</label>
                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" name="full_name" placeholder="Enter your full name" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" placeholder="Enter your email" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Username</label>
                        <div class="input-group">
                            <i class="fas fa-at"></i>
                            <input type="text" name="username" placeholder="Choose a username" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="regPassword" placeholder="Create a password (min. 6 characters)" required onkeyup="checkPasswordStrength()">
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrength"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="confirm_password" placeholder="Confirm your password" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="register" class="btn btn-register">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
                
                <div class="footer-text">
                    Already have an account? <a onclick="showForm('login')">Login here</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showForm(formType) {
            // Update tabs
            document.getElementById('loginTab').classList.remove('active');
            document.getElementById('registerTab').classList.remove('active');
            
            // Update forms
            document.getElementById('loginForm').classList.remove('active');
            document.getElementById('registerForm').classList.remove('active');
            
            if (formType === 'login') {
                document.getElementById('loginTab').classList.add('active');
                document.getElementById('loginForm').classList.add('active');
            } else {
                document.getElementById('registerTab').classList.add('active');
                document.getElementById('registerForm').classList.add('active');
            }
        }
        
        function checkPasswordStrength() {
            const password = document.getElementById('regPassword').value;
            const strengthBar = document.getElementById('passwordStrength');
            
            // Remove all classes
            strengthBar.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
            
            if (password.length === 0) {
                strengthBar.style.width = '0';
                return;
            }
            
            // Check password strength
            let strength = 0;
            
            // Length check
            if (password.length >= 6) strength += 1;
            if (password.length >= 8) strength += 1;
            
            // Character type checks
            if (/[a-z]/.test(password)) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^a-zA-Z0-9]/.test(password)) strength += 1;
            
            // Determine strength class
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        }
        
        // Check for error in register form and show it if needed
        <?php if ($error && isset($_POST['register'])): ?>
        showForm('register');
        <?php endif; ?>
        
        <?php if ($success): ?>
        showForm('login');
        <?php endif; ?>
    </script>
</body>
</html>