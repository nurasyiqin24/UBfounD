<?php
session_start();

// Initialise messages and user information
$message = "";
$messageType = "success";

$userId = $_SESSION["user_id"] ?? null;
$userEmail = "";
$userPhone = "";
$categories = [];


// Check if the user is logged in
if ($userId === null){
    header("Location: login.html");
    exit();
}

// Escape output safely
function escapeHtml($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}


// Connect to the database
$conn = new mysqli(
    "localhost", 
    "root", 
    "1234", 
    "lostandfound", 
    4406
    );

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Retrieve category information
$sql = "SELECT CategoryID, CategoryName
        FROM category
        ORDER BY CategoryID ASC";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Retrieve registered email and phone information
$sql = "SELECT * FROM `user` WHERE UserID = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();

$userResult = $stmt->get_result();
$user = $userResult->fetch_assoc();

$stmt->close();

if ($user) {
    $userEmail = $user["Emaill"] ?? "";

    // Check common possible phone column names.
    // Change this to your actual phone column if necessary.
    $userPhone = $user["Phone"] 
        ?? $user["PhoneNumber"]
        ?? $user["ContactNumber"]
        ?? "";
}


// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get the user's ID
    $userID = $_SESSION["user_id"];

    // Get the form values
    $itemName = trim($_POST["itemName"]??"");
    $categoryName = trim($_POST["category"]??"");
    $description = trim($_POST["description"]??"");
    $size = trim($_POST["size"]??"");
    $brand = trim($_POST["brand"]??"");
    $colour = trim($_POST["colour"]??"");

    $dateFound = $_POST["dateFound"]??"";
    $timeOccurred = $_POST["timeOccurred"]??"";

    $location = trim($_POST["location"]??"");
    $notes = trim($_POST["notes"]??"");

    // Check required fields
    if (
        empty($itemName) ||
        empty($categoryName) ||
        empty($size)||
        empty($brand) ||
        empty($colour) ||
        empty($description) ||
        empty($dateFound) ||
        empty($location)

    ){
        
        $message = "Please complete all required fields.";
        $messageType = "danger";
    }

    
   // Add seconds to the selected time
    if ($timeOccurred !== "") {

    // HTML time input usually returns HH:MM
    if (strlen($timeOccurred) === 5) {
        $timeOccurred .= ":00";
    }

    } else {

    // Store NULL if no time was provided
    $timeOccurred = null;
    }

    // Store the uploaded image path
    $imagePath = null;
    $destination = null;

    // Check if an image was uploaded
    if (
        isset($_FILES["itemImage"]) &&
        $_FILES["itemImage"]["error"] != UPLOAD_ERR_NO_FILE
    ) {

        // Check for upload errors
        if ($_FILES["itemImage"]["error"] != UPLOAD_ERR_OK) {
            die("Image upload failed. Please try again.");
        }

        // Check image size (maximum 5 MB)
        if ($_FILES["itemImage"]["size"] > 5 * 1024 * 1024) {
            die("Image size must not exceed 5 MB.");
        }

        // Allow only JPG and PNG images
        $allowedTypes = [
            "image/jpeg" => "jpg",
            "image/png" => "png"
        ];

        // Check the actual image file type
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $fileType = $fileInfo->file($_FILES["itemImage"]["tmp_name"]);

        if (!isset($allowedTypes[$fileType])) {
            die("Only JPG and PNG images are allowed.");
        }

        // Create the uploads folder if it does not exist
        $uploadFolder = __DIR__ . "/uploads/";

        if (!is_dir($uploadFolder)) {
            if(!mkdir($uploadFolder, 0755, true)) {
                        throw new Exception("Unable to create uploads folder.");
                    }
        }

        // Generate a unique filename
        $fileName = uniqid("found_") . "." . $allowedTypes[$fileType];

        // Set the image destination
        $destination = $uploadFolder . $fileName;

        // Move the uploaded image into the folder
        if (!move_uploaded_file(
            $_FILES["itemImage"]["tmp_name"],
            $destination
        )) {
            die("Unable to upload image.");
        }

        // Store the image path for the database
        $imagePath = "uploads/" . $fileName;
    }

    // Start database transaction
    $conn->begin_transaction();

    try {

        // Get the CategoryID based on the selected category
        $sql = "SELECT CategoryID
                FROM `category`
                WHERE CategoryName = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $categoryName);
        $stmt->execute();
        $stmt->bind_result($categoryID);

        if (!$stmt->fetch()) {
            throw new Exception("Category not found.");
        }

        $stmt->close();

        // Insert item details into ITEM PROFILE
        $sql = "INSERT INTO item_profile
                (ItemName, CategoryID, Size, Brand, Colour, OtherComments)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
                "sissss",
                $itemName,
                $categoryID,
                $size,
                $brand,
                $colour,
                $description
            );

        $stmt->execute();

        // Get the new ItemProfileID
        $itemProfileID = $conn->insert_id;

        $stmt->close();

        // Set the report type and status
        $reportType = "FOUND";
        $status = "Found item is available to claim";

        // Insert report details into ITEM REPORT
        $sql = "INSERT INTO `item_report`
                (UserID, ItemProfileID, ReportType, DateReported,
                 Location, DateOccurred, TimeOccurred, Status, Comments)
                VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "iissssss",
            $userID,
            $itemProfileID,
            $reportType,
            $location,
            $dateFound,
            $timeOccured,
            $status,
            $notes
        );

        $stmt->execute();
        $stmt->close();

        // Insert image information if an image was uploaded
        if ($imagePath !== null) {

            $sql = "INSERT INTO `item_image`
                    (ItemProfileID, ImageLink, DateUpload)
                    VALUES (?, ?, CURDATE())";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "is", 
                $itemProfileID, 
                $imagePath
            );
            $stmt->execute();
            $stmt->close();

            
        }

        // Save all database changes
        $conn->commit();

        // Close the connection
        $conn->close();

        // Redirect to the Found Items page
        header("Location: found-items.php?success=1");
        exit();

    } catch (Throwable $e) {

        // Undo database changes if something goes wrong
        $conn->rollback();

        // Remove the uploaded image if the report was not saved
        if ($destination !== null && file_exists($destination)) {
            unlink($destination);
        }

        // Display the error message
        die("Error submitting report: " .
            htmlspecialchars($e->getMessage()));
    }
}

