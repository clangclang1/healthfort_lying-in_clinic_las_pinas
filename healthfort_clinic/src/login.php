<?php
  require_once __DIR__ . '/../db/conn.php';

  mysqli_report(MYSQLI_REPORT_OFF);
  session_start();
  $loginMessage = '';
  $loginMessageClass = '';
  $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
  $isLocalRequest = in_array($remoteAddress, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
  $showAdminSetup = false;
  if ($isLocalRequest) {
    $loginTable = mysqli_query($conn, "SHOW TABLES LIKE 'login_tbl'");
    if ($loginTable === false) {
      error_log('Admin setup availability check failed: ' . mysqli_error($conn));
    } elseif (mysqli_num_rows($loginTable) === 0) {
      $showAdminSetup = true;
    } else {
      $adminCount = mysqli_query($conn, "SELECT COUNT(*) AS total FROM login_tbl WHERE role = 'admin'");
      if ($adminCount === false) {
        error_log('Admin account count failed: ' . mysqli_error($conn));
      } else {
        $showAdminSetup = (int) mysqli_fetch_assoc($adminCount)['total'] === 0;
      }
    }
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!in_array($role, ['patient', 'admin'], true)) {
      $loginMessage = 'Select a valid account type.';
      $loginMessageClass = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
      $loginMessage = 'Enter a valid email address and password.';
      $loginMessageClass = 'error';
    } else {
      $query = $role === 'admin'
        ? "SELECT NULL AS firstname, password FROM login_tbl WHERE email = ? AND role = 'admin' LIMIT 1"
        : 'SELECT firstname, password FROM signup_tbl WHERE email = ? LIMIT 1';
      $stmt = mysqli_prepare($conn, $query);
      if (!$stmt) {
        error_log('Login lookup preparation failed: ' . mysqli_error($conn));
        http_response_code(500);
        $loginMessage = 'We could not process your sign-in right now.';
        $loginMessageClass = 'error';
      } else {
        mysqli_stmt_bind_param($stmt, 's', $email);
        if (!mysqli_stmt_execute($stmt)) {
          error_log('Login lookup failed: ' . mysqli_stmt_error($stmt));
          mysqli_stmt_close($stmt);
          http_response_code(500);
          $loginMessage = 'We could not process your sign-in right now.';
          $loginMessageClass = 'error';
        } else {
          $result = mysqli_stmt_get_result($stmt);
          if ($result === false) {
            error_log('Login result retrieval failed: ' . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            http_response_code(500);
            $loginMessage = 'We could not process your sign-in right now.';
            $loginMessageClass = 'error';
          } else {
            $account = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$account || !password_verify($password, $account['password'])) {
              $loginMessage = 'The email or password is incorrect.';
              $loginMessageClass = 'error';
            } else {
              session_regenerate_id(true);
              $_SESSION['user_role'] = $role;
              $_SESSION['user_email'] = $email;
              if ($role === 'patient') {
                $_SESSION['patient_email'] = $email;
                $_SESSION['patient_firstname'] = $account['firstname'];
              }
              $destination = $role === 'admin'
                ? 'admin_side/admin_dashboard.php'
                : 'client_side/patient_dashboard.php';
              header('Location: ' . $destination, true, 303);
              exit;
            }
          }
        }
      }
    }
  }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>Login </title>
  <link rel="icon" type="image/png" sizes="64x64" href="data:image/png;base64,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" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../style/login.css">
