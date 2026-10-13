<?php



echo "<h2>Registration form reached PHP!</h2>";

echo "<h3>Data received:</h3>";

echo "<pre>";
print_r($_POST);
echo "</pre>";

// ========================================
// DATABASE CONNECTION
// ========================================

$conn = new mysqli(
    "localhost",
    "root",
    "1234",
    "lostandfound",
    4406
);


// Check database connection
if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

echo "<p>Database connection successful!</p>";


// ========================================
// GET FORM DATA
// ========================================

$name = $_POST['name'] ?? '';
$studentID = $_POST['student_id'] ?? '';
$phone = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';


// ========================================
// CHECK PASSWORD
// ========================================

if ($password !== $confirmPassword) {
    die("Password that you entered do not match.");
}


// ========================================
// HASH PASSWORD
// ========================================

$hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
);


// ========================================
// DEFAULT ROLE
// ========================================

$role = "Student";


// ========================================
// CURRENT DATE AND TIME
// ========================================

$dateRegistered = date("Y-m-d H:i:s");


// ========================================
// INSERT INTO DATABASE
// ========================================

$sql = "INSERT INTO `user`
        (Name, StudentID, Emaill, Password, Phone, Role, DateRegistered)
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);


// Check SQL statement
if (!$stmt) {
    die("SQL Prepare Error: " . $conn->error);
}


$stmt->bind_param(
    "sssssss",
    $name,
    $studentID,
    $email,
    $hashedPassword,
    $phone,
    $role,
    $dateRegistered
);


// ========================================
// EXECUTE
// ========================================

if ($stmt->execute()) {

   echo "<script>
        alert('Registration Successful! Your account has been created.');
        window.location.href = 'register.html';
    </script>";

} else {

    echo "<script>
        alert('Registration Failed! Please try again.');
        window.history.back();
    </script>";

}


// Close connection
$stmt->close();
$conn->close();

?>




