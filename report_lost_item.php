<?php
// Start the session so we can identify the logged-in user.
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli(
    "localhost",
    "root",
    "1234",
    "lostandfound",
    4406
);

// Use UTF-8 for text stored in the database.
$conn->set_charset("utf8mb4");

// Get the ID of the user who logged in.
// IMPORTANT: This session key must match your login PHP code.
$userId = $_SESSION["user_id"] ?? null;

// Store messages that will be displayed on the page.
$message = "";
$messageType = "danger";

// Variables used to display the user's registered details.
$userEmail = "";
$userPhone = "";
$userName = "";

// Store category options retrieved from the CATEGORY table.
$categories = [];

// Check whether a user is logged in before allowing a report.
$columnsResult = $conn->query("SHOW COLUMNS FROM `user`");
$userColumns = [];

while ($column = $columnsResult->fetch_assoc()) {
    $userColumns[] = $column["Field"];
}

$emailColumn = null;

foreach (["Email", "Emaill"] as $candidate) {
    if (in_array($candidate, $userColumns, true)) {
        $emailColumn = $candidate;
        break;
    }
}

if ($emailColumn === null) {
    throw new Exception("Email column not found in user table.");
}

if ($userId !== null) {
    $userStmt = $conn->prepare(
        "SELECT Name, $emailColumn, Phone
         FROM `user`
         WHERE UserID = ?"
    );

    $userStmt->bind_param("i", $userId);
    $userStmt->execute();

    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();

    // Confirm that this user ID exists in the database.
    if ($user) {
        $userName = $user["Name"] ?? "";
        $userEmail = $user[$emailColumn] ?? "";
        $userPhone = $user["Phone"] ?? "";
    } else {
        $userId = null;
    }

    $userStmt->close();
}

// Load the available categories from the database.
$categoryResult = $conn->query(
    "SELECT CategoryID, CategoryName
     FROM `category`
     ORDER BY CategoryName"
);

while ($category = $categoryResult->fetch_assoc()) {
    $categories[] = $category;
}

