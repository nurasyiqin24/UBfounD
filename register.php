<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: register.html");
    exit;
}

function getUserColumn(mysqli $conn, array $candidates): string
{
    $result = $conn->query("SHOW COLUMNS FROM `user`");
    if ($result === false) {
        return $candidates[0];
    }

    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row["Field"];
    }

    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }

    return $candidates[0];
}

$conn = new mysqli(
    "localhost",
    "root",
    "1234",
    "lostandfound",
    4406
);

if ($conn->connect_error) {
    http_response_code(500);
    header("Location: register.html?error=" . urlencode("Database connection failed. Please try again later."));
    exit;
}

$conn->set_charset("utf8mb4");

$name = trim($_POST["name"] ?? "");
$studentID = trim($_POST["student_id"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirm_password"] ?? "";

if ($name === "" || $studentID === "" || $phone === "" || $email === "" || $password === "" || $confirmPassword === "") {
    header("Location: register.html?error=" . urlencode("All fields are required."));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: register.html?error=" . urlencode("Please enter a valid email address."));
    exit;
}

if ($password !== $confirmPassword) {
    header("Location: register.html?error=" . urlencode("Passwords do not match."));
    exit;
}

if (strlen($password) < 8) {
    header("Location: register.html?error=" . urlencode("Password must be at least 8 characters long."));
    exit;
}

$emailColumn = getUserColumn($conn, ["Email", "Emaill"]);
$studentColumn = getUserColumn($conn, ["StudentID", "StudentId", "student_id"]);

$duplicateStmt = $conn->prepare("SELECT 1 FROM `user` WHERE `{$emailColumn}` = ? OR `{$studentColumn}` = ? LIMIT 1");
if ($duplicateStmt === false) {
    $conn->close();
    header("Location: register.html?error=" . urlencode("Unable to validate your account details."));
    exit;
}

$duplicateStmt->bind_param("ss", $email, $studentID);
$duplicateStmt->execute();
$duplicateResult = $duplicateStmt->get_result();
$duplicateStmt->close();

if ($duplicateResult->num_rows > 0) {
    $conn->close();
    header("Location: register.html?error=" . urlencode("An account with this email or student ID already exists."));
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$role = "Student";
$dateRegistered = date("Y-m-d H:i:s");

$insertSql = "INSERT INTO `user` (`Name`, `StudentID`, `{$emailColumn}`, `Password`, `Phone`, `Role`, `DateRegistered`) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insertSql);

if ($stmt === false) {
    $conn->close();
    header("Location: register.html?error=" . urlencode("Registration failed. Please try again later."));
    exit;
}

$stmt->bind_param("sssssss", $name, $studentID, $email, $hashedPassword, $phone, $role, $dateRegistered);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    header("Location: login.html?success=" . urlencode("Registration successful. Please log in."));
    exit;
}

$stmt->close();
$conn->close();
header("Location: register.html?error=" . urlencode("Registration failed. Please try again."));
exit;

?>



