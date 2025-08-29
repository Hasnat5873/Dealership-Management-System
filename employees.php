<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'User';
$user_id = $_SESSION['user_id'];

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch user name based on role
$name = '';
try {
    if ($role === 'User') {
        $stmt = $conn->prepare("SELECT first_name, last_name FROM user_profiles WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            $name = trim($profile['first_name'] . ' ' . $profile['last_name']);
        }
    } elseif ($role === 'Head of Company') {
        $stmt = $conn->prepare("SELECT company_name FROM company_head_details WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($company) {
            $name = $company['company_name'];
        }
    }
    if (empty($name)) {
        $name = $_SESSION['username'];
    }
} catch (PDOException $e) {
    $name = $_SESSION['username'];
}

$errorMsg = '';
$successMsg = '';

// Validate inputs
function validate_string($input, $field_name, $min_length = 2, $max_length = 255) {
    $input = trim($input);
    if (empty($input)) {
        return "$field_name is required.";
    }
    if (strlen($input) < $min_length) {
        return "$field_name must be at least $min_length characters.";
    }
    if (strlen($input) > $max_length) {
        return "$field_name cannot exceed $max_length characters.";
    }
    if (!preg_match('/^[a-zA-Z0-9\s\-\,\.\#]+$/', $input)) {
        return "$field_name contains invalid characters.";
    }
    return '';
}

function validate_contact($contact) {
    $contact = trim($contact);
    if (empty($contact)) {
        return "Contact is required.";
    }
    if (!preg_match('/^[\+]?[0-9\s\-]{10,15}$/', $contact)) {
        return "Contact must be a valid phone number (10-15 digits, may include + or -).";
    }
    return '';
}

function validate_role($role) {
    $valid_roles = ['Manager', 'Sales', 'Support', 'HR', 'Delivery Man', 'Other'];
    if (empty($role)) {
        return "Role is required.";
    }
    if (!in_array($role, $valid_roles)) {
        return "Invalid role selected.";
    }
    return '';
}

