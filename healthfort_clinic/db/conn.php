<?php

// 1. Database credentials
$host = "localhost";     // The server where the database lives
$username = "root";      // Default username for local servers (like XAMPP)
$password = "";          // Default password is empty on XAMPP (use "root" for MAMP)
$dbname = "healthfort_clinic"; // Replace this with your actual database name

// 2. Create the connection
$conn = mysqli_connect($host, $username, $password, $dbname);

// 3. Check if the connection worked
if (!$conn) {
    // If it fails, stop the script and print the error
    die("Connection failed: " . mysqli_connect_error());
}

// Optional: Uncomment the line below just to test if it works!
// echo "Connected successfully!";