</head>
<body>

  <main class="signup auth-page">
    <div class="container signup-grid">

      <div class="signup-main">
        <div class="auth-card">

          <h1 class="auth-title" id="auth-title">Welcome back</h1>

          <section class="auth-panel" id="panel-login" role="tabpanel" aria-labelledby="tab-login">
            <form class="auth-form" id="login-form" method="post" action="login.php" novalidate>
              <div class="stack">
                <label class="field">Sign in as
                  <select name="role" required>
                    <option value="patient">Patient</option>
                    <option value="admin">Admin</option>
                  </select>
                </label>
                <label class="field">Email address
                  <input type="email" name="email" autocomplete="email" required />
                </label>
                <label class="field">Password
                  <input type="password" name="password" autocomplete="current-password" required />
                </label>
                <label class="terms">
                  <input type="checkbox" name="remember" />
                  <span>Keep me signed in on this device</span>
                </label>
              </div>
              <button type="submit" class="btn btn-primary btn-block submit">Sign in</button>
              <p class="form-msg<?php echo $loginMessageClass === 'error' ? ' error' : ''; ?>" id="login-msg" role="status"><?php echo htmlspecialchars($loginMessage, ENT_QUOTES, 'UTF-8'); ?></p>
              <p class="switch">New to Healthfort? <a href="signup.php">Create an account</a></p>
              <?php if ($showAdminSetup): ?>
                <p class="switch"><a href="admin_setup.php">Set up the first admin account</a></p>
              <?php endif; ?>
              <p class="demo-note">Sign in with the email and password you registered with.</p>
            </form>
          </section>

          <section class="auth-panel" id="panel-signup" role="tabpanel" aria-labelledby="tab-signup" hidden>
            <form class="auth-form" id="signup-form">

          
        <div class="form-grid">
          <label class="field">First name
            <input type="text" name="firstname" autocomplete="given-name" required />
          </label>

          <label class="field">Middle name <span class="hint">(optional)</span>
            <input type="text" name="middlename" autocomplete="additional-name" />
          </label>

          <label class="field full">Surname
            <input type="text" name="surname" autocomplete="family-name" required />
          </label>

          <div class="field email-field">
            <span id="email-label">Email address</span>
            <div class="email-row" role="group" aria-labelledby="email-label">
              <input type="text" id="email-user" name="emailuser" placeholder="yourname" autocomplete="username" pattern="[A-Za-z0-9._%+-]+" title="Use letters, numbers, dots, or underscores before the @" required />
              <select id="email-provider" aria-label="Email provider" required>
                <option value="gmail.com" selected>@gmail.com</option>
                <option value="yahoo.com">@yahoo.com</option>
                <option value="outlook.com">@outlook.com</option>
                <option value="hotmail.com">@hotmail.com</option>
                <option value="icloud.com">@icloud.com</option>
              </select>
            </div>
            <input type="hidden" name="email" id="email-field" />
          </div>

          <label class="field">Mobile number
            <input type="tel" name="phone" autocomplete="tel" placeholder="09XX XXX XXXX" pattern="09[0-9]{2} ?[0-9]{3} ?[0-9]{4}" title="Use an 11-digit mobile number, for example 0966 726 0918" required />
          </label>

          <div class="field dob-field">
            <span id="dob-label">Date of birth</span>
            <div class="dob-selects" role="group" aria-labelledby="dob-label">
              <select id="dob-month" aria-label="Month">
                <option value="">Month</option>
                <option value="1">January</option>
                <option value="2">February</option>
                <option value="3">March</option>
                <option value="4">April</option>
                <option value="5">May</option>
                <option value="6">June</option>
                <option value="7">July</option>
                <option value="8">August</option>
                <option value="9">September</option>
                <option value="10">October</option>
                <option value="11">November</option>
                <option value="12">December</option>
              </select>
              <select id="dob-day" aria-label="Day" data-placeholder="Day">
                <option value="">Day</option>
              </select>
              <select id="dob-year" aria-label="Year" data-placeholder="Year">
                <option value="">Year</option>
              </select>
            </div>
            <input type="hidden" name="dob" id="dob-field" />
          </div>

          <label class="field">Password
            <input type="password" name="password" autocomplete="new-password" minlength="8" required />
            <span class="hint">At least 8 characters.</span>
          </label>

          <label class="field full">Confirm password
            <input type="password" name="confirm" autocomplete="new-password" required />
          </label>

          <label class="terms full">
            <input type="checkbox" name="terms" required />
            <span>I agree to the Terms of Use and Privacy Policy of Healthfort Lying-In Clinic.</span>
          </label>
        </div>
        <br />
        <button type="submit" class="btn btn-primary btn-block submit">Create account</button>
        <br />
        <p class="form-msg" id="form-msg" role="status"></p>
      </form>
            <p class="switch">Already have an account? <a href="#login">Login</a></p>
          </section>
        </div>
      </div>

    </div>
  </main>

  <script>
    (function () {
      var form = document.getElementById("signup-form");
      var msg = document.getElementById("form-msg");
      var password = form.elements["password"];
      var confirm = form.elements["confirm"];

      function checkMatch() {
        confirm.setCustomValidity(confirm.value && confirm.value !== password.value ? "Passwords do not match." : "");
      }

      password.addEventListener("input", checkMatch);
      confirm.addEventListener("input", checkMatch);

      form.addEventListener("submit", function (event) {
        event.preventDefault();
        checkMatch();
        if (!document.getElementById("email-field").value) {
          msg.className = "form-msg error";
          msg.textContent = "Please complete your email address.";
          return;
        }
        if (!document.getElementById("dob-field").value) {
          msg.className = "form-msg error";
          msg.textContent = "Please choose your date of birth.";
          return;
        }
        if (!form.checkValidity()) {
          form.reportValidity();
          msg.className = "form-msg error";
          msg.textContent = "Please check the highlighted fields.";
          return;
        }
        msg.className = "form-msg";
        msg.textContent = "Thank you! Your account request has been received.";
        form.reset();
      });
    })();
  </script>
  <script>
    (function () {
      var YEAR_MIN = 1920;
      var YEAR_MAX = new Date().getFullYear();
      var form = document.getElementById("signup-form");
      var hidden = document.getElementById("dob-field");
      var month = document.getElementById("dob-month");
      var day = document.getElementById("dob-day");
      var year = document.getElementById("dob-year");

      function pad(n) {
        return String(n).padStart(2, "0");
      }

      function daysInMonth() {
        if (!month.value) return 31;
        return new Date(year.value || 2000, parseInt(month.value, 10), 0).getDate();
      }

      // Rebuild a dropdown's choices, keeping the current choice when it is still valid.
      function setOptions(select, values) {
        var keep = select.value;
        var placeholder = select.getAttribute("data-placeholder") || select.options[0].textContent;
        select.innerHTML = "";
        var first = document.createElement("option");
        first.value = "";
        first.textContent = placeholder;
        select.appendChild(first);
        values.forEach(function (v) {
          var opt = document.createElement("option");
          opt.value = String(v);
          opt.textContent = String(v);
          select.appendChild(opt);
        });
        select.value = keep;
        if (select.value !== keep) select.value = "";
      }

      function refreshDays() {
        var n = daysInMonth();
        var values = [];
        for (var d = 1; d <= n; d++) values.push(d);
        setOptions(day, values);
      }

      function update() {
        refreshDays();
        if (month.value && day.value && year.value) {
          hidden.value = year.value + "-" + pad(month.value) + "-" + pad(day.value);
        } else {
          hidden.value = "";
        }
      }

      var years = [];
      for (var y = YEAR_MAX; y >= YEAR_MIN; y--) years.push(y);
      setOptions(year, years);
      refreshDays();

      month.addEventListener("change", update);
      day.addEventListener("change", update);
      year.addEventListener("change", update);

      form.addEventListener("reset", function () {
        setTimeout(function () {
          refreshDays();
          hidden.value = "";
        }, 0);
      });
    })();
  </script>
  <script>
    (function () {
      var form = document.getElementById("signup-form");
      var hidden = document.getElementById("email-field");
      var user = document.getElementById("email-user");
      var provider = document.getElementById("email-provider");

      function sync() {
        var name = user.value.trim();
        hidden.value = name ? name + "@" + provider.value : "";
      }

      user.addEventListener("input", sync);
      provider.addEventListener("change", sync);
      form.addEventListener("reset", function () { setTimeout(sync, 0); });
      sync();
    })();
  </script>
  <script>
    (function () {
      var panels = {
        login: document.getElementById("panel-login"),
        signup: document.getElementById("panel-signup")
      };
      var title = document.getElementById("auth-title");
      var TITLES = { login: "Welcome back", signup: "Create your account" };

      function show(which) {
        panels.login.hidden = which !== "login";
        panels.signup.hidden = which !== "signup";
        title.textContent = TITLES[which];
        if (window.history && window.history.replaceState) {
          window.history.replaceState(null, "", "#" + which);
        }
      }

      var loginForm = document.getElementById("login-form");
      var loginMsg = document.getElementById("login-msg");

      loginForm.addEventListener("submit", function (e) {
        if (!loginForm.checkValidity()) {
          e.preventDefault();
          loginForm.reportValidity();
          loginMsg.className = "form-msg error";
          loginMsg.textContent = "Please enter your email and password.";
          return;
        }
        loginMsg.className = "form-msg";
        loginMsg.textContent = "Signing you in...";
      });

      document.getElementById("forgot").addEventListener("click", function (e) {
        e.preventDefault();
        loginMsg.className = "form-msg";
        loginMsg.textContent = "Password reset is not set up yet. Please contact the clinic.";
      });

      show(window.location.hash === "#signup" ? "signup" : "login");
      window.addEventListener("hashchange", function () {
        show(window.location.hash === "#signup" ? "signup" : "login");
      });
    })();
  </script>

</body>
</html>
