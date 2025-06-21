<?php
session_start();
include '../../database/connection.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$db = db_agriloan_connect();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    try {
        // Validate inputs
        $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
        $phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING);
        $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
        $password = $_POST['password'];

        if (!preg_match('/^\+255[0-9]{9}$/', $phone_number)) {
            throw new Exception('Invalid phone number. Use +255 format.');
        }
        if (strlen($full_name) < 2 || strlen($full_name) > 100) {
            throw new Exception('Name must be 2-100 characters.');
        }
        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters.');
        }
        if (!in_array($role, ['farmer', 'approver', 'super_admin'])) {
            throw new Exception('Invalid role selected.');
        }

        // Handle national ID file upload
        $national_id = null;
        if (isset($_FILES['national_id']) && $_FILES['national_id']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'Uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_ext = pathinfo($_FILES['national_id']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            if (!move_uploaded_file($_FILES['national_id']['tmp_name'], $file_path)) {
                throw new Exception('Failed to upload ID file.');
            }
            $national_id = $file_path;
        } else {
            throw new Exception('National ID file is required.');
        }

        // Check for duplicate phone number
        $stmt = $db->prepare('SELECT user_id FROM users WHERE phone_number = ?');
        $stmt->execute([$phone_number]);
        if ($stmt->fetch()) {
            throw new Exception('Phone number already exists.');
        }

        // Store user
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare('INSERT INTO users (phone_number, full_name, national_id, password, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$phone_number, $full_name, $national_id, $hashed_password, $role]);

        // Debug: Log successful registration
        file_put_contents('debug.log', "Register: User registered - phone: $phone_number, role: $role, session_id: " . session_id() . "\n", FILE_APPEND);

        // Show success message and redirect
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Success!',
                    text: 'Registration completed! Please log in.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = 'sign-in.php';
                });
            });
        </script>";
    } catch (Exception $e) {
        file_put_contents('debug.log', "Register error: " . $e->getMessage() . "\n", FILE_APPEND);
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Error!',
                    text: '" . addslashes($e->getMessage()) . "',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <title>Register | Agri-Loan Connect</title>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />
    <link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link id="pagestyle" href="../assets/css/argon-dashboard.css?v=2.1.0" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.css">
    <script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        function convertToUppercase(input) {
            input.value = input.value.toUpperCase();
        }
    </script>
</head>
<body class="">
    <nav class="navbar navbar-expand-lg position-absolute top-0 z-index-3 w-100 shadow-none my-3 navbar-transparent mt-4">
        <div class="container">
            <a class="navbar-brand font-weight-bolder ms-lg-0 ms-3 text-white" href="../pages/dashboard.html">Agri-Loan Connect</a>
            <button class="navbar-toggler shadow-none ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#navigation" aria-controls="navigation" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon mt-2">
                    <span class="navbar-toggler-bar bar1"></span>
                    <span class="navbar-toggler-bar bar2"></span>
                    <span class="navbar-toggler-bar bar3"></span>
                </span>
            </button>
            <div class="collapse navbar-collapse" id="navigation">
                <ul class="navbar-nav mx-auto"></ul>
                <ul class="navbar-nav d-lg-block d-none"></ul>
            </div>
        </div>
    </nav>
    <main class="main-content mt-0">
        <div class="page-header align-items-start min-vh-50 pt-5 pb-11 m-3 border-radius-lg" style="background-image: url('../assets/img/agriloan-bg.jpg'); background-position: top;">
            <span class="mask bg-gradient-dark opacity-6"></span>
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-5 text-center mx-auto">
                        <h1 class="text-white mb-2 mt-5">Welcome!</h1>
                        <p class="text-lead text-white">Register now to get the loans you need for your agricultural progress</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="row mt-lg-n10 mt-md-n11 mt-n10 justify-content-center">
                <div class="col-xl-4 col-lg-5 col-md-7 mx-auto">
                    <div class="card z-index-0">
                        <div class="card-header text-center pt-4">
                            <h5>Register</h5>
                        </div>
                        <div class="card-body">
                            <form role="form" method="post" enctype="multipart/form-data" action="">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" placeholder="Full Name" name="full_name" id="full_name" oninput="convertToUppercase(this)" required>
                                </div>
                                <div class="mb-3">
                                    <label for="phone_number" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" placeholder="+255123456789" name="phone_number" id="phone_number" required>
                                </div>
                                <div class="mb-3">
                                    <label for="national_id" class="form-label">Upload ID</label>
                                    <input type="file" class="form-control" name="national_id" id="national_id" accept=".jpg,.jpeg,.png,.pdf" required>
                                </div>
                                <div class="mb-3">
                                    <label for="role" class="form-label">Choose your role</label>
                                    <select class="form-control" name="role" id="role" required>
                                        <option value="farmer">Farmer</option>
                                        <option value="approver">Approver</option>
                                        <option value="super_admin">Super Admin</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" placeholder="Password" name="password" id="password" required>
                                </div>
                                <div class="text-center">
                                    <button type="submit" class="btn bg-gradient-dark w-100 my-4 mb-2" name="submit">Sign up</button>
                                </div>
                                <p class="text-sm mt-3 mb-0">Already have an account? <a href="sign-in.php" class="text-dark font-weight-bolder">Sign in</a></p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <footer class="footer py-5">
        <div class="container">
            <div class="row">
                <div class="col-8 mx-auto text-center">
                    <a href="javascript:;" class="text-secondary me-xl-5 me-3 mb-sm-0 mb-2">About Us</a>
                    <a href="javascript:;" class="text-secondary me-xl-5 me-3 mb-sm-0 mb-2">Team</a>
                </div>
            </div>
            <div class="row">
                <div class="col-8 mx-auto text-center mt-1">
                    <div class="text-secondary text-center">
                        © <script>document.write(new Date().getFullYear())</script> Agri-Loan Connect. All Rights Reserved.
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.js"></script>
    <script>
        var win = navigator.platform.indexOf('Win') > -1;
        if (win && document.querySelector('#sidenav-scrollbar')) {
            var options = { damping: '0.5' };
            Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
        }
        function convertToUppercase(input) {
            input.value = input.value.toUpperCase();
        }
    </script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>
    <script src="../assets/js/argon-dashboard.min.js?v=2.1.0"></script>
</body>
</html>