// Process the form only when the user submits it.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Do not accept reports from users who are not logged in.
    if ($userId === null) {
        $message = "Please log in before submitting a lost-item report.";
    } else {

        // Get the submitted form values.
        $itemName = trim($_POST["itemName"] ?? "");
        $size = trim($_POST["size"] ?? "");
        $categoryId = $_POST["category"] ?? "";
        $description = trim($_POST["description"] ?? "");
        $dateLost = $_POST["dateLost"] ?? "";
        $location = trim($_POST["location"] ?? "");
        $notes = trim($_POST["notes"] ?? "");

        // Validate the date format and prevent future lost dates.
        $dateObject = DateTime::createFromFormat("!Y-m-d", $dateLost);

        $validDate = $dateObject
            && $dateObject->format("Y-m-d") === $dateLost
            && $dateLost <= date("Y-m-d");

        // Check the required fields.
        if (
            $itemName === "" ||
            $size === "" ||
            $categoryId === "" ||
            !ctype_digit((string)$categoryId) ||
            $description === "" ||
            !$validDate ||
            $location === ""
        ) {
            $message = "Please enter valid information in all required fields.";
        } else {

            // Confirm that the selected category exists.
            $checkCategory = $conn->prepare(
                "SELECT CategoryID
                 FROM `category`
                 WHERE CategoryID = ?"
            );

            $checkCategory->bind_param("i", $categoryId);
            $checkCategory->execute();

            $categoryExists = $checkCategory
                ->get_result()
                ->num_rows > 0;

            $checkCategory->close();

            if (!$categoryExists) {
                $message = "Please select a valid category.";
            } else {

                // The image is optional. If uploaded, validate it.
                $imagePath = null;
                $uploadError = "";

                if (
                    isset($_FILES["itemImage"]) &&
                    $_FILES["itemImage"]["error"] !== UPLOAD_ERR_NO_FILE
                ) {
                    if ($_FILES["itemImage"]["error"] !== UPLOAD_ERR_OK) {
                        $uploadError = "The image could not be uploaded.";
                    } elseif ($_FILES["itemImage"]["size"] > 5 * 1024 * 1024) {
                        // Limit image uploads to 5 MB.
                        $uploadError = "The image must be 5 MB or smaller.";
                    } else {
                        // Check the actual file type, not just its filename.
                        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                        $mimeType = $fileInfo->file(
                            $_FILES["itemImage"]["tmp_name"]
                        );

                        $allowedTypes = [
                            "image/jpeg" => "jpg",
                            "image/png" => "png",
                            "image/webp" => "webp"
                        ];

                        if (!isset($allowedTypes[$mimeType])) {
                            $uploadError =
                                "Please upload a JPG, PNG or WebP image.";
                        } else {
                            // Create a unique filename for the image.
                            $extension = $allowedTypes[$mimeType];
                            $newFileName = bin2hex(random_bytes(16))
                                . "." . $extension;

                            // Save images in the project's uploads folder.
                            $uploadDirectory = __DIR__ . "/uploads/";

                            if (
                                !is_dir($uploadDirectory) &&
                                !mkdir($uploadDirectory, 0755, true)
                            ) {
                                $uploadError =
                                    "The image upload folder could not be created.";
                            } elseif (
                                !is_writable($uploadDirectory) &&
                                !is_dir($uploadDirectory)
                            ) {
                                $uploadError =
                                    "The image upload folder is not writable.";
                            } else {
                                $destination = $uploadDirectory . $newFileName;

                                if (
                                    move_uploaded_file(
                                        $_FILES["itemImage"]["tmp_name"],
                                        $destination
                                    )
                                ) {
                                    // Store a relative path in the database.
                                    $imagePath = "uploads/" . $newFileName;
                                } else {
                                    $uploadError =
                                        "The image could not be saved.";
                                }
                            }
                        }
                    }
                }

                if ($uploadError !== "") {
                    $message = $uploadError;
                    } else {
                        try {
                            // Begin a transaction so related records are saved
                            // together instead of leaving a partial report.
                            $conn->begin_transaction();

                            // STEP 1: Save the item's details.
                            // The description is stored in OtherComments
                            // because ITEM PROFILE has no Description column.
                            $profileStmt = $conn->prepare(
                                "INSERT INTO `item_profile`
                                    (CategoryID, ItemName, Size, Brand, Colour, OtherComments)
                                VALUES (?, ?, ?, ?, ?, ?)"
                            );

                            $profileStmt->bind_param(
                                "isssss",
                                $categoryId,
                                $itemName,
                                $size,
                                $brand,
                                $colour,
                                $description
                            );

                            $profileStmt->execute();

                            // Get the ID of the newly created item profile.
                            $itemProfileId = $conn->insert_id;
                            $profileStmt->close();

                            // STEP 2: Save the lost-item report.
                            // UserID links the report to the logged-in user.
                            // DateReported records today's date.
                            // DateOccured records the date the item was lost.
                            // Additional notes are saved in Comments.
                            //
                            // Check whether TimeOccured allows NULL.
                            // If it does, save NULL because the form has no time field.
                            // Otherwise, omit it and rely on the database default.
                            $timeColumn = $conn->query(
                                "SHOW COLUMNS FROM `item_report`
                                LIKE 'TimeOccured'"
                            )->fetch_assoc();

                            if ($timeColumn && $timeColumn["Null"] === "YES") {
                                $reportStmt = $conn->prepare(
                                    "INSERT INTO `item_report`
                                        (UserID, ItemProfileID, ReportType,
                                        DateReported, Location, DateOccured,
                                        TimeOccured, Status, Comments)
                                    VALUES (?, ?, 'Lost', CURDATE(),
                                            ?, ?, NULL, 'Open', ?)"
                                );
                            } else {
                                $reportStmt = $conn->prepare(
                                    "INSERT INTO `item_report`
                                        (UserID, ItemProfileID, ReportType,
                                        DateReported, Location, DateOccured,
                                        Status, Comments)
                                    VALUES (?, ?, 'Lost', CURDATE(),
                                            ?, ?, 'Open', ?)"
                                );
                            }

                            $reportStmt->bind_param(
                                "iisss",
                                $userId,
                                $itemProfileId,
                                $location,
                                $dateLost,
                                $notes
                            );

                            $reportStmt->execute();
                            $reportStmt->close();

                            // STEP 3: Save the image path if an image was uploaded.
                            if ($imagePath !== null) {
                                $imageStmt = $conn->prepare(
                                    "INSERT INTO `item_image`
                                        (ItemProfileID, ImageLink, DateUpload)
                                    VALUES (?, ?, NOW())"
                                );

                                $imageStmt->bind_param(
                                    "is",
                                    $itemProfileId,
                                    $imagePath
                                );

                                $imageStmt->execute();
                                $imageStmt->close();
                            }

                            // Save all database changes together.
                            $conn->commit();

                            $message = "Your lost-item report was submitted successfully.";
                            $messageType = "success";

                        } catch (Throwable $error) {
                            // Undo database changes if any step fails.
                            $conn->rollback();

                            // Log the technical error for debugging.
                            error_log("Lost-item report error: " . $error->getMessage());

                            // Show a simple message to the user.
                            $message =
                                "Database error: " . $error->getMessage();
                    }
                }
            }
        }
    }
}

