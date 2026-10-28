<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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

// Retrieve found items.
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
    WHERE UPPER(r.ReportType) = ?
    ORDER BY r.DateReported DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $reportType);
$stmt->execute();

$result = $stmt->get_result();

$items = [];
$categories = [];

while ($row = $result->fetch_assoc()) {
    $items[] = $row;

    $categoryName = $row["CategoryName"] ?? "";

    if ($categoryName !== "") {
        $categories[$categoryName] = $categoryName;
    }
}

// Sort categories alphabetically.
$categories = array_values($categories);
sort($categories, SORT_NATURAL | SORT_FLAG_CASE);

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Found Items - UbdFind</title>

    <!-- Bootstrap CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Our own CSS file -->
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <!-- ==================== NAVBAR ==================== -->

    <nav class="navbar navbar-expand-lg bg-white border-bottom">

        <div class="container">

            <!-- Website name -->
            <a class="navbar-brand fw-bold" href="index.html">
                UbdFind
            </a>

            <!-- Mobile menu button -->
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarNav"
                aria-controls="navbarNav"
                aria-expanded="false"
                aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation links -->
            <div class="collapse navbar-collapse" id="navbarNav">

                <ul class="navbar-nav ms-auto">

                    <li class="nav-item">
                        <a class="nav-link" href="index.html">
                            Home
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="lost-items.php">
                            Lost Items
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link active"
                            aria-current="page"
                            href="found-items.php"
                        >
                            Found Items
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="my-reports.html">
                            My Reports
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="matches.html">
                            Matches
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link notification-bell"
                            href="notifications.html"
                            aria-label="Notifications"
                        >
                            🔔
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="profile.html">
                            Profile
                        </a>
                    </li>

                </ul>
            </div>
        </div>
    </nav>


    <!-- ==================== PAGE HEADER ==================== -->

    <section class="py-5">

        <div class="container">

            <h1 class="fw-bold">
                Found Items
            </h1>

            <p class="text-muted">
                Browse items that have been reported found in UBD.
            </p>

        </div>
    </section>


    <!-- ==================== SEARCH AND FILTER ==================== -->

    <section class="pb-4">

        <div class="container">

            <div class="row g-3">

                <!-- Search box -->
                <div class="col-md-6">

                    <label for="searchItem" class="form-label">
                        Search Items
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="searchItem"
                        placeholder="Search by item name..."
                    >

                </div>

                <!-- Category filter -->
                <div class="col-md-4">

                    <label for="categoryFilter" class="form-label">
                        Category
                    </label>

                    <select
                        class="form-select"
                        id="categoryFilter"
                    >
                        <option value="" selected>
                            All Categories
                        </option>

                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars(
                                $category,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">
                                <?= htmlspecialchars(
                                    $category,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <!-- Search button -->
                <div class="col-md-2 d-flex align-items-end">

                    <button
                        type="button"
                        class="btn btn-primary w-100 search-button"
                        id="searchButton"
                    >
                        🔍 Search
                    </button>

                </div>

            </div>
        </div>
    </section>


    <!-- ==================== FOUND ITEM LIST ==================== -->

    <section class="py-4">

        <div class="container">

            <?php if (empty($items)): ?>

                <div class="alert alert-info" role="status">
                    There are currently no items reported as found.
                </div>

            <?php else: ?>

                <div class="row g-4" id="itemsContainer">

                    <?php foreach ($items as $item): ?>

                        <?php
                        $itemName = $item["ItemName"] ?? "Unnamed Item";
                        $categoryName = $item["CategoryName"] ?? "Other";
                        $location = $item["Location"] ?? "Location not specified";
                        $dateOccurred = $item["DateOccurred"] ?? "";
                        $imageLink = trim($item["ImageLink"] ?? "");

                        $foundDate = $dateOccurred !== ""
                            ? date("d F Y", strtotime($dateOccurred))
                            : "Not specified";
                        ?>

                        <div
                            class="col-md-6 col-lg-4 item-card-container"
                            data-name="<?= htmlspecialchars(
                                $itemName,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            data-category="<?= htmlspecialchars(
                                $categoryName,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                            <div class="item-card h-100">

                                <!-- Item image -->
                                <div class="item-image">

                                    <?php if ($imageLink !== ""): ?>

                                        <img
                                            src="<?= htmlspecialchars(
                                                $imageLink,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            alt="<?= htmlspecialchars(
                                                $itemName,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            loading="lazy"
                                        >

                                    <?php else: ?>

                                        <span
                                            style="font-size: 16px; color: #6c757d;"
                                        >
                                            No image available
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <!-- Item information -->
                                <div class="p-3">

                                    <!-- Found status -->
                                    <span class="badge bg-success mb-2">
                                        FOUND
                                    </span>

                                    <!-- Item name -->
                                    <h5 class="fw-bold">
                                        <?= htmlspecialchars(
                                            $itemName,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </h5>

                                    <!-- Category -->
                                    <p class="text-muted mb-1">
                                        Category:
                                        <?= htmlspecialchars(
                                            $categoryName,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                    <!-- Location -->
                                    <p class="text-muted mb-1">
                                        Location:
                                        <?= htmlspecialchars(
                                            $location,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                    <!-- Date -->
                                    <p class="text-muted">
                                        Found Date:
                                        <?= htmlspecialchars(
                                            $foundDate,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                    <!-- View details button -->
                                    <a
                                        href="item-details.php?id=<?= (int) $item['ReportID'] ?>"
                                        class="btn btn-primary w-100"
                                    >
                                        View Details
                                    </a>

                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>

                </div>

                <!-- No matching results message -->
                <p
                    id="noResults"
                    class="text-muted text-center mt-4"
                    hidden
                >
                    No found items match your search.
                </p>

            <?php endif; ?>

        </div>
    </section>


    <!-- ==================== FOOTER ==================== -->

    <footer class="py-2">

        <div class="container text-center">

            <p class="fw-bold mb-1">
                UbdFind
            </p>

            <p class="small mb-1">
                Campus Lost &amp; Found System
            </p>

            <p class="small mb-1">
                Need help?
            </p>

            <p class="small mb-1">
                Email:
                <a href="mailto:lostandfound@university.edu">
                    UbdFind@ubd.edu.bn
                </a>
            </p>

            <p class="small mb-2">
                Phone: +673 246 XXXX
            </p>

            <p class="small mb-0">
                &copy; 2026 UbdFind
            </p>

        </div>
    </footer>


    <!-- ==================== SEARCH FUNCTIONALITY ==================== -->

    <script>
        const searchInput = document.getElementById("searchItem");
        const categoryFilter = document.getElementById("categoryFilter");
        const searchButton = document.getElementById("searchButton");
        const cards = document.querySelectorAll(".item-card-container");
        const noResults = document.getElementById("noResults");

        function filterItems() {
            const searchText = searchInput.value.trim().toLowerCase();
            const selectedCategory = categoryFilter.value.toLowerCase();

            let visibleCount = 0;

            cards.forEach(function(card) {
                const name = (
                    card.dataset.name || ""
                ).toLowerCase();

                const category = (
                    card.dataset.category || ""
                ).toLowerCase();

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

            if (noResults) {
                noResults.hidden = visibleCount > 0;
            }
        }

        // Search as the user types.
        searchInput.addEventListener("input", filterItems);

        // Filter when the category changes.
        categoryFilter.addEventListener("change", filterItems);

        // Search when the button is clicked.
        searchButton.addEventListener("click", filterItems);

        // Allow Enter to trigger search.
        searchInput.addEventListener("keydown", function(event) {
            if (event.key === "Enter") {
                filterItems();
            }
        });
    </script>


    <!-- Bootstrap JavaScript -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    ></script>

</body>
</html>

<?php
$conn->close();
?>
