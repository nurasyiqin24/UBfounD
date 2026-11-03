<?php
// Connect to the UbdFind database.
// Keep your existing local database password here.
$conn = new mysqli(
    "localhost",
    "root",
    "1234",
    "lostandfound",
    4406
);

$conn->set_charset("utf8mb4");

// Check database connection.
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit("Database connection failed. Please try again later.");
}

// Check whether a ReportID was provided
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    die("Invalid item report.");
}

$reportID = (int) $_GET['id'];

$sql = "
    SELECT
        r.ReportID,
        r.ReportType,
        r.DateReported,
        r.Location,
        r.DateOccurred,
        r.TimeOccurred,
        r.Status,
        r.Comments,

        p.ItemProfileID,
        p.ItemName,
        p.Size,
        p.Brand,
        p.Colour,
        p.OtherComments,

        c.CategoryName,
        (
            SELECT ii.ImageLink
            FROM item_image AS ii
            WHERE ii.ItemProfileID = p.ItemProfileID
            ORDER BY ii.DateUpload DESC
            LIMIT 1
        ) AS ImageLink

    FROM item_report AS r

    INNER JOIN item_profile AS p
        ON r.ItemProfileID = p.ItemProfileID

    LEFT JOIN category AS c
        ON p.CategoryID = c.CategoryID

    WHERE r.ReportID = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reportID);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Item report not found.");
}

$itemDetails = $result->fetch_assoc();

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, 'UTF-8');
}

$reportedDate = !empty($itemDetails['DateReported'])
    ? date("d F, Y", strtotime($itemDetails['DateReported']))
    : "Not specified";

$occurredDate = !empty($itemDetails['DateOccurred'])
    ? date("d F, Y", strtotime($itemDetails['DateOccurred']))
    : "Not specified";

$occurredTime = !empty($itemDetails['TimeOccurred'])
    ? date("h:i A", strtotime($itemDetails['TimeOccurred']))
    : "Not specified";

//Close the database connection
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Item Details | UBFounD</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg border-bottom">
    <div class="container">

        <a class="navbar-brand" href="index.html">
            <img src="UBFounD\assets\images\ubfound-logo-version3a.png" 
                alt="UBFounD Logo" 
                class="navbar-logo"
            >
        </a>

        <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">

                <li class="nav-item">
                    <a class="nav-link" href="index.html">Home</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="lost-items.php">Lost Items</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="found-items.php">Found Items</a>
                </li>

            </ul>
        </div>

    </div>
</nav>


<!-- ITEM DETAILS -->
<section class="py-5">

    <div class="container">

        <a href="<?= strtoupper($item['ReportType']) === 'LOST' ? 'lost-items.php' : 'found-items.php' ?>"
           class="btn btn-outline-secondary mb-4">
            &larr; Back to Items
        </a>

        <div class="card shadow-sm border-0">

            <div class="row g-0">

                <!-- ITEM IMAGE -->
                <div class="col-md-5">

                    <?php if (!empty($item['ImageLink'])): ?>

                        <img
                            src="<?= e($item['ImageLink']) ?>"
                            alt="<?= e($item['ItemName']) ?>"
                            class="img-fluid w-100 h-100"
                            style="object-fit: contain; min-height: 300px;"
                        >

                    <?php else: ?>

                        <div class="d-flex align-items-center justify-content-center
                                    bg-light h-100"
                             style="min-height: 300px;">

                            <span class="text-muted">
                                No image available
                            </span>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- ITEM INFORMATION -->
                <div class="col-md-7">

                    <div class="card-body p-4">

                        <span class="badge <?= strtoupper($item['ReportType']) === 'LOST' ? 'bg-danger' : 'bg-success' ?> mb-3">

                            <?= e(strtoupper($item['ReportType'])) ?>

                        </span>

                        <h2 class="fw-bold mb-3">
                            <?= e($item['ItemName']) ?>
                        </h2>

                        <p class="text-muted">
                            <?= e($item['CategoryName'] ?: 'Not specified') ?>
                        </p>

                        <hr>

                        <h5 class="fw-bold mb-3">Item Information</h5>

                        <p><strong>Brand:</strong>
                            <?= e($item['Brand']) ?>
                        </p>

                        <p><strong>Colour:</strong>
                            <?= e($item['Colour']) ?>
                        </p>

                        <p><strong>Size:</strong>
                            <?= e($item['Size']) ?>
                        </p>

                        <p><strong>Additional Information:</strong>
                            <?= e($item['OtherComments'] ?: 'Not provided') ?>
                        </p>

                        <hr>

                        <h5 class="fw-bold mb-3">Report Information</h5>

                        <p><strong>Location:</strong>
                            <?= e($item['Location']) ?>
                        </p>

                        <p><strong>Date Lost / Found:</strong>
                            <?= e($occurredDate) ?>
                        </p>

                        <p><strong>Time:</strong>
                            <?= e($occurredTime) ?>
                        </p>

                        <p><strong>Date Reported:</strong>
                            <?= e($reportedDate) ?>
                        </p>

                        <p><strong>Status:</strong>
                            <?= e($item['Status']) ?>
                        </p>

                        <p><strong>Report Comments:</strong>
                            <?= e($item['Comments'] ?: 'No comments provided') ?>
                        </p>

                        <hr>

                        <p class="text-muted small mb-0">
                            Report ID: <?= e($item['ReportID']) ?>
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- FOOTER -->
<footer class="py-3 text-center">
    <div class="container">
        <p class="mb-0">
            &copy; 2026 UBFounD - Campus Lost & Found System
        </p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>