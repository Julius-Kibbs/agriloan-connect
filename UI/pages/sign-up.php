<!--
=========================================================
* Argon Dashboard 3 - v2.1.0
=========================================================

* Product Page: https://www.creative-tim.com/product/argon-dashboard
* Copyright 2024 Creative Tim (https://www.creative-tim.com)
* Licensed under MIT (https://www.creative-tim.com/license)
* Coded by Creative Tim

=========================================================

* The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
-->
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="../assets/img/favicon.png">
  <title>
    Register | Agri-Loan Connect
  </title>
  <!--     Fonts and icons     -->
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />
  <!-- Nucleo Icons -->
  <link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-icons.css" rel="stylesheet" />
  <link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-svg.css" rel="stylesheet" />
  <!-- Font Awesome Icons -->
  <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
  <!-- CSS Files -->
  <link id="pagestyle" href="../assets/css/argon-dashboard.css?v=2.1.0" rel="stylesheet" />
</head>

<body class="">
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg position-absolute top-0 z-index-3 w-100 shadow-none my-3 navbar-transparent mt-4">
    <div class="container">

    <?php

        session_start();

include 'php/config.php'; // hapa  unaweka Header yako ya Html

// Weka hii kitu kwenye Header yako kwa Ajiri ya Response
// <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.css">

// <!-- JavaScript code prevents the page from reloading when refreshed -->
//     <script>
//         if (window.history.replaceState) {
//             window.history.replaceState(null, null, window.location.href);
//         }
//     </script>


if (isset($_POST['submit'])) {
    // receive all input values from the form

   
    $fullname  = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phoneno      = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $national_id      = mysqli_real_escape_string($conn, $_POST['national_id']);
    $role      = mysqli_real_escape_string($conn, $_POST['role']);
    $password      = mysqli_real_escape_string($conn, $_POST['password']);

        // Check if username exists in the database
        $phonenoCheckQuery = "SELECT * FROM `users` WHERE `phoneno` = '$phone_number'"; // Hapa unaweza check kitu chochote 
        $result = mysqli_query($conn, $phonenoCheckQuery);

        if (mysqli_num_rows($result) > 0) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Phone Number already exists. Please choose a different phone number.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
                </script>";
        } else{
            $hashedpass = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (fullname, phonenumber, national_id, role,  password)
                        VALUES('$fullname', '$phone_number', '$national_id', '$role', '$hashedpass', '$password')";
            if (mysqli_query($conn, $query)) {
                echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Success!',
                        text: 'User Successfully Created',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                });
                </script>";
            } else {
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: 'Error!',
                            text: 'Ooops There's an Error',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
                </script>";
            }
        }
      }
    
       ?>


      <a class="navbar-brand font-weight-bolder ms-lg-0 ms-3 text-white" href="../pages/dashboard.html">
        Agri-Loan Connect
      </a>
      <button class="navbar-toggler shadow-none ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#navigation" aria-controls="navigation" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon mt-2">
          <span class="navbar-toggler-bar bar1"></span>
          <span class="navbar-toggler-bar bar2"></span>
          <span class="navbar-toggler-bar bar3"></span>
        </span>
      </button>
      <div class="collapse navbar-collapse" id="navigation">
        <ul class="navbar-nav mx-auto">
          <li class="nav-item">
          </li>
        </ul>
        <ul class="navbar-nav d-lg-block d-none">
          <li class="nav-item">
          </li>
        </ul>
      </div>
    </div>
  </nav>
  <!-- End Navbar -->
  <main class="main-content  mt-0">
    <div class="page-header align-items-start min-vh-50 pt-5 pb-11 m-3 border-radius-lg" style="background-image: url('C:\Users\hp\OneDrive\Desktop\AgriLoan Connect\agriloan-connect\UI\pages\agriloan pic 3.jpeg'); background-position: top;">
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
            <div class="row px-xl-5 px-sm-4 px-3">
              <div class="mt-2 position-relative text-center">
              </div>
            </div>
            <div class="card-body">
              <form role="form" method="post" enctype="multipart/form-data" action="">

                <div class="mb-3">
                   <label for ="fullname" class="form-label">Full Name</label>
                  <input type="text" class="form-control" placeholder="Full Name" name="fullname" aria-label="Full Name" required>
                </div>

                <div class="mb-3">
                  <label for ="phoneno" class="form-label">Phone Number</label>
                  <input type="text" class="form-control" placeholder="Phone Number" name="phoneno" aria-label="Phone Number" required>
                </div>

                <div class ="mb-3">
                <label for ="id" class="form-label">Upload ID</label> 
                <input type="file" class ="form-control" aria-placeholder="Upload your file" name="national_id" required class="box">
                </div>

                 <div class ="mb-3">
                <label for ="role" class="form-label">Choose your role</label>
                <select class="form-control" aria-placeholder="Choose role" id = "role" name="role" required>
                    <option value="farmer">Farmer</option>
                    <option value="approver">Approver</option>
                    <option value="super_admin">Admin</option>
                </select>
                </div>

                <div class="mb-3">
                <label for ="password" class="form-label">Password</label>
                  <input type="password" class="form-control" placeholder="Password" name="password" aria-label="Password" required>
                </div>

                <div class="text-center">
                  <button type="submit" class="btn bg-gradient-dark w-100 my-4 mb-2" name="submit">Sign up</button>
                </div>

                <p class="text-sm mt-3 mb-0">Already have an account? <a href="sign-in.html" class="text-dark font-weight-bolder">Sign in</a></p>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
  <!-- -------- START FOOTER 3 w/ COMPANY DESCRIPTION WITH LINKS & SOCIAL ICONS & COPYRIGHT ------- -->
  <footer class="footer py-5">
    <div class="container">
      <div class="row">
          <a href="javascript:;" target="_blank" class="text-secondary me-xl-5 me-3 mb-sm-0 mb-2">
            About Us
          </a>
          <a href="javascript:;" target="_blank" class="text-secondary me-xl-5 me-3 mb-sm-0 mb-2">
            Team
          </a>
        </div>
      </div>
      <div class="row">
        <div class="col-8 mx-auto text-center mt-1">
          <div class="text-secondary text-center">
            © <script>
              document.write(new Date().getFullYear())
            </script> Agri-Loan Connect. All Rights Reserved.
          </div>
        </div>
      </div>
    </div>
  </footer>
  <!-- -------- END FOOTER 3 w/ COMPANY DESCRIPTION WITH LINKS & SOCIAL ICONS & COPYRIGHT ------- -->
  <!--   Core JS Files   -->
  <script src="../assets/js/core/popper.min.js"></script>
  <script src="../assets/js/core/bootstrap.min.js"></script>
  <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
  <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
  <script>
    var win = navigator.platform.indexOf('Win') > -1;
    if (win && document.querySelector('#sidenav-scrollbar')) {
      var options = {
        damping: '0.5'
      }
      Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
    }
  </script>
  <!-- Github buttons -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>
  <!-- Control Center for Soft Dashboard: parallax effects, scripts for the example pages etc -->
  <script src="../assets/js/argon-dashboard.min.js?v=2.1.0"></script>
  <?php  ?>
</body>

</html>