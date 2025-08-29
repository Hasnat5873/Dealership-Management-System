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
    $role = $_POST['role'] ?? '';

    // Fields for User role
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $trade_license_no = trim($_POST['trade_license_no'] ?? '');
    $nid_no = trim($_POST['nid_no'] ?? '');

    // Fields for Head of Company role
    $company_name = trim($_POST['company_name'] ?? '');
    $company_address = trim($_POST['company_address'] ?? '');
    $company_phone = trim($_POST['company_phone'] ?? '');
    $department = trim($_POST['department'] ?? '');

    // --- STRONG VALIDATION ---
    if (empty($username) || empty($password) || empty($role)) {
        $error = "Username, password, and role are required.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $error = "Username must be 3-20 characters long and can only contain letters, numbers, and underscores.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one digit.";
    } elseif (!preg_match('/[\W_]/', $password)) {
        $error = "Password must contain at least one special character.";
    } elseif (!in_array($role, ['User', 'Head of Company'])) {
        $error = "Invalid role selected.";
    } elseif ($role === 'User') {
        // Validate user profile details
        if (empty($first_name) || !preg_match("/^[a-zA-Z\s'-]{2,50}$/", $first_name)) {
            $error = "Valid first name is required (letters only, 2-50 chars).";
        } elseif (empty($last_name) || !preg_match("/^[a-zA-Z\s'-]{2,50}$/", $last_name)) {
            $error = "Valid last name is required (letters only, 2-50 chars).";
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Valid email address is required.";
        } elseif (!empty($phone) && !preg_match("/^\+?[0-9]{7,15}$/", $phone)) {
            $error = "Phone number must be 7-15 digits (can start with +).";
        } elseif (!empty($address) && strlen($address) < 5) {
            $error = "Address must be at least 5 characters long.";
        } elseif (empty($trade_license_no) || !preg_match('/^[a-zA-Z0-9-]{5,50}$/', $trade_license_no)) {
            $error = "Valid Trade License NO is required (alphanumeric with hyphens, 5-50 chars).";
        } elseif (empty($nid_no) || !filter_var($nid_no, FILTER_VALIDATE_INT) || strlen($nid_no) < 10 || strlen($nid_no) > 17) {
            $error = "Valid NID NO is required (integer, 10-17 digits).";
        } elseif (!isset($_FILES['trade_license_photo']) || $_FILES['trade_license_photo']['error'] !== UPLOAD_ERR_OK || $_FILES['trade_license_photo']['size'] > 5000000 || !in_array(mime_content_type($_FILES['trade_license_photo']['tmp_name']), ['image/jpeg', 'image/png', 'image/gif'])) {
            $error = "Valid Trade License photo is required (JPEG/PNG/GIF, max 5MB).";
        } elseif (isset($_FILES['tin_photo']) && $_FILES['tin_photo']['error'] === UPLOAD_ERR_OK && ($_FILES['tin_photo']['size'] > 5000000 || !in_array(mime_content_type($_FILES['tin_photo']['tmp_name']), ['image/jpeg', 'image/png', 'image/gif']))) {
            $error = "Valid TIN photo (optional, JPEG/PNG/GIF, max 5MB).";
        }
    } elseif ($role === 'Head of Company') {
        // Validate company details
        if (empty($company_name) || !preg_match("/^[a-zA-Z0-9\s&.'-]{2,100}$/", $company_name)) {
            $error = "Valid company name is required (letters, numbers, &, . , ' allowed).";
        } elseif (!empty($company_address) && strlen($company_address) < 5) {
            $error = "Company address must be at least 5 characters long.";
        } elseif (!empty($company_phone) && !preg_match("/^\+?[0-9]{7,15}$/", $company_phone)) {
            $error = "Company phone number must be 7-15 digits (can start with +).";
        } elseif (!empty($department) && !preg_match("/^[a-zA-Z\s'-]{2,50}$/", $department)) {
            $error = "Department must be 2-50 alphabetic characters.";
        }
    }

    // --- Database Insert (only if no error) ---
    if (empty($error)) {
        try {
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
            $checkStmt->execute(['username' => $username]);
            if ($checkStmt->fetch()) {
                $error = "Username already exists.";
            } else {
                $conn->beginTransaction();
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO users (username, password, role) 
                                        VALUES (:username, :password, :role)");
                $stmt->execute([
                    'username' => $username,
                    'password' => $hashed_password,
                    'role' => $role
                ]);
                $user_id = $conn->lastInsertId();

                // Handle file uploads for User role
                $trade_license_photo_path = null;
                $tin_photo_path = null;
                if ($role === 'User') {
                    $target_dir = "uploads/";
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }

                    // Trade License Photo (required)
                    $trade_license_photo_ext = pathinfo($_FILES["trade_license_photo"]["name"], PATHINFO_EXTENSION);
                    $trade_license_photo_path = $target_dir . 'trade_license_' . $user_id . '.' . $trade_license_photo_ext;
                    if (!move_uploaded_file($_FILES["trade_license_photo"]["tmp_name"], $trade_license_photo_path)) {
                        throw new Exception("Failed to upload Trade License photo.");
                    }

                    // TIN Photo (optional)
                    if (isset($_FILES['tin_photo']) && $_FILES['tin_photo']['error'] === UPLOAD_ERR_OK) {
                        $tin_photo_ext = pathinfo($_FILES["tin_photo"]["name"], PATHINFO_EXTENSION);
                        $tin_photo_path = $target_dir . 'tin_' . $user_id . '.' . $tin_photo_ext;
                        if (!move_uploaded_file($_FILES["tin_photo"]["tmp_name"], $tin_photo_path)) {
                            throw new Exception("Failed to upload TIN photo.");
                        }
                    }

                    $stmt = $conn->prepare("INSERT INTO user_profiles 
                        (user_id, first_name, last_name, email, phone, address, trade_license_no, trade_license_photo, nid_no, tin_photo) 
                        VALUES (:user_id, :first_name, :last_name, :email, :phone, :address, :trade_license_no, :trade_license_photo, :nid_no, :tin_photo)");
                    $stmt->execute([
                        'user_id' => $user_id,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'email' => $email,
                        'phone' => $phone ?: null,
                        'address' => $address ?: null,
                        'trade_license_no' => $trade_license_no,
                        'trade_license_photo' => $trade_license_photo_path,
                        'nid_no' => (int)$nid_no,
                        'tin_photo' => $tin_photo_path
                    ]);
                } elseif ($role === 'Head of Company') {
                    $stmt = $conn->prepare("INSERT INTO company_head_details 
                        (user_id, company_name, company_address, company_phone, department) 
                        VALUES (:user_id, :company_name, :company_address, :company_phone, :department)");
                    $stmt->execute([
                        'user_id' => $user_id,
                        'company_name' => $company_name,
                        'company_address' => $company_address ?: null,
                        'company_phone' => $company_phone ?: null,
                        'department' => $department ?: null
                    ]);
                }

                $conn->commit();
                header("Location: login.php");
                exit();
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "An error occurred during registration: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sign Up - Dealership System</title>
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

    .signup-container {
        background: rgba(255,255,255,0.15);
        backdrop-filter: blur(15px);
        padding: 40px 30px;
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.25);
        width: 100%;
        max-width: 450px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .signup-container:hover {
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
        margin-bottom: 20px;
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

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 12px 12px 12px 45px;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        background: rgba(255,255,255,0.25);
        color: #fff;
        outline: none;
        box-sizing: border-box;
        transition: background 0.3s, box-shadow 0.3s;
    }

    .form-group input::placeholder,
    .form-group select option {
        color: #e0e0e0;
    }

    .form-group input:focus,
    .form-group select:focus {
        background: rgba(255,255,255,0.35);
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
        text-align: center;
    }

    .login-link {
        margin-top: 20px;
        font-size: 14px;
        text-align: center;
        color: #fff;
    }

    .login-link a {
        color: #FFD700;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s;
    }

    .login-link a:hover {
        color: #fff;
        text-decoration: underline;
    }

    .role-specific { display: none; }
</style>
<script>
    function toggleFields() {
        const role = document.querySelector('select[name="role"]').value;
        document.getElementById('user-fields').style.display = role === 'User' ? 'block' : 'none';
        document.getElementById('company-fields').style.display = role === 'Head of Company' ? 'block' : 'none';
    }
</script>
</head>
<body>
<div class="signup-container">
    <h3><i class="fa-solid fa-user-plus"></i> Sign Up</h3>
    <?php if ($error) echo "<p class='error'><i class='fa-solid fa-circle-exclamation'></i> $error</p>"; ?>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <i class="fa-solid fa-user"></i>
            <input type="text" name="username" placeholder="Username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="password" placeholder="Password" required>
        </div>
        <div class="form-group">
            <i class="fa-solid fa-user-tag"></i>
            <select name="role" onchange="toggleFields()" required>
                <option value="">Select Role</option>
                <option value="User">User</option>
                <option value="Head of Company">Head of Company</option>
            </select>
        </div>

        <!-- User Role Fields -->
        <div id="user-fields" class="role-specific">
            <div class="form-group">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="first_name" placeholder="First Name" value="<?php echo htmlspecialchars($first_name ?? ''); ?>">
            </div>
            <div class="form-group">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="last_name" placeholder="Last Name" value="<?php echo htmlspecialchars($last_name ?? ''); ?>">
            </div>
            <div class="form-group">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>
            <div class="form-group">
                <i class="fa-solid fa-phone"></i>
                <input type="text" name="phone" placeholder="Phone (optional)" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
            </div>
            <div class="form-group">
                <i class="fa-solid fa-home"></i>
                <input type="text" name="address" placeholder="Address (optional)" value="<?php echo htmlspecialchars($address ?? ''); ?>">
            </div>
            <div class="form-group">
                <i class="fa-solid fa-file-contract"></i>
                <input type="text" name="trade_license_no" placeholder="Trade License NO" value="<?php echo htmlspecialchars($trade_license_no ?? ''); ?>">
            </div>
            <div class="form-group">
                <i class="fa-solid fa-image"></i>
                <input type="file" name="trade_license_photo" style="padding: 12px;" accept="image/*">
            </div>
            <div class="form-group">
                <i class="fa-solid fa-id-card"></i>
                <input type="number" name="nid_no" placeholder="NID NO" value="<?php echo htmlspecialchars($nid_no ?? ''); ?>">
            </div>
            <div class="form-group">
                <i class="fa-solid fa-image"></i>
                <input type="file" name="tin_photo" style="padding: 12px;" accept="image/*">
            </div>
        </div>

        <!-- Head of Company Fields -->
        <div id="company-fields" class="role-specific">
            <div class="form-group">
                <i class="fa-solid fa-building"></i>
                <input type="text" name="company_name" placeholder="Company Name" value="<?php echo htmlspecialchars($company_name ?? ''); ?>">
            </div>
            <div class="form-group">
                <i class="fa-solid fa-home"></i>
                <input type="text" name="company_address" placeholder="Company Address (optional)" value="<?php echo htmlspecialchars($company_address ?? ''); ?>">
            </div>
            <div class="form-group">
                <i class="fa-solid fa-phone"></i>
                <input type="text" name="company_phone" placeholder="Company Phone (optional)" value="<?php echo htmlspecialchars($company_phone ?? ''); ?>">
            </div>
            <div class="form-group">
                <i class="fa-solid fa-sitemap"></i>
                <input type="text" name="department" placeholder="Department (optional)" value="<?php echo htmlspecialchars($department ?? ''); ?>">
            </div>
        </div>

        <button type="submit"><i class="fa-solid fa-right-to-bracket"></i> Sign Up</button>
    </form>
    <div class="login-link">
        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>
</div>
<script>toggleFields();</script>
</body>
</html>