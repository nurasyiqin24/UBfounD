<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.html");
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
    header("Location: login.html?error=" . urlencode("Database connection failed. Please try again later."));
    exit;
}

$conn->set_charset("utf8mb4");

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") {
    header("Location: login.html?error=" . urlencode("Email and password are required."));
    exit;
}

$emailColumn = getUserColumn($conn, ["Email", "Emaill"]);
$stmt = $conn->prepare("SELECT * FROM `user` WHERE `{$emailColumn}` = ? LIMIT 1");

if ($stmt === false) {
    $conn->close();
    header("Location: login.html?error=" . urlencode("Unable to process login request."));
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

if ($user && isset($user["Password"]) && password_verify($password, $user["Password"])) {
    $_SESSION["user_id"] = $user["UserID"] ?? $user["id"] ?? null;
    $_SESSION["user_name"] = $user["Name"] ?? "";
    $_SESSION["user_email"] = $user[$emailColumn] ?? $email;
    $_SESSION["user_role"] = $user["Role"] ?? "Student";

    header("Location: index.html");
    exit;
}

header("Location: login.html?error=" . urlencode("Invalid email or password."));
exit;
