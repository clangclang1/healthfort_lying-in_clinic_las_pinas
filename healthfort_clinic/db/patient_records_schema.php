<?php
function ensurePatientRecordTables($conn) {
  $queries = [
    "CREATE TABLE IF NOT EXISTS patient_appointments (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      patient_email VARCHAR(255) NOT NULL,
      service VARCHAR(100) NOT NULL,
      appointment_date DATE NOT NULL,
      appointment_time TIME NOT NULL,
      doctor VARCHAR(100) NOT NULL,
      notes TEXT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'Waiting',
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY patient_appointments_patient (patient_email),
      KEY patient_appointments_slot (appointment_date, appointment_time, doctor, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS patient_pregnancy_profiles (
      patient_email VARCHAR(255) NOT NULL PRIMARY KEY,
      estimated_due_date DATE NOT NULL,
      next_visit DATE NULL,
      assigned_provider VARCHAR(100) NOT NULL,
      follow_up_required TINYINT(1) NOT NULL DEFAULT 0,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS patient_prenatal_visits (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      patient_email VARCHAR(255) NOT NULL,
      visit_date DATE NOT NULL,
      weeks TINYINT UNSIGNED NOT NULL,
      blood_pressure VARCHAR(7) NOT NULL,
      weight DECIMAL(5,1) NOT NULL,
      fetal_heart_rate SMALLINT UNSIGNED NOT NULL,
      notes TEXT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY patient_prenatal_visits_patient_date (patient_email, visit_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  ];

  foreach ($queries as $query) {
    if (!mysqli_query($conn, $query)) {
      error_log('Patient record table setup failed: ' . mysqli_error($conn));
      return false;
    }
  }

  return true;
}
