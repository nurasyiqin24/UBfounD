
<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.html");
    exit;
}

$conn = new mysqli(
    "localhost",
    "root",
    "1234",
    "lostandfound",
    4406
);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit("Database connection failed. Please try again later.");
}

$conn->set_charset("utf8mb4");

// Read the login form values.
$identifier = trim($_POST["identifier"] ?? "");
$password = $_POST["password"] ?? "";

// Validate the submitted fields.
if ($identifier === "" || $password === "") {
    $conn->close();
    header("Location: login.html?error=" .
        urlencode("Please enter your email or Student ID and password."));
    exit;
}

// Read the available columns from the user table.
$result = $conn->query("SHOW COLUMNS FROM `user`");

if ($result === false) {
    $conn->close();
    error_log("Unable to inspect user table columns.");
    http_response_code(500);
    exit("Unable to process login request.");
}

$columns = [];

while ($row = $result->fetch_assoc()) {
    $columns[] = $row["Field"];
}

$result->free();

// Find the email column.
$emailColumn = null;

foreach (["Email", "Emaill"] as $candidate) {
    if (in_array($candidate, $columns, true)) {
        $emailColumn = $candidate;
        break;
    }
}

// Find the Student ID column.
$studentColumn = null;

foreach (["StudentID", "StudentId", "student_id"] as $candidate) {
    if (in_array($candidate, $columns, true)) {
        $studentColumn = $candidate;
        break;
    }
}

if ($emailColumn === null || $studentColumn === null) {
    $conn->close();
    error_log("Email or Student ID column was not found.");
    http_response_code(500);
    exit("Please check the email and Student ID columns in your database.");
}

// Search using email OR Student ID.
$sql = "SELECT * FROM `user`
        WHERE `{$emailColumn}` = ?
           OR `{$studentColumn}` = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $conn->close();
    error_log("Login query preparation failed.");
    http_response_code(500);
    exit("Unable to process login request.");
}

$stmt->bind_param("ss", $identifier, $identifier);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();
$conn->close();

// Verify the password.
if ($user && isset($user["Password"])
    && password_verify($password, $user["Password"])) {

    session_regenerate_id(true);

    $_SESSION["user_id"] = $user["UserID"] ?? $user["id"] ?? null;
    $_SESSION["user_name"] = $user["Name"] ?? "";
    $_SESSION["user_email"] = $user[$emailColumn] ?? "";
    $_SESSION["user_role"] = $user["Role"] ?? "Student";

    header("Location: index.html");
    exit;
}

// Invalid credentials.
header("Location: login.html?error=" .
    urlencode("Invalid email, Student ID, or password."));
exit;
?>
