<?php
session_start();
include '../../database/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mysqli = db_agriloan_connect();

    $full_name = trim($_POST['full_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = 'farmer'; // Only farmers register here

    $errors = [];
    if (empty($full_name) || strlen($full_name) < 2) {
        $errors[] = 'Full name is required and must be at least 2 characters.';
    }
    if (empty($phone_number) || !preg_match('/^\+255[0-9]{9}$/', $phone_number)) {
        $errors[] = 'Phone number must be in the format +255XXXXXXXXX.';
    }
    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    // Check if phone number exists
    $stmt = $mysqli->prepare('SELECT user_id FROM users WHERE phone_number = ?');
    $stmt->bind_param('s', $phone_number);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = 'Phone number already registered.';
    }
    $stmt->close();

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare('INSERT INTO users (full_name, phone_number, password, role, approval_status) VALUES (?, ?, ?, ?, ?)');
        $approval_status = 'pending';
        $stmt->bind_param('sssss', $full_name, $phone_number, $hashed_password, $role, $approval_status);
        if ($stmt->execute()) {
            file_put_contents('debug.log', '[' . date('Y-m-d H:i:s') . ' EAT] User registered: phone=' . $phone_number . ', status=pending, session_id=' . session_id() . PHP_EOL, FILE_APPEND);
            echo "<script>alert('Registration successful! Awaiting approval.'); window.location.href='sign-in.php';</script>";
        } else {
            $errors[] = 'Registration failed. Please try again.';
            file_put_contents('debug.log', '[' . date('Y-m-d H:i:s') . ' EAT] Registration failed: ' . $mysqli->error . ', session_id=' . session_id() . PHP_EOL, FILE_APPEND);
        }
        $stmt->close();
    }

    if (!empty($errors)) {
        echo "<script>alert('" . implode('\n', array_map('addslashes', $errors)) . "');</script>";
    }

    $mysqli->close();
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
    <link id="pagestyle" href="../assets/css/argon-dashboard.css?v=2.1.0" rel="stylesheet" />
</head>
<body class="bg-gray-100">
<div class="container position-sticky z-index-sticky top-0">
    <div class="row">
        <div class="col-12">
            <nav class="navbar navbar-expand-lg blur border-radius-lg top-0 z-index-3 shadow position-absolute mt-4 py-2 start-0 end-0 mx-4">
                <div class="container-fluid">
                    <a class="navbar-brand font-weight-bolder ms-lg-0 ms-3" href="index.php">
                        Agri-Loan Connect
                    </a>
                </div>
            </nav>
        </div>
    </div>
</div>
<main class="main-content mt-0">
    <section>
        <div class="page-header min-vh-100">
            <div class="container">
                <div class="row">
                    <div class="col-xl-4 col-lg-5 col-md-7 d-flex flex-column mx-lg-0 mx-auto">
                        <div class="card card-plain">
                            <div class="card-header pb-0 text-left">
                                <h4 class="font-weight-bolder">Sign Up</h4>
                                <p class="mb-0">Enter your details to register as a farmer</p>
                            </div>
                            <div class="card-body">
                                <form role="form" method="POST" action="register.php">
                                    <div class="mb-3">
                                        <input type="text" class="form-control form-control-lg" placeholder="Full Name" name="full_name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <input type="text" class="form-control form-control-lg" placeholder="Phone Number (+255XXXXXXXXX)" name="phone_number" value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <input type="password" class="form-control form-control-lg" placeholder="Password" name="password">
                                    </div>
                                    <div class="mb-3">
                                        <input type="password" class="form-control form-control-lg" placeholder="Confirm Password" name="confirm_password">
                                    </div>
                                    <div class="text-center">
                                        <button type="submit" class="btn btn-lg btn-primary btn-lg w-100 mt-4 mb-0">Sign Up</button>
                                    </div>
                                </form>
                            </div>
                            <div class="card-footer text-center pt-0 px-lg-2 px-1">
                                <p class="mb-4 text-sm mx-auto">
                                    Already have an account?
                                    <a href="sign-in.php" class="text-primary text-gradient font-weight-bold">Sign in</a>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 d-lg-flex d-none h-100 my-auto pe-0 position-absolute top-0 end-0 text-center justify-content-center flex-column">
                        <div class="position-relative bg-gradient-primary h-100 m-3 px-7 border-radius-lg d-flex flex-column justify-content-center">
                            <img src="../assets/img/shapes/pattern-lines.svg" alt="pattern-lines" class="position-absolute opacity-4 start-0">
                            <div class="position-relative">
                                <img class="max-width-500 w-100 position-relative z-index-2" src="../assets/img/illustrations/sign-up.png">
                            </div>
                            <h4 class="mt-5 text-white font-weight-bolder">"Empowering Tanzanian Farmers"</h4>
                            <p class="text-white">Join Agri-Loan Connect to access microloans tailored for your agricultural needs.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<script src="../assets/js/core/popper.min.js"></script>
<script src="../assets/js/core/bootstrap.min.js"></script>
<script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
<script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
<script src="../assets/js/argon-dashboard.min.js?v=2.1.0"></script>
</body>
</html>