// Escape text before displaying it in HTML.
function escapeHtml($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Report Lost Item - UbdFind</title>

    <!-- Bootstrap CSS keeps the page responsive. -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <!-- Navigation Bar-->
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
                        <a class="nav-link" href="lostitems.html">Lost Items</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="founditems.html">Found Items</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="myreports.html">My Reports</a>
                    </li>

                    <li class="nav-item">
                        <!-- Corrected the Matches link. -->
                        <a class="nav-link" href="matches.html">Matches</a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link notification-bell"
                            href="notifications.html">🔔</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="profile.html">Profile</a>
                    </li>

                </ul>
            </div>
        </div>
    </nav>

    
    <section class="hero-section py-5">
        <div class="container">
            <div class="hero-card p-4 p-md-5">

                <h1 class="fw-bold mb-2">Report Lost Item</h1>

                <p class="text-muted mb-0">
                    Provide details about your lost item to help others
                    identify and return your item.
                </p>

            </div>
        </div>
    </section>

    <!-- Report Form -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4 p-md-5">

                            <!--
                                POST sends form values to this PHP page.
                                multipart/form-data is needed for image uploads.
                            -->
                            <form
                                method="POST"
                                action="report_lost_item.php"
                                enctype="multipart/form-data">

                                <!-- Display PHP success or error messages. -->
                                <?php if ($message !== ""): ?>
                                    <div class="alert alert-<?php
                                        echo escapeHtml($messageType);
                                    ?>" role="alert">
                                        <?php echo escapeHtml($message); ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Warn users if they are not logged in. -->
                                <?php if ($userId === null): ?>
                                    <div class="alert alert-warning">
                                        Please log in before submitting a lost-item report.
                                    </div>
                                <?php endif; ?>

                                <!-- Item Name -->
                                <div class="mb-4">
                                    <label
                                        for="itemName"
                                        class="form-label fw-semibold">
                                        Item Name
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="itemName"
                                        name="itemName"
                                        placeholder="e.g. Black wallet"
                                        maxlength="255"
                                        required>
                                </div>

                                <!-- Category options are loaded from CATEGORY. -->
                                <div class="mb-4">
                                    <label
                                        for="category"
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
                                                echo escapeHtml($category["CategoryID"]);
                                            ?>">
                                                <?php echo escapeHtml(
                                                    $category["CategoryName"]
                                                ); ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <!-- Item description. -->
                                <div class="mb-4">
                                    <label
                                        for="description"
                                        class="form-label fw-semibold">
                                        Description
                                    </label>

                                    <textarea
                                        class="form-control"
                                        id="description"
                                        name="description"
                                        rows="4"
                                        placeholder="Describe the item, including its colour, brand, model, markings, size or other identifying details."
                                        required></textarea>
                                </div>

                                <!--Item size-->
                                <div class="mb-3">
                                    <label for="size" class="form-label">Item Size</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            id="size"
                                            name="size"
                                            placeholder="e.g. Small, medium, large, or approximately 20 cm"
                                            required
                                        >
                                </div>

                                <!--Item Colour-->
                                <div class="mb-3">
                                    <label for="colour" class="form-label">Item Colour</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            id="colour"
                                            name="colour"
                                            placeholder="e.g. Red, Blue, Green"
                                            required
                                        >
                                </div>

                                <!--Item Brand-->
                                <div class="mb-3">
                                    <label for="brand" class="form-label">Item Brand</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            id="brand"
                                            name="brand"
                                            placeholder="e.g. Apple, Samsung, Nike"
                                            required
                                        >
                                </div>

                                <!-- Date the item was lost. -->
                                <div class="mb-4">
                                    <label
                                        for="dateLost"
                                        class="form-label fw-semibold">
                                        Date Lost
                                    </label>

                                    <input
                                        type="date"
                                        class="form-control"
                                        id="dateLost"
                                        name="dateLost"
                                        max="<?php echo date('Y-m-d'); ?>"
                                        required>
                                </div>

                                <!-- Last known location of the item. -->
                                <div class="mb-4">
                                    <label
                                        for="location"
                                        class="form-label fw-semibold">
                                        Last Known Location
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="location"
                                        name="location"
                                        placeholder="e.g. UBD Library"
                                        maxlength="255"
                                        required>

                                    <div class="form-text">
                                        Enter the place where you last remember
                                        having the item before it was lost.
                                    </div>
                                </div>

                                <!-- Optional image upload. -->
                                <div class="mb-4">
                                    <label
                                        for="itemImage"
                                        class="form-label fw-semibold">
                                        Upload Image
                                    </label>

                                    <input
                                        type="file"
                                        class="form-control"
                                        id="itemImage"
                                        name="itemImage"
                                        accept="image/jpeg,image/png,image/webp">

                                    <div class="form-text">
                                        Upload a JPG, PNG or WebP photo
                                        (maximum 5 MB).
                                    </div>
                                </div>

                                <!--
                                    Contact Information now comes from USER.
                                    It is displayed for reference and is not
                                    submitted as a separate report field.
                                -->
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

                                <!-- Additional notes about the lost item. -->
                                <div class="mb-4">
                                    <label
                                        for="notes"
                                        class="form-label fw-semibold">
                                        Additional Notes
                                    </label>

                                    <textarea
                                        class="form-control"
                                        id="notes"
                                        name="notes"
                                        rows="3"
                                        placeholder="Add any additional information that may help someone identify your item."></textarea>
                                </div>

                                <!-- Form buttons. -->
                                <div class="d-flex flex-column flex-sm-row gap-2">

                                    <a
                                        href="lostitems.html"
                                        class="btn btn-outline-secondary px-4">
                                        Cancel
                                    </a>

                                    <button
                                        type="submit"
                                        class="btn btn-primary px-4"
                                        <?php echo $userId === null ? "disabled" : ""; ?>>
                                        Submit Lost Item Report
                                    </button>

                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    
    <footer class="border-top py-4 mt-3">
        <div class="container text-center">
            <p class="text-muted mb-0">
                © 2026 UbdFind. Helping UBD students find what they've lost.
            </p>
        </div>
    </footer>

    <!-- Bootstrap JavaScript for the responsive navigation bar. -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>

</body>
</html>

<?php
// Close the database connection when the page has finished processing.
$conn->close();
?>