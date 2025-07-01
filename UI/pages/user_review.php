<?php
session_start();
include '../../database/connection.php';
include 'definition.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$activeUserStatus = USER_STATUS['ACTIVE'];
$pendingUserStatus = USER_STATUS['PENDING'];
$suspendedUserStatus = USER_STATUS['SUSPENDED'];
$deletedUserStatus = USER_STATUS['DELETED'];
$blockedUserStatus = USER_STATUS['BLOCKED'];
$deactivatedUserStatus = USER_STATUS['DEACTIVATED'];
$archivedUserStatus = USER_STATUS['ARCHIVED'];
$rejectedUserStatus = USER_STATUS['REJECTED'];

$mysqli = db_agriloan_connect();

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id']))
    $validActions = ['approve' => 'approved', 'reject' => 'rejected'];
    $postAction = $_POST['action'];

    if (!array_key_exists($postAction, $validActions)) {
        exit('Invalid action.');
    }

    $user_id = (int)$_POST['user_id'];
    $actionStatus = $validActions[$postAction];

    // Assuming $pendingUserStatus = 'pending';
    $stmt = $mysqli->prepare('UPDATE users SET userStatus = ? WHERE user_id = ? AND userStatus = ?');
    if (!$stmt) {
        error_log("Prepare failed: " . $mysqli->error);
        exit("Database error.");
    }

    $stmt->bind_param('sis', $actionStatus, $user_id, $pendingUserStatus);

    if ($stmt->execute()) {
        file_put_contents('debug.log', '[' . date('Y-m-d H:i:s') . ' EAT] User ' . $actionStatus . ': user_id=' . $user_id . ', by=' . $_SESSION['user_id'] . ', session_id=' . session_id() . PHP_EOL, FILE_APPEND);
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Success!',
                text: 'User " . ucfirst($actionStatus) . " successfully.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = 'user_review.php';
            });
        });
    </script>";
    } else {
        file_put_contents('debug.log', '[' . date('Y-m-d H:i:s') . ' EAT] User review failed: ' . $stmt->error . ', session_id=' . session_id() . PHP_EOL, FILE_APPEND);
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Error!',
                text: 'Failed to process user review. Please try again.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        });
    </script>";
    }

    $stmt->close();
}


// Fetch pending users
$stmt = $mysqli->prepare('SELECT user_id, full_name, phone_number, created_at FROM users WHERE userStatus = ? ORDER BY created_at DESC');
$stmt->bind_param('s', $pendingUserStatus);
$stmt->execute();
$result = $stmt->get_result();
$pending_users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$mysqli->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <title>User Review | Agri-Loan Connect</title>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />
    <link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link id="pagestyle" href="../assets/css/argon-dashboard.css?v=2.1.0" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#usersTable').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                pageLength: 10
            });
        });
    </script>
</head>
<body class="g-sidenav-show bg-gray-100">
<div class="min-height-300 bg-dark position-absolute w-100"></div>
<aside class="sidenav bg-white navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start ms-4" id="sidenav-main">
    <div class="sidenav-header">
        <i class="fas fa-times p-3 cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
        <a class="navbar-brand m-0" href="dashboard.php">
            <img src="../assets/img/logo-ct-dark.png" width="26px" height="26px" class="navbar-brand-img h-100" alt="main_logo">
            <span class="ms-1 font-weight-bold">Agri-Loan Connect</span>
        </a>
    </div>
    <hr class="horizontal dark mt-0">
    <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="ni ni-tv-2 text-dark text-sm opacity-10"></i>
                    </div>
                    <span class="nav-link-text ms-1">Dashboard</span>
                </a>
            </li>
            <?php if (in_array($_SESSION['role'], ['farmer', 'super_admin'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="loan_application.php">
                        <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                            <i class="ni ni-calendar-grid-58 text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text ms-1">Loan Application</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if (in_array($_SESSION['role'], ['approver', 'super_admin'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="loan_review.php">
                        <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                            <i class="ni ni-settings text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text ms-1">Loan Review</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="user_review.php">
                        <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                            <i class="ni ni-single-02 text-dark text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text ms-1">User Review</span>
                    </a>
                </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" href="loan_status.php">
                    <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="ni ni-settings text-dark text-sm opacity-10"></i>
                    </div>
                    <span class="nav-link-text ms-1">Loan Status</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="contact.php">
                    <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="ni ni-credit-card text-dark text-sm opacity-10"></i>
                    </div>
                    <span class="nav-link-text ms-1">Contact</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="profile.php">
                    <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="ni ni-single-02 text-dark text-sm opacity-10"></i>
                    </div>
                    <span class="nav-link-text ms-1">Profile</span>
                </a>
            </li>
        </ul>
    </div>
</aside>
<main class="main-content position-relative border-radius-lg">
    <nav class="navbar navbar-main navbar-expand-lg px-0 mx-4 shadow-none border-radius-xl" id="navbarBlur" data-scroll="false">
        <div class="container-fluid py-1 px-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                    <li class="breadcrumb-item text-sm"><a class="opacity-5 text-white" href="javascript:;">Pages</a></li>
                    <li class="breadcrumb-item text-sm text-white active" aria-current="page">User Review</li>
                </ol>
                <h6 class="font-weight-bolder text-white mb-0">User Review</h6>
            </nav>
            <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                <div class="ms-md-auto pe-md-3 d-flex align-items-center">
                    <div class="input-group">
                        <span class="input-group-text text-body"><i class="fas fa-search" aria-hidden="true"></i></span>
                        <input type="text" class="form-control" placeholder="Type here...">
                    </div>
                </div>
                <ul class="navbar-nav justify-content-end">
                    <li class="nav-item d-flex align-items-center">
                        <a href="index.php" class="nav-link text-white font-weight-bold px-0">
                            <i class="fa fa-user me-sm-1"></i>
                            <span class="d-sm-inline d-none">Sign Out</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header pb-0 p-3">
                        <h6 class="mb-0">Pending User Registrations</h6>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($pending_users)): ?>
                            <p class="text-center">No pending user registrations.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="usersTable" class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th>User ID</th>
                                        <th>Full Name</th>
                                        <th>Phone Number</th>
                                        <th>Registered On</th>
                                        <th>Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($pending_users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone_number']); ?></td>
                                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($user['created_at']))); ?></td>
                                            <td>
                                                <form method="POST" action="user_review.php" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                                                    <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="../assets/js/core/popper.min.js"></script>
<script src="../assets/js/core/bootstrap.min.js"></script>
<script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
<script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.js"></script>
<script src="../assets/js/argon-dashboard.min.js?v=2.1.0"></script>
</body>
</html>