// Handle form submissions (only for User role)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $role === 'User') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMsg = "Invalid CSRF token.";
    } else {
        try {
            if (isset($_POST['add'])) {
                $name_input = trim($_POST['name']);
                $contact = trim($_POST['contact']);
                $role_input = $_POST['role'];

                // Validate inputs
                $name_error = validate_string($name_input, "Name");
                $contact_error = validate_contact($contact);
                $role_error = validate_role($role_input);

                if ($name_error || $contact_error || $role_error) {
                    $errorMsg = implode(" ", array_filter([$name_error, $contact_error, $role_error]));
                } else {
                    $stmt = $conn->prepare("INSERT INTO employees (user_id, name, contact, role) VALUES (:user_id, :name, :contact, :role)");
                    $stmt->execute(['user_id' => $user_id, 'name' => $name_input, 'contact' => $contact, 'role' => $role_input]);
                    $successMsg = "Employee added successfully!";
                }
            } elseif (isset($_POST['update'])) {
                $id = (int)$_POST['id'];
                $name_input = trim($_POST['name']);
                $contact = trim($_POST['contact']);
                $role_input = $_POST['role'];

                // Validate inputs
                $name_error = validate_string($name_input, "Name");
                $contact_error = validate_contact($contact);
                $role_error = validate_role($role_input);

                if ($name_error || $contact_error || $role_error) {
                    $errorMsg = implode(" ", array_filter([$name_error, $contact_error, $role_error]));
                } else {
                    $stmt = $conn->prepare("SELECT user_id FROM employees WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    if ($stmt->fetchColumn() == $user_id) {
                        $stmt = $conn->prepare("UPDATE employees SET name = :name, contact = :contact, role = :role WHERE id = :id AND user_id = :user_id");
                        $stmt->execute(['id' => $id, 'user_id' => $user_id, 'name' => $name_input, 'contact' => $contact, 'role' => $role_input]);
                        $successMsg = "Employee updated successfully!";
                    } else {
                        $errorMsg = "You are not authorized to update this employee.";
                    }
                }
            } elseif (isset($_POST['delete'])) {
                $id = (int)$_POST['id'];
                if ($id <= 0) {
                    $errorMsg = "Invalid employee ID.";
                } else {
                    $stmt = $conn->prepare("SELECT user_id FROM employees WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    if ($stmt->fetchColumn() == $user_id) {
                        $stmt = $conn->prepare("DELETE FROM employees WHERE id = :id AND user_id = :user_id");
                        $stmt->execute(['id' => $id, 'user_id' => $user_id]);
                        $successMsg = "Employee deleted successfully!";
                    } else {
                        $errorMsg = "You are not authorized to delete this employee.";
                    }
                }
            }
        } catch (PDOException $e) {
            $errorMsg = "Database error: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Fetch employees (only for User role)
$employees = [];
if ($role === 'User') {
    try {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMsg = "Failed to fetch employees: " . htmlspecialchars($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - Elite Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Enhanced Glassmorphism and Premium Styles */
        .glass-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }
        .glass-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);
        }
        .gradient-text {
            background: linear-gradient(90deg, #34d399, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .fade-in {
            animation: fadeIn 1s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            overflow-x: hidden;
        }
        /* Enhanced Particle Background */
        #particles-js {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        /* Premium Scrollbar */
        ::-webkit-scrollbar { width: 12px; }
        ::-webkit-scrollbar-track { background: #1e293b; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(#34d399, #10b981); border-radius: 10px; border: 2px solid #1e293b; }
        ::-webkit-scrollbar-thumb:hover { background: linear-gradient(#10b981, #059669); }
        /* Form and Table Styling */
        input, select, button {
            transition: all 0.4s ease;
        }
        input:focus, select:focus {
            outline: none;
            box-shadow: 0 0 0 2px #10b981;
        }
        table tr:hover {
            background: rgba(255, 255, 255, 0.08);
        }
        /* Premium Select Styling */
        select {
            appearance: none;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23d1d5db'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E") no-repeat right 1rem center/1.5em;
            background-color: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #d1d5db;
            border-radius: 0.5rem;
            padding: 0.75rem 2.5rem 0.75rem 1rem;
        }
        select option {
            background: #1e293b;
            color: #d1d5db;
        }
        /* Modal Styling */
        .modal-overlay {
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
        }
        .modal {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        .modal input, .modal select {
            background: rgba(255, 255, 255, 0.08);
            color: #d1d5db;
            border: 1px solid rgba(255, 255, 255, 0.25);
        }
        .modal button:hover {
            background: #059669;
        }
        /* Toast Styling */
        #successToast {
            background: linear-gradient(90deg, #34d399, #10b981);
        }
        #errorToast {
            background: linear-gradient(90deg, #ef4444, #dc2626);
        }
        .toast {
            transition: opacity 0.5s ease, transform 0.5s ease;
            opacity: 0;
            transform: translateY(20px);
        }
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        /* Responsive Table */
        .table-container {
            overflow-x: auto;
        }
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            .table-container {
                margin-bottom: 20px;
            }
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            tr {
                margin-bottom: 15px;
                background: rgba(255, 255, 255, 0.08);
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
                padding: 15px;
                border-radius: 12px;
            }
            td {
                border: none;
                position: relative;
                padding-left: 50%;
                white-space: normal;
                text-align: left;
            }
            td::before {
                position: absolute;
                left: 15px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: 600;
                color: #d1d5db;
                content: attr(data-label);
            }
            .action-buttons {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body class="min-h-screen text-gray-100 font-inter">
    <!-- Particle Background -->
    <div id="particles-js"></div>
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script>
        particlesJS('particles-js', {
            particles: {
                number: { value: 80, density: { enable: true, value_area: 1000 } },
                color: { value: '#34d399' },
                shape: { type: 'circle' },
                opacity: { value: 0.4, random: true },
                size: { value: 3, random: true },
                line_linked: { enable: true, distance: 150, color: '#34d399', opacity: 0.3, width: 1.5 },
                move: { enable: true, speed: 2, direction: 'none', random: false, straight: false, out_mode: 'out' }
            },
            interactivity: {
                detect_on: 'canvas',
                events: { onhover: { enable: true, mode: 'grab' }, onclick: { enable: true, mode: 'push' }, resize: true },
                modes: { grab: { distance: 200, line_linked: { opacity: 0.5 } }, push: { particles_nb: 4 } }
            },
            retina_detect: true
        });
    </script>

    <!-- Header -->
    <header class="bg-gradient-to-r from-emerald-800 to-teal-700 text-white py-8 px-8 shadow-2xl">
        <div class="container mx-auto flex items-center justify-between pl-64">
            <div class="flex items-center space-x-4">
                <i class="fa-solid fa-users text-5xl animate-pulse"></i>
                <h1 class="text-4xl font-bold gradient-text">Employee Management</h1>
            </div>
            <div class="text-right">
                <p class="text-2xl font-semibold"><?php echo htmlspecialchars($name); ?></p>
                <p class="text-md opacity-80"><?php echo htmlspecialchars($role); ?></p>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container mx-auto mt-10 px-6 flex">
        <?php include 'sidebar.php'; ?>
        
        <main class="flex-1 ml-64 p-10" role="main">
            <h2 class="text-2xl font-semibold gradient-text mb-8 flex items-center space-x-3">
                <i class="fa-solid fa-users text-xl"></i>
                <span>Employee Management</span>
            </h2>
            <?php if ($successMsg): ?>
                <div id="successToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white show"><?php echo htmlspecialchars($successMsg); ?></div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
                <div id="errorToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white show"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>
            <div class="glass-card p-10 rounded-2xl fade-in">
                <?php if ($role === 'Head of Company'): ?>
                    <div class="text-center">
                        <h3 class="text-xl font-semibold gradient-text mb-6 flex items-center justify-center space-x-3">
                            <i class="fa-solid fa-exclamation-triangle text-xl"></i>
                            <span>Access Denied</span>
                        </h3>
                        <p class="text-gray-400">Head of Company users are not authorized to view or manage employee data.</p>
                    </div>
                <?php else: ?>
                    <form class="add-form mb-6 grid grid-cols-1 md:grid-cols-2 gap-6" method="POST" id="addEmployeeForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="form-group">
                            <label for="name" class="text-lg font-semibold gradient-text mb-2">Name</label>
                            <input type="text" name="name" id="name" placeholder="Name" 
                                   class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required />
                        </div>
                        <div class="form-group">
                            <label for="contact" class="text-lg font-semibold gradient-text mb-2">Contact</label>
                            <input type="text" name="contact" id="contact" placeholder="Contact" 
                                   class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required />
                        </div>
                        <div class="form-group">
                            <label for="role" class="text-lg font-semibold gradient-text mb-2">Role</label>
                            <select name="role" id="role" class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required>
                                <option value="" disabled selected>Select Role</option>
                                <option value="Manager">Manager</option>
                                <option value="Sales">Sales</option>
                                <option value="Support">Support</option>
                                <option value="HR">HR</option>
                                <option value="Delivery Man">Delivery Man</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <button type="submit" name="add" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-4 rounded-lg col-span-1 md:col-span-2 transition-all duration-300 shadow-lg">
                            <i class="fa-solid fa-plus mr-3"></i> Add Employee
                        </button>
                    </form>
                    <div class="table-container">
                        <h3 class="text-xl font-semibold gradient-text mb-6">Employee List</h3>
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-emerald-800/60 text-gray-100">
                                    <th class="p-5">ID</th>
                                    <th class="p-5">Name</th>
                                    <th class="p-5">Contact</th>
                                    <th class="p-5">Role</th>
                                    <th class="p-5">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($employees): ?>
                                    <?php foreach ($employees as $emp): ?>
                                        <tr class="border-b border-gray-700/50">
                                            <td class="p-5" data-label="ID"><?php echo htmlspecialchars($emp['id']); ?></td>
                                            <td class="p-5" data-label="Name"><?php echo htmlspecialchars($emp['name']); ?></td>
                                            <td class="p-5" data-label="Contact"><?php echo htmlspecialchars($emp['contact']); ?></td>
                                            <td class="p-5" data-label="Role"><?php echo htmlspecialchars($emp['role']); ?></td>
                                            <td class="p-5 action-buttons" data-label="Actions">
                                                <button class="update-btn bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-3 rounded-lg transition-all duration-200 shadow" 
                                                        data-id="<?php echo htmlspecialchars($emp['id']); ?>" 
                                                        data-name="<?php echo htmlspecialchars($emp['name']); ?>" 
                                                        data-contact="<?php echo htmlspecialchars($emp['contact']); ?>" 
                                                        data-role="<?php echo htmlspecialchars($emp['role']); ?>" 
                                                        title="Edit Employee">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this employee?');" class="inline-block">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($emp['id']); ?>">
                                                    <button type="submit" name="delete" class="bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg transition-all duration-200 shadow" title="Delete Employee">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="p-5 text-center">No employees found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Update Modal -->
    <div class="modal-overlay fixed inset-0 flex items-center justify-center z-[1000] hidden" id="updateModal" aria-hidden="true" role="dialog">
        <div class="modal p-10 rounded-2xl max-w-lg w-full">
            <button class="close-btn absolute top-6 right-6 text-gray-400 hover:text-gray-100 text-3xl" id="closeModal" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <h3 class="text-2xl font-semibold gradient-text mb-8 flex items-center space-x-3">
                <i class="fa-solid fa-user-pen text-xl"></i>
                <span>Update Employee</span>
            </h3>
            <form method="POST" id="updateEmployeeForm" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="id" id="updateEmployeeId">
                <div class="form-group">
                    <label for="updateName" class="text-lg font-semibold gradient-text mb-2">Name</label>
                    <input type="text" name="name" id="updateName" placeholder="Name" 
                           class="w-full p-4 rounded-lg" required>
                </div>
                <div class="form-group">
                    <label for="updateContact" class="text-lg font-semibold gradient-text mb-2">Contact</label>
                    <input type="text" name="contact" id="updateContact" placeholder="Contact" 
                           class="w-full p-4 rounded-lg" required>
                </div>
                <div class="form-group">
                    <label for="updateRole" class="text-lg font-semibold gradient-text mb-2">Role</label>
                    <select name="role" id="updateRole" class="w-full p-4 rounded-lg" required>
                        <option value="" disabled>Select Role</option>
                        <option value="Manager">Manager</option>
                        <option value="Sales">Sales</option>
                        <option value="Support">Support</option>
                        <option value="HR">HR</option>
                        <option value="Delivery Man">Delivery Man</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <button type="submit" name="update" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-4 rounded-lg w-full transition-all duration-300 shadow-lg">
                    <i class="fa-solid fa-floppy-disk mr-3"></i> Save Changes
                </button>
            </form>
        </div>
    </div>

    <!-- Toasts -->
    <div id="successToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white opacity-0 pointer-events-none z-[1100]"></div>
    <div id="errorToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white opacity-0 pointer-events-none z-[1100]"></div>

    <script>
        const modalOverlay = document.getElementById('updateModal');
        const closeModalBtn = document.getElementById('closeModal');
        const updateForm = document.getElementById('updateEmployeeForm');
        const updateEmployeeId = document.getElementById('updateEmployeeId');
        const updateName = document.getElementById('updateName');
        const updateContact = document.getElementById('updateContact');
        const updateRole = document.getElementById('updateRole');
        const addEmployeeForm = document.getElementById('addEmployeeForm');

        // Modal Handling
        document.querySelectorAll('.update-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                updateEmployeeId.value = btn.getAttribute('data-id');
                updateName.value = btn.getAttribute('data-name');
                updateContact.value = btn.getAttribute('data-contact');
                updateRole.value = btn.getAttribute('data-role');
                modalOverlay.classList.remove('hidden');
                modalOverlay.setAttribute('aria-hidden', 'false');
                updateName.focus();
            });
        });

        closeModalBtn.addEventListener('click', () => {
            modalOverlay.classList.add('hidden');
            modalOverlay.setAttribute('aria-hidden', 'true');
            updateForm.reset();
        });

        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) {
                modalOverlay.classList.add('hidden');
                modalOverlay.setAttribute('aria-hidden', 'true');
                updateForm.reset();
            }
        });

        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modalOverlay.classList.contains('hidden')) {
                modalOverlay.classList.add('hidden');
                modalOverlay.setAttribute('aria-hidden', 'true');
                updateForm.reset();
            }
        });

        // Client-side Validation for Add Employee Form
        if (addEmployeeForm) {
            addEmployeeForm.addEventListener('submit', (e) => {
                const name = document.getElementById('name').value.trim();
                const contact = document.getElementById('contact').value.trim();
                const role = document.getElementById('role').value;

                if (name.length < 2 || name.length > 255) {
                    e.preventDefault();
                    showToast('error', 'Name must be between 2 and 255 characters.');
                    return;
                }
                if (!/^[a-zA-Z0-9\s\-\,\.\#]+$/.test(name)) {
                    e.preventDefault();
                    showToast('error', 'Name contains invalid characters.');
                    return;
                }
                if (!/^[\+]?[0-9\s\-]{10,15}$/.test(contact)) {
                    e.preventDefault();
                    showToast('error', 'Contact must be a valid phone number (10-15 digits, may include + or -).');
                    return;
                }
                if (!['Manager', 'Sales', 'Support', 'HR', 'Delivery Man', 'Other'].includes(role)) {
                    e.preventDefault();
                    showToast('error', 'Please select a valid role.');
                    return;
                }
            });
        }

        // Client-side Validation for Update Form
        if (updateForm) {
            updateForm.addEventListener('submit', (e) => {
                const name = updateName.value.trim();
                const contact = updateContact.value.trim();
                const role = updateRole.value;

                if (name.length < 2 || name.length > 255) {
                    e.preventDefault();
                    showToast('error', 'Name must be between 2 and 255 characters.');
                    return;
                }
                if (!/^[a-zA-Z0-9\s\-\,\.\#]+$/.test(name)) {
                    e.preventDefault();
                    showToast('error', 'Name contains invalid characters.');
                    return;
                }
                if (!/^[\+]?[0-9\s\-]{10,15}$/.test(contact)) {
                    e.preventDefault();
                    showToast('error', 'Contact must be a valid phone number (10-15 digits, may include + or -).');
                    return;
                }
                if (!['Manager', 'Sales', 'Support', 'HR', 'Delivery Man', 'Other'].includes(role)) {
                    e.preventDefault();
                    showToast('error', 'Please select a valid role.');
                    return;
                }
            });
        }

        // Show Toasts
        function showToast(type, message) {
            const toast = type === 'success' ? document.getElementById('successToast') : document.getElementById('errorToast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }
    </script>
</body>
</html>