// Close the database connection
$conn->close();
?>


<?php
// This page should use the same PHP setup as report_lost_item.php.
// The variables below must be initialized by your PHP processing code:
// $message, $messageType, $userId, $categories,
// $userEmail, $userPhone, $conn and escapeHtml().
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Report Found Item - UbdFind</title>

    <!-- Bootstrap CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg bg-white border-bottom">
        <div class="container">

            <a class="navbar-brand fw-bold" href="index.html">
                UbdFind
            </a>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarNav"
                aria-controls="navbarNav"
                aria-expanded="false"
                aria-label="Toggle navigation">

                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">

                    <li class="nav-item">
                        <a class="nav-link" href="lost-items.php">Lost Items</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="found-items.php">Found Items</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="myreports.php">My Reports</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="matches.html">Matches</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link notification-bell"
                            href="notifications.html">🔔</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="profile.html">Profile</a>
                    </li>

                </ul>
            </div>
        </div>
    </nav>


    <!-- Hero Section -->
    <section class="hero-section py-5">
        <div class="container">

            <div class="hero-card p-4 p-md-5">

                <h1 class="fw-bold mb-2">Report Found Item</h1>

                <p class="text-muted mb-0">
                    Provide details about the item you found on campus
                    to help its owner identify and retrieve it.
                </p>

            </div>
        </div>
    </section>


    <!-- Found Item Report Form -->
    <section class="py-5">
        <div class="container">

            <div class="row justify-content-center">
                <div class="col-lg-8">

                    <div class="card border-0 shadow-sm rounded-4">

                        <div class="card-body p-4 p-md-5">

                            <form
                                method="POST"
                                action="report_found_item.php"
                                enctype="multipart/form-data">

                                <!-- Success or Error Message -->
                                <?php if ($message !== ""): ?>

                                    <div class="alert alert-<?php
                                        echo escapeHtml($messageType);
                                    ?>" role="alert">

                                        <?php echo escapeHtml($message); ?>

                                    </div>

                                <?php endif; ?>


                                <!-- Login Warning -->
                                <?php if ($userId === null): ?>

                                    <div class="alert alert-warning">
                                        Please log in before submitting a found-item report.
                                    </div>

                                <?php endif; ?>


                                <!-- Item Name -->
                                <div class="mb-4">

                                    <label for="itemName"
                                        class="form-label fw-semibold">
                                        Item Name
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="itemName"
                                        name="itemName"
                                        placeholder="e.g. Black wallet"
                                        maxlength="100"
                                        required>

                                </div>


                                <!-- Category -->
                                <div class="mb-4">

                                    <label for="category"
                                        class="form-label fw-semibold">
                                        Category
                                    </label>

                                    <select
                                        class="form-select"
                                        id="category"
                                        name="category"
                                        required>

                                        <option value="" selected disabled>
                                            Select a category
                                        </option>

                                        <?php foreach ($categories as $category): ?>

                                            <option value="<?php
                                                echo escapeHtml($category["CategoryName"]);
                                            ?>">

                                                <?php echo escapeHtml(
                                                    $category["CategoryName"]
                                                ); ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <!-- Description -->
                                <div class="mb-4">

                                    <label for="description"
                                        class="form-label fw-semibold">
                                        Description
                                    </label>

                                    <textarea
                                        class="form-control"
                                        id="description"
                                        name="description"
                                        rows="4"
                                        maxlength="1000"
                                        placeholder="Describe the item and its identifying details."
                                        required></textarea>

                                </div>


                                <!-- Item Size -->
                                <div class="mb-3">

                                    <label for="size"
                                        class="form-label fw-semibold">
                                        Item Size
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="size"
                                        name="size"
                                        maxlength="50"
                                        placeholder="e.g. Small, Medium, Large or approximately 20 cm"
                                        required>

                                </div>


                                <!-- Item Colour -->
                                <div class="mb-3">

                                    <label for="colour"
                                        class="form-label fw-semibold">
                                        Colour
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="colour"
                                        name="colour"
                                        maxlength="20"
                                        placeholder="e.g. Red, Blue, Green"
                                        required>

                                </div>


                                <!-- Item Brand -->
                                <div class="mb-3">

                                    <label for="brand"
                                        class="form-label fw-semibold">
                                        Brand
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="brand"
                                        name="brand"
                                        maxlength="100"
                                        placeholder="e.g. Apple, Samsung, Nike or Unknown"
                                        required>

                                </div>


                                <!-- Date Found -->
                                <div class="mb-4">

                                    <label for="dateFound"
                                        class="form-label fw-semibold">
                                        Date Found
                                    </label>

                                    <input
                                        type="date"
                                        class="form-control"
                                        id="dateFound"
                                        name="dateFound"
                                        max="<?php echo date('Y-m-d'); ?>"
                                        required>

                                </div>


                                <!-- Time Found -->
                                <div class="mb-4">

                                    <label for="timeOccurred"
                                        class="form-label fw-semibold">
                                        Time Found
                                    </label>

                                    <input
                                        type="time"
                                        class="form-control"
                                        id="timeOccurred"
                                        name="timeOccurred">

                                </div>


                                <!-- Found Location -->
                                <div class="mb-4">

                                    <label for="location"
                                        class="form-label fw-semibold">
                                        Found Location
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="location"
                                        name="location"
                                        placeholder="e.g. UBD Library"
                                        maxlength="300"
                                        required>

                                    <div class="form-text">
                                        Enter the location where you found the item.
                                    </div>

                                </div>


                                <!-- Image Upload -->
                                <div class="mb-4">

                                    <label for="itemImage"
                                        class="form-label fw-semibold">
                                        Upload Image
                                    </label>

                                    <input
                                        type="file"
                                        class="form-control"
                                        id="itemImage"
                                        name="itemImage"
                                        accept="image/jpeg,image/png">

                                    <div class="form-text">
                                        Upload a JPG or PNG photo
                                        (maximum 5 MB).
                                    </div>

                                </div>


                                <!-- Registered Contact Information -->
                                <div class="mb-4">

                                    <label class="form-label fw-semibold">
                                        Registered Contact Information
                                    </label>

                                    <div class="form-control bg-light">

                                        <strong>Email:</strong>

                                        <?php
                                        echo $userEmail !== ""
                                            ? escapeHtml($userEmail)
                                            : "No email registered";
                                        ?>

                                        <br>

                                        <strong>Phone:</strong>

                                        <?php
                                        echo $userPhone !== ""
                                            ? escapeHtml($userPhone)
                                            : "No phone number registered";
                                        ?>

                                    </div>

                                    <div class="form-text">
                                        These details come from your registered
                                        UbdFind account.
                                    </div>

                                </div>


                                <!-- Additional Notes -->
                                <div class="mb-4">

                                    <label for="notes"
                                        class="form-label fw-semibold">
                                        Additional Notes
                                    </label>

                                    <textarea
                                        class="form-control"
                                        id="notes"
                                        name="notes"
                                        rows="3"
                                        maxlength="2000"
                                        placeholder="Add any additional information that may help someone identify the item."></textarea>

                                </div>


                                <!-- Form Buttons -->
                                <div class="d-flex flex-column flex-sm-row gap-2">

                                    <a
                                        href="found-items.php"
                                        class="btn btn-outline-secondary px-4">
                                        Cancel
                                    </a>

                                    <button
                                        type="submit"
                                        class="btn btn-primary px-4"
                                        <?php echo $userId === null ? "disabled" : ""; ?>>

                                        Submit Found Item Report

                                    </button>

                                </div>

                            </form>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>


    <!-- Footer -->
    <footer class="border-top py-4 mt-3">

        <div class="container text-center">

            <p class="text-muted mb-0">
                © 2026 UbdFind. Helping UBD students return found items.
            </p>

        </div>

    </footer>


    <!-- Bootstrap JavaScript -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>

</body>
</html>

