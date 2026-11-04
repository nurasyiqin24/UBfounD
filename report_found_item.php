<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION["UserID"])) {
    header("Location: login.html");
    exit();
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

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get the user's ID
    $userID = $_SESSION["UserID"];

    // Get the form values
    $itemName = trim($_POST["itemName"]);
    $categoryName = trim($_POST["category"]);
    $description = trim($_POST["description"]);
    $size = trim($_POST["size"]);
    $brand = trim($_POST["brand"]);
    $colour = trim($_POST["colour"]);
    $description = trim($_POST["description"]);

    $dateFound = $_POST["dateFound"];
    $timeOccurred = $_POST["timeOccurred"];

    $location = trim($_POST["location"]);
    $notes = trim($_POST["notes"]);

    // Check required fields
    if (
        empty($itemName) ||
        empty($categoryName) ||
        empty($size) ||
        empty($brand) ||
        empty($colour) ||
        empty($description) ||
        empty($dateFound) ||
        empty($description) ||
        empty($timeOccurred) ||
        empty($location)
    ) {
        die("Please complete all required fields.");
    }

    // Add seconds to the selected time
    if($timeOccurred !== "") {
        $timeOccurred .= ":00";
    } else {
        $timeOccurred = $timeOccurred null;;
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
            mkdir($uploadFolder, 0755, true);
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
            "sisss",
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
        $reportType = "Found";
        $status = "Open";

        // Insert report details into ITEM REPORT
        $sql = "INSERT INTO `ITEM REPORT`
                (UserID, ItemProfileID, ReportType, DateReported,
                 Location, DateOccured, TimeOccured, Status, Comments)
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

            $sql = "INSERT INTO `IMAGE ITEM`
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
        header("Location: founditems.php?success=1");
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
