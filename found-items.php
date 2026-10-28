<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Connect to the UbdFind database
$conn = new mysqli(
    "localhost",
    "root",
    "1234",
    "lostandfound",
    4406
);

$conn->set_charset("utf8mb4");

// Retrieve found items from the database that have been recorded as found. The query joins the item_report, item_profile, and category tables to get the necessary information about each found item. It also retrieves the most recent image for each item from the item_image table.
$reportType = "FOUND";

$sql = "
    SELECT
        r.ReportID,
        r.ReportType,
        r.Location,
        r.DateOccurred,
        r.DateReported,
        r.Status,
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
    WHERE r.ReportType = ?
    ORDER BY r.DateReported DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $reportType);
$stmt->execute();

$result = $stmt->get_result();

$items = [];

while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

// Get categories for the category filter
$categories = [];

foreach ($items as $item) {
    $categoryName = $item["CategoryName"] ?? "";

    if (
        $categoryName !== "" &&
        !in_array($categoryName, $categories, true)
    ) {
        $categories[] = $categoryName;
    }
}

sort($categories);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title> Found Items | UbdFind</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<!-- Navigation bar -->
<nav class="navbar navbar-expand-lg navbar-light sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.html">UbdFind</a>

        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="index.html">Home</a>
            <a class="nav-link" href="lost-items.php">Lost Items</a>
            <a class="nav-link" href="found-items.php">Found Items</a>
        </div>
    </div>
</nav>

<!-- Page heading -->
<div class="container listing-page">
    <div class="listing-heading">
        <h1>Found Items</h1>
        <p>Browse items that have been reported found on campus.</p>
    </div>

    <!-- Search and category filter -->
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <input
                type="text"
                id="searchItem"
                class="form-control"
                placeholder="Search by item name..."
                aria-label="Search items">
        </div>

        <div class="col-md-4">
            <select id="categoryFilter"
                    class="form-select"
                    aria-label="Filter by category">
                <option value="">All Categories</option>

                <?php foreach ($categories as $category): ?>
                    <option value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Dynamic item cards -->
    <div class="row g-4" id="itemsContainer">

        <?php foreach ($items as $item): ?>
            <?php
                $itemName = $item["ItemName"] ?? "Unnamed Item";
                $categoryName = $item["CategoryName"] ?? "Uncategorised";
                $location = $item["Location"] ?? "Location not specified";
                $dateOccurred = $item["DateOccurred"] ?? "";
                $imageLink = $item["ImageLink"] ?? "";
            ?>

            <div class="col-md-6 col-lg-4 item-card-container"
                 data-name="<?= htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8') ?>"
                 data-category="<?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?>">

                <div class="card h-100 shadow-sm">

                    <?php if ($imageLink !== ""): ?>
                        <img
                            src="<?= htmlspecialchars($imageLink, ENT_QUOTES, 'UTF-8') ?>"
                            class="card-img-top"
                            alt="<?= htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8') ?>"
                            style="height: 220px; object-fit: cover;">
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center bg-light text-muted"
                             style="height: 220px;">
                            No image available
                        </div>
                    <?php endif; ?>

                    <div class="card-body">
                        <span class="report-badge report-badge-found">
                            FOUND
                        </span>

                        <h5 class="card-title mt-2">
                            <?= htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8') ?>
                        </h5>

                        <p class="card-text mb-1">
                            <strong>Category:</strong>
                            <?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <p class="card-text mb-1">
                            <strong>Location:</strong>
                            <?= htmlspecialchars($location, ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <p class="card-text mb-1">
                            <strong>Date:</strong>
                            <?= $dateOccurred !== ""
                                ? htmlspecialchars($dateOccurred, ENT_QUOTES, 'UTF-8')
                                : "Not specified" ?>
                        </p>

                        <p class="card-text">
                            <strong>Status:</strong>
                            <?= htmlspecialchars($item["Status"] ?? "Not specified", ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>

                </div>
            </div>

        <?php endforeach; ?>

    </div>

    <!-- Shown when no items match the filters -->
    <p id="noResults"
       class="text-center mt-4"
       <?= count($items) > 0 ? "hidden" : "" ?>>
        No items found.
    </p>
</div>

<!-- Search and category filtering -->
<script>
    const searchInput = document.getElementById("searchItem");
    const categoryFilter = document.getElementById("categoryFilter");
    const cards = document.querySelectorAll(".item-card-container");
    const noResults = document.getElementById("noResults");

    function filterItems() {
        const searchText = searchInput.value.toLowerCase().trim();
        const selectedCategory = categoryFilter.value.toLowerCase();

        let visibleCount = 0;

        cards.forEach(function(card) {
            const name = card.dataset.name.toLowerCase();
            const category = card.dataset.category.toLowerCase();

            const matchesName = name.includes(searchText);
            const matchesCategory =
                selectedCategory === "" ||
                category === selectedCategory;

            const visible = matchesName && matchesCategory;

            card.hidden = !visible;

            if (visible) {
                visibleCount++;
            }
        });

        noResults.hidden = visibleCount > 0;
    }

    searchInput.addEventListener("input", filterItems);
    categoryFilter.addEventListener("change", filterItems);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
