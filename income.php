<?php
session_start();
include 'db_connect.php';

// Redirect if not logged in
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

$successMsg = '';
$errorMsg = '';

// Validate inputs
function validate_description($description) {
    $description = trim($description);
    if (empty($description)) {
        return "Description is required.";
    }
    if (strlen($description) < 2) {
        return "Description must be at least 2 characters.";
    }
    if (strlen($description) > 255) {
        return "Description cannot exceed 255 characters.";
    }
    if (!preg_match('/^[a-zA-Z0-9\s\-\,\.\#]+$/', $description)) {
        return "Description contains invalid characters.";
    }
    return '';
}

function validate_amount($amount) {
    if ($amount === '' || $amount === null) {
        return "Amount is required.";
    }
    $amount = (float)$amount;
    if ($amount < 0) {
        return "Amount cannot be negative.";
    }
    if ($amount > 99999999.99) {
        return "Amount is too large.";
    }
    return '';
}

function validate_date($date) {
    if (empty($date)) {
        return "Date is required.";
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return "Invalid date format.";
    }
    $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
        return "Invalid date.";
    }
    $today = new DateTime();
    if ($parsedDate > $today) {
        return "Date cannot be in the future.";
    }
    return '';
}

if ($role === 'User') {
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $errorMsg = "Invalid CSRF token.";
        } else {
            try {
                if (isset($_POST['add'])) {
                    $description = trim($_POST['description']);
                    $amount = $_POST['amount'];
                    $income_date = $_POST['income_date'];

                    // Validate inputs
                    $desc_error = validate_description($description);
                    $amount_error = validate_amount($amount);
                    $date_error = validate_date($income_date);

                    if ($desc_error || $amount_error || $date_error) {
                        $errorMsg = implode(" ", array_filter([$desc_error, $amount_error, $date_error]));
                    } else {
                        $stmt = $conn->prepare("INSERT INTO general_incomes (user_id, description, amount, income_date) 
                                                VALUES (:user_id, :description, :amount, :income_date)");
                        $stmt->execute([
                            'user_id' => $user_id,
                            'description' => $description,
                            'amount' => (float)$amount,
                            'income_date' => $income_date
                        ]);
                        $successMsg = "Income added successfully!";
                    }
                } elseif (isset($_POST['update'])) {
                    $id = (int)$_POST['id'];
                    $description = trim($_POST['description']);
                    $amount = $_POST['amount'];
                    $income_date = $_POST['income_date'];

                    // Validate inputs
                    $desc_error = validate_description($description);
                    $amount_error = validate_amount($amount);
                    $date_error = validate_date($income_date);

                    if ($id <= 0) {
                        $errorMsg = "Invalid income ID.";
                    } elseif ($desc_error || $amount_error || $date_error) {
                        $errorMsg = implode(" ", array_filter([$desc_error, $amount_error, $date_error]));
                    } else {
                        $stmt = $conn->prepare("UPDATE general_incomes 
                                                SET description = :description, amount = :amount, income_date = :income_date 
                                                WHERE id = :id AND user_id = :user_id");
                        $stmt->execute([
                            'id' => $id,
                            'user_id' => $user_id,
                            'description' => $description,
                            'amount' => (float)$amount,
                            'income_date' => $income_date
                        ]);
                        if ($stmt->rowCount() > 0) {
                            $successMsg = "Income updated successfully!";
                        } else {
                            $errorMsg = "No income found or you are not authorized.";
                        }
                    }
                } elseif (isset($_POST['delete'])) {
                    $id = (int)$_POST['id'];
                    if ($id <= 0) {
                        $errorMsg = "Invalid income ID.";
                    } else {
                        $stmt = $conn->prepare("DELETE FROM general_incomes WHERE id = :id AND user_id = :user_id");
                        $stmt->execute(['id' => $id, 'user_id' => $user_id]);
                        if ($stmt->rowCount() > 0) {
                            $successMsg = "Income deleted successfully!";
                        } else {
                            $errorMsg = "No income found or you are not authorized.";
                        }
                    }
                }
            } catch (PDOException $e) {
                $errorMsg = "Database error: " . htmlspecialchars($e->getMessage());
            }
        }
    }

    // Fetch incomes for the user
    try {
        $stmt = $conn->prepare("SELECT * FROM general_incomes WHERE user_id = :user_id ORDER BY income_date DESC");
        $stmt->execute(['user_id' => $user_id]);
        $incomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMsg = "Error fetching incomes: " . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMsg = "Access denied. Only Users can view or manage income data.";
    $incomes = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Income Management - Elite Dashboard</title>
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
        .modal input {
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
                <i class="fa-solid fa-money-bill-wave text-5xl animate-pulse"></i>
                <h1 class="text-4xl font-bold gradient-text">Income Management</h1>
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
                <i class="fa-solid fa-money-bill-wave text-xl"></i>
                <span>Income Management</span>
            </h2>
            <?php if ($successMsg): ?>
                <div id="successToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white show"><?php echo htmlspecialchars($successMsg); ?></div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
                <div id="errorToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white show"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>
            <div class="glass-card p-10 rounded-2xl fade-in">
                <?php if ($role === 'User'): ?>
                    <form class="add-form mb-6 grid grid-cols-1 md:grid-cols-2 gap-6" method="POST" id="addIncomeForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="form-group">
                            <label for="description" class="text-lg font-semibold gradient-text mb-2">Description</label>
                            <input type="text" name="description" id="description" placeholder="Description" 
                                   class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required />
                        </div>
                        <div class="form-group">
                            <label for="amount" class="text-lg font-semibold gradient-text mb-2">Amount (৳)</label>
                            <input type="number" name="amount" id="amount" step="0.01" min="0" placeholder="Amount (৳)" 
                                   class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required />
                        </div>
                        <div class="form-group">
                            <label for="income_date" class="text-lg font-semibold gradient-text mb-2">Date</label>
                            <input type="date" name="income_date" id="income_date" value="<?php echo date('Y-m-d'); ?>" 
                                   class="glass-card p-4 rounded-lg border-none text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-emerald-500" required />
                        </div>
                        <button type="submit" name="add" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-4 rounded-lg col-span-1 md:col-span-2 transition-all duration-300 shadow-lg">
                            <i class="fa-solid fa-plus mr-3"></i> Add Income
                        </button>
                    </form>
                    <div class="table-container">
                        <h3 class="text-xl font-semibold gradient-text mb-6">Income List</h3>
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-emerald-800/60 text-gray-100">
                                    <th class="p-5">ID</th>
                                    <th class="p-5">Description</th>
                                    <th class="p-5">Amount (৳)</th>
                                    <th class="p-5">Date</th>
                                    <th class="p-5">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($incomes): ?>
                                    <?php foreach ($incomes as $i): ?>
                                        <tr class="border-b border-gray-700/50">
                                            <td class="p-5" data-label="ID"><?php echo htmlspecialchars($i['id']); ?></td>
                                            <td class="p-5" data-label="Description"><?php echo htmlspecialchars($i['description']); ?></td>
                                            <td class="p-5" data-label="Amount (৳)"><?php echo number_format($i['amount'], 2); ?></td>
                                            <td class="p-5" data-label="Date"><?php echo htmlspecialchars($i['income_date']); ?></td>
                                            <td class="p-5 action-buttons" data-label="Actions">
                                                <button class="update-btn bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-3 rounded-lg transition-all duration-200 shadow" 
                                                        data-id="<?php echo htmlspecialchars($i['id']); ?>" 
                                                        data-description="<?php echo htmlspecialchars($i['description']); ?>" 
                                                        data-amount="<?php echo htmlspecialchars($i['amount']); ?>" 
                                                        data-date="<?php echo htmlspecialchars($i['income_date']); ?>" 
                                                        title="Edit Income">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this income?');" class="inline-block">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($i['id']); ?>">
                                                    <button type="submit" name="delete" class="bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg transition-all duration-200 shadow" title="Delete Income">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="p-5 text-center">No income records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center">
                        <h3 class="text-xl font-semibold gradient-text mb-6 flex items-center justify-center space-x-3">
                            <i class="fa-solid fa-exclamation-triangle text-xl"></i>
                            <span>Access Denied</span>
                        </h3>
                        <p class="text-gray-400">Only Users can view or manage income data.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Update Modal -->
    <?php if ($role === 'User'): ?>
    <div class="modal-overlay fixed inset-0 flex items-center justify-center z-[1000] hidden" id="updateModal" aria-hidden="true" role="dialog">
        <div class="modal p-10 rounded-2xl max-w-lg w-full">
            <button class="close-btn absolute top-6 right-6 text-gray-400 hover:text-gray-100 text-3xl" id="closeModal" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <h3 class="text-2xl font-semibold gradient-text mb-8 flex items-center space-x-3">
                <i class="fa-solid fa-pen-to-square text-xl"></i>
                <span>Update Income</span>
            </h3>
            <form method="POST" id="updateIncomeForm" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="id" id="updateIncomeId">
                <div class="form-group">
                    <label for="updateDescription" class="text-lg font-semibold gradient-text mb-2">Description</label>
                    <input type="text" name="description" id="updateDescription" placeholder="Description" 
                           class="w-full p-4 rounded-lg" required>
                </div>
                <div class="form-group">
                    <label for="updateAmount" class="text-lg font-semibold gradient-text mb-2">Amount (৳)</label>
                    <input type="number" name="amount" id="updateAmount" step="0.01" min="0" placeholder="Amount (৳)" 
                           class="w-full p-4 rounded-lg" required>
                </div>
                <div class="form-group">
                    <label for="updateDate" class="text-lg font-semibold gradient-text mb-2">Date</label>
                    <input type="date" name="income_date" id="updateDate" class="w-full p-4 rounded-lg" required>
                </div>
                <button type="submit" name="update" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-4 rounded-lg w-full transition-all duration-300 shadow-lg">
                    <i class="fa-solid fa-floppy-disk mr-3"></i> Save Changes
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Toasts -->
    <div id="successToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white opacity-0 pointer-events-none z-[1100]"></div>
    <div id="errorToast" class="toast fixed bottom-10 right-10 p-6 rounded-xl text-white opacity-0 pointer-events-none z-[1100]"></div>

    <script>
        <?php if ($role === 'User'): ?>
        const modalOverlay = document.getElementById('updateModal');
        const closeModalBtn = document.getElementById('closeModal');
        const updateForm = document.getElementById('updateIncomeForm');
        const updateIncomeId = document.getElementById('updateIncomeId');
        const updateDescription = document.getElementById('updateDescription');
        const updateAmount = document.getElementById('updateAmount');
        const updateDate = document.getElementById('updateDate');
        const addIncomeForm = document.getElementById('addIncomeForm');

        // Modal Handling
        document.querySelectorAll('.update-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                updateIncomeId.value = btn.getAttribute('data-id');
                updateDescription.value = btn.getAttribute('data-description');
                updateAmount.value = btn.getAttribute('data-amount');
                updateDate.value = btn.getAttribute('data-date');
                modalOverlay.classList.remove('hidden');
                modalOverlay.setAttribute('aria-hidden', 'false');
                updateDescription.focus();
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

        // Client-side Validation for Add Income Form
        if (addIncomeForm) {
            addIncomeForm.addEventListener('submit', (e) => {
                const description = document.getElementById('description').value.trim();
                const amount = document.getElementById('amount').value;
                const income_date = document.getElementById('income_date').value;

                if (description.length < 2 || description.length > 255) {
                    e.preventDefault();
                    showToast('error', 'Description must be between 2 and 255 characters.');
                    return;
                }
                if (!/^[a-zA-Z0-9\s\-\,\.\#]+$/.test(description)) {
                    e.preventDefault();
                    showToast('error', 'Description contains invalid characters.');
                    return;
                }
                if (!amount || parseFloat(amount) < 0) {
                    e.preventDefault();
                    showToast('error', 'Amount must be a non-negative number.');
                    return;
                }
                if (parseFloat(amount) > 99999999.99) {
                    e.preventDefault();
                    showToast('error', 'Amount is too large.');
                    return;
                }
                if (!income_date) {
                    e.preventDefault();
                    showToast('error', 'Date is required.');
                    return;
                }
                if (!/^\d{4}-\d{2}-\d{2}$/.test(income_date)) {
                    e.preventDefault();
                    showToast('error', 'Invalid date format.');
                    return;
                }
                const parsedDate = new Date(income_date);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (isNaN(parsedDate.getTime()) || parsedDate > today) {
                    e.preventDefault();
                    showToast('error', 'Date must be valid and not in the future.');
                    return;
                }
            });
        }

        // Client-side Validation for Update Form
        if (updateForm) {
            updateForm.addEventListener('submit', (e) => {
                const description = updateDescription.value.trim();
                const amount = updateAmount.value;
                const income_date = updateDate.value;

                if (description.length < 2 || description.length > 255) {
                    e.preventDefault();
                    showToast('error', 'Description must be between 2 and 255 characters.');
                    return;
                }
                if (!/^[a-zA-Z0-9\s\-\,\.\#]+$/.test(description)) {
                    e.preventDefault();
                    showToast('error', 'Description contains invalid characters.');
                    return;
                }
                if (!amount || parseFloat(amount) < 0) {
                    e.preventDefault();
                    showToast('error', 'Amount must be a non-negative number.');
                    return;
                }
                if (parseFloat(amount) > 99999999.99) {
                    e.preventDefault();
                    showToast('error', 'Amount is too large.');
                    return;
                }
                if (!income_date) {
                    e.preventDefault();
                    showToast('error', 'Date is required.');
                    return;
                }
                if (!/^\d{4}-\d{2}-\d{2}$/.test(income_date)) {
                    e.preventDefault();
                    showToast('error', 'Invalid date format.');
                    return;
                }
                const parsedDate = new Date(income_date);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (isNaN(parsedDate.getTime()) || parsedDate > today) {
                    e.preventDefault();
                    showToast('error', 'Date must be valid and not in the future.');
                    return;
                }
            });
        }
        <?php endif; ?>

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