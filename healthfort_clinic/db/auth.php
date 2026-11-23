<?php
/**
 * Shared authentication helpers for Healthfort.
 * Used by src/login.php and src/signup.php.
 *
 * Usage:  require_once __DIR__ . '/../db/conn.php';
 *         require_once __DIR__ . '/../db/auth.php';
 *         auth_start_session();
 *         auth_ensure_tables($conn);
 */

const AUTH_MAX_FAILS_PER_EMAIL = 8;    // failed sign-ins per email in the window
const AUTH_MAX_FAILS_PER_IP    = 30;   // failed sign-ins per IP in the window
const AUTH_WINDOW_MINUTES      = 15;

/** Start the session with hardened cookie settings. Call before any session use. */
function auth_start_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) {
    return;
  }
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');
  ini_set('session.use_strict_mode', '1');
  ini_set('session.use_only_cookies', '1');
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

/** Create the account tables if they do not exist yet. */
function auth_ensure_tables($conn): bool {
  $queries = [
    "CREATE TABLE IF NOT EXISTS signup_tbl (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      firstname VARCHAR(100) NOT NULL,
      middlename VARCHAR(100) NULL,
      surname VARCHAR(100) NOT NULL,
      email VARCHAR(255) NOT NULL,
      phone VARCHAR(20) NOT NULL,
      dob DATETIME NOT NULL,
      password VARCHAR(255) NOT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY signup_tbl_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS login_tbl (
      email VARCHAR(255) NOT NULL PRIMARY KEY,
      password VARCHAR(255) NOT NULL,
      role VARCHAR(20) NOT NULL DEFAULT 'admin',
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS login_attempts (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      email VARCHAR(255) NOT NULL,
      ip VARCHAR(45) NOT NULL,
      attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY login_attempts_email (email, attempted_at),
      KEY login_attempts_ip (ip, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  ];
  foreach ($queries as $query) {
    if (!mysqli_query($conn, $query)) {
      error_log('Auth table setup failed: ' . mysqli_error($conn));
      return false;
    }
  }
  return true;
}

/** Per-form CSRF token, stored in the session. */
function auth_csrf_token(string $form): string {
  $key = 'csrf_' . $form;
  if (empty($_SESSION[$key])) {
    $_SESSION[$key] = bin2hex(random_bytes(32));
  }
  return $_SESSION[$key];
}

function auth_csrf_valid(string $form, $submitted): bool {
  $key = 'csrf_' . $form;
  return is_string($submitted)
    && !empty($_SESSION[$key])
    && hash_equals($_SESSION[$key], $submitted);
}

function auth_client_ip(): string {
  // REMOTE_ADDR only: forwarded headers can be spoofed by the client.
  return substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
}

function auth_normalize_email(string $email): string {
  return strtolower(trim($email));
}

/** True when this email or IP has too many recent failed sign-ins. */
function auth_is_throttled($conn, string $email, string $ip): bool {
  $stmt = mysqli_prepare(
    $conn,
    'SELECT COALESCE(SUM(email = ?), 0) AS by_email, COALESCE(SUM(ip = ?), 0) AS by_ip
     FROM login_attempts
     WHERE attempted_at > (NOW() - INTERVAL ' . AUTH_WINDOW_MINUTES . ' MINUTE)
       AND (email = ? OR ip = ?)'
  );
  if (!$stmt) {
    error_log('Login throttle check failed: ' . mysqli_error($conn));
    return false; // fail open so a DB hiccup does not lock everyone out
  }
  mysqli_stmt_bind_param($stmt, 'ssss', $email, $ip, $email, $ip);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $row = $result ? mysqli_fetch_assoc($result) : null;
  mysqli_stmt_close($stmt);
  if (!$row) {
    return false;
  }
  return (int) $row['by_email'] >= AUTH_MAX_FAILS_PER_EMAIL
      || (int) $row['by_ip'] >= AUTH_MAX_FAILS_PER_IP;
}

function auth_record_failure($conn, string $email, string $ip): void {
  $stmt = mysqli_prepare($conn, 'INSERT INTO login_attempts (email, ip) VALUES (?, ?)');
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $email, $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }
  // Housekeeping: drop rows older than a day.
  mysqli_query($conn, 'DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
}

function auth_clear_failures($conn, string $email): void {
  $stmt = mysqli_prepare($conn, 'DELETE FROM login_attempts WHERE email = ?');
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }
}

/** Establish a fresh authenticated session. */
function auth_sign_in(string $role, string $email, ?string $firstname = null): void {
  session_regenerate_id(true);
  $_SESSION = [];
  $_SESSION['user_role']  = $role;
  $_SESSION['user_email'] = $email;
  if ($role === 'patient') {
    $_SESSION['patient_email']     = $email;
    $_SESSION['patient_firstname'] = $firstname;
  }
}

function auth_dashboard_for(string $role): string {
  return $role === 'admin' ? 'admin_side/admin_dashboard.php' : 'client_side/patient_dashboard.php';
}