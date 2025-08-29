<?php
session_start();
include 'db_connect.php';

if (!isset($conn) || $conn === null) {
    die("Database connection failed. Check db_connect.php or server status.");
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = "Username can only contain letters, numbers, and underscores.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header("Location: index.php");
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Dealership System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #0A4D68, #088395);
        }

       .login-container {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(15px);
    padding: 50px 30px; /* reduced padding to prevent overflow */
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.25);
    width: 100%;
    max-width: 400px; /* limit max width */
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    box-sizing: border-box; /* ensure padding included in width */
}

        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }

        h3 {
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .form-group {
            position: relative;
            margin-bottom: 25px;
        }

        .form-group i {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #fff;
            opacity: 0.7;
            font-size: 16px;
        }

      .form-group input {
    width: 100%;
    padding: 12px 12px 12px 45px;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    background: rgba(255, 255, 255, 0.25);
    color: #fff;
    outline: none;
    transition: background 0.3s, box-shadow 0.3s;
    box-sizing: border-box; /* fixes overflow issue */
}

        .form-group input::placeholder {
            color: #e0e0e0;
        }

        .form-group input:focus {
            background: rgba(255, 255, 255, 0.35);
            box-shadow: 0 0 10px rgba(255,255,255,0.5);
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #088395;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.3s, transform 0.3s, box-shadow 0.3s;
        }

        button:hover {
            background-color: #0A4D68;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }

        .error {
            color: #ff6b6b;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .signup {
            margin-top: 20px;
            font-size: 14px;
            color: #fff;
        }

        .signup a {
            color: #FFD700;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .signup a:hover { color: #fff; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <h3><i class="fa-solid fa-car-side"></i> Dealer Login</h3>
        <?php if ($error) echo "<p class='error'><i class='fa-solid fa-circle-exclamation'></i> $error</p>"; ?>
        <form method="POST">
            <div class="form-group">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="username" placeholder="Username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit"><i class="fa-solid fa-right-to-bracket"></i> Login</button>
        </form>
        <div class="signup">
            <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
        </div>
    </div>
</body>
</html>
