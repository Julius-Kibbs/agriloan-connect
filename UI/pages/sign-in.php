<?php
session_start();
include '../../database/connection.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$mysqli = db_agriloan_connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    try {
        $phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING);
        $password = $_POST['password'];

        if (!preg_match('/^\+255[0-9]{9}$/', $phone_number)) {
            throw new Exception('Invalid phone number. Use +255 format.');
        }
        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters.');
        }

        $stmt = $mysqli->prepare('SELECT user_id, hashed_password, role FROM users WHERE phone_number = ?');
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $mysqli->error);
        }
        $stmt->bind_param('s', $phone_number);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        $stmt->bind_result($user_id, $hashed_password, $role);
        $stmt->fetch(); // Fixed: No arguments
        $stmt->close();

        if (!$user_id) {
            throw new Exception('User not found.');
        }

        if (!password_verify($password, $hashed_password)) {
            throw new Exception('Incorrect password.');
        }

        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        // Set session
        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = $role;

        // Debug: Log successful login
        file_put_contents('debug.log', "Login: User logged in - phone: $phone_number, role: $role, session_id: " . session_id() . "\n", FILE_APPEND);

        // Redirect based on role
        $redirect = $role === 'super_admin' || $role === 'approver' ? 'admin_loans.php' : 'dashboard.php';
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Success!',
                    text: 'Login successful!',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = '$redirect';
                });
            });
        </script>";
    } catch (Exception $e) {
        file_put_contents('debug.log', "Login error: " . $e->getMessage() . "\n", FILE_APPEND);
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
    <title>Sign In | Agri-Loan Connect</title>
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
        function validatePhone(input) {
            const phoneRegex = /^\+255[0-9]{9}$/;
            if (!phoneRegex.test(input.value)) {
                input.setCustomValidity('Please enter a valid phone number in +255 format (e.g., +255123456789)');
            } else {
                input.setCustomValidity('');
            }
        }
        function validatePassword(input) {
            if (input.value.length < 6) {
                input.setCustomValidity('Password must be at least 6 characters.');
            } else {
                input.setCustomValidity('');
            }
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
                        <h1 class="text-white mb-2 mt-5">Welcome Back!</h1>
                        <p class="text-lead text-white">Sign in to access your Agri-Loan Connect account</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="row mt-lg-n10 mt-md-n11 mt-n10 justify-content-center">
                <div class="col-xl-4 col-lg-5 col-md-7 mx-auto">
                    <div class="card z-index-0">
                        <div class="card-header text-center pt-4">
                            <h5>Sign In</h5>
                        </div>
                        <div class="card-body">
                            <form role="form" method="post" action="" onsubmit="return this.reportValidity()">
                                <div class="mb-3">
                                    <label for="phone_number" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control form-control-lg" placeholder="+255123456789" name="phone_number" id="phone_number" oninput="validatePhone(this)" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control form-control-lg" placeholder="Password" name="password" id="password" oninput="validatePassword(this)" minlength="6" required>
                                </div>
                                <div class="text-center">
                                    <button type="submit" class="btn bg-gradient-dark w-100 my-4 mb-2" name="submit">Sign in</button>
                                </div>
                                <p class="text-sm mt-3 mb-0">Don't have an account? <a href="sign-up.php" class="text-dark font-weight-bolder">Sign up</a></p>
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
    </script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>
    <script src="../assets/js/argon-dashboard.min.js?v=2.1.0"></script>
</body>
</html>