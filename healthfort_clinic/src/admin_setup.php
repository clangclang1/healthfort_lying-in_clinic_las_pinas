<?php
  session_start();
  require_once __DIR__ . '/../db/conn.php';
  mysqli_report(MYSQLI_REPORT_OFF);
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');

  $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
  if (!in_array($remoteAddress, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) {
    http_response_code(403);
    exit('Initial admin setup is only available from this computer.');
  }

  $createTable = "CREATE TABLE IF NOT EXISTS login_tbl (
    email VARCHAR(255) NOT NULL PRIMARY KEY,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  if (!mysqli_query($conn, $createTable)) {
    error_log('Admin account table setup failed: ' . mysqli_error($conn));
    http_response_code(500);
    exit('Admin account setup is temporarily unavailable.');
  }

  if (empty($_SESSION['admin_setup_csrf'])) {
    $_SESSION['admin_setup_csrf'] = bin2hex(random_bytes(32));
  }
  $setupMessage = '';
  $setupMessageClass = '';

  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    if (!is_string($csrf) || !hash_equals($_SESSION['admin_setup_csrf'], $csrf)) {
      $setupMessage = 'Your setup session expired. Refresh the page and try again.';
      $setupMessageClass = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
      $setupMessage = 'Enter a valid email address.';
      $setupMessageClass = 'error';
    } elseif (strlen($password) < 12) {
      $setupMessage = 'Choose a password with at least 12 characters.';
      $setupMessageClass = 'error';
    } elseif ($password !== $confirmPassword) {
      $setupMessage = 'The passwords do not match.';
      $setupMessageClass = 'error';
    } else {
      $lockResult = mysqli_query($conn, "SELECT GET_LOCK('healthfort_first_admin_setup', 10) AS acquired");
      $lock = $lockResult ? mysqli_fetch_assoc($lockResult) : null;
      if (!$lockResult) {
        error_log('Admin setup lock request failed: ' . mysqli_error($conn));
        http_response_code(500);
        $setupMessage = 'Admin account setup is temporarily unavailable.';
        $setupMessageClass = 'error';
      } elseif ((int) $lock['acquired'] !== 1) {
        $setupMessage = 'Another admin setup is in progress. Try again shortly.';
        $setupMessageClass = 'error';
      } else {
        $adminCount = mysqli_query($conn, "SELECT COUNT(*) AS total FROM login_tbl WHERE role = 'admin'");
        if ($adminCount === false) {
          error_log('Admin account count failed: ' . mysqli_error($conn));
          mysqli_query($conn, "SELECT RELEASE_LOCK('healthfort_first_admin_setup')");
          http_response_code(500);
          $setupMessage = 'Admin account setup is temporarily unavailable.';
          $setupMessageClass = 'error';
        } elseif ((int) mysqli_fetch_assoc($adminCount)['total'] > 0) {
          mysqli_query($conn, "SELECT RELEASE_LOCK('healthfort_first_admin_setup')");
          $setupMessage = 'An admin account has already been created. Sign in instead.';
          $setupMessageClass = 'error';
        } else {
          $passwordHash = password_hash($password, PASSWORD_DEFAULT);
          $insert = mysqli_prepare($conn, "INSERT INTO login_tbl (email, password, role) VALUES (?, ?, 'admin')");
          if (!$insert) {
            error_log('Admin account insert preparation failed: ' . mysqli_error($conn));
            mysqli_query($conn, "SELECT RELEASE_LOCK('healthfort_first_admin_setup')");
            http_response_code(500);
            $setupMessage = 'Admin account setup is temporarily unavailable.';
            $setupMessageClass = 'error';
          } else {
            mysqli_stmt_bind_param($insert, 'ss', $email, $passwordHash);
            if (!mysqli_stmt_execute($insert)) {
              error_log('Admin account insert failed: ' . mysqli_stmt_error($insert));
              mysqli_stmt_close($insert);
              mysqli_query($conn, "SELECT RELEASE_LOCK('healthfort_first_admin_setup')");
              $setupMessage = 'This email address is already in use.';
              $setupMessageClass = 'error';
            } else {
              mysqli_stmt_close($insert);
              mysqli_query($conn, "SELECT RELEASE_LOCK('healthfort_first_admin_setup')");
              session_regenerate_id(true);
              $_SESSION['user_role'] = 'admin';
              $_SESSION['user_email'] = $email;
              unset($_SESSION['admin_setup_csrf']);
              header('Location: admin_side/admin_dashboard.php', true, 303);
              exit;
            }
          }
        }
      }
    }
  }

  $adminCount = mysqli_query($conn, "SELECT COUNT(*) AS total FROM login_tbl WHERE role = 'admin'");
  if ($adminCount === false) {
    error_log('Admin account availability check failed: ' . mysqli_error($conn));
    http_response_code(500);
    exit('Admin account setup is temporarily unavailable.');
  }
  $adminAlreadyExists = (int) mysqli_fetch_assoc($adminCount)['total'] > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>First Admin Setup</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../style/login.css" />
</head>
<body>
  <main class="signup">
    <div class="container signup-grid">
      <section class="auth-card">
        <h1 class="auth-title"><?php echo $adminAlreadyExists ? 'Admin setup is closed' : 'Set up the first admin account'; ?></h1>
        <?php if ($adminAlreadyExists): ?>
          <p class="demo-note">An admin account already exists. Sign in to continue to the admin dashboard.</p>
          <p class="switch"><a href="login.php">Go to login</a></p>
        <?php else: ?>
          <p class="demo-note">This one-time setup is only available from this computer. Choose a password with at least 12 characters.</p>
          <form class="auth-form" method="post" action="admin_setup.php">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['admin_setup_csrf'], ENT_QUOTES, 'UTF-8'); ?>" />
            <div class="stack">
              <label class="field">Admin email address
                <input type="email" name="email" autocomplete="email" maxlength="255" required />
              </label>
              <label class="field">Password
                <input type="password" name="password" autocomplete="new-password" minlength="12" required />
              </label>
              <label class="field">Confirm password
                <input type="password" name="confirm_password" autocomplete="new-password" minlength="12" required />
              </label>
            </div>
            <button type="submit" class="btn btn-primary btn-block submit">Create admin account</button>
            <p class="form-msg<?php echo $setupMessageClass === 'error' ? ' error' : ''; ?>" role="status"><?php echo htmlspecialchars($setupMessage, ENT_QUOTES, 'UTF-8'); ?></p>
          </form>
          <p class="switch"><a href="login.php">Return to login</a></p>
        <?php endif; ?>
      </section>
    </div>
  </main>
</body>
</html>
