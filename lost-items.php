
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

// Retrieve lost items from the database that has ben recorded as lost and not yet found. The query joins the item_report, item_profile, and category tables to get the necessary information about each lost item. It also retrieves the most recent image for each item from the item_image table.
$reportType = "LOST";

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost Items | UbdFind</title>
</head>
<body>

    <h1>Lost Items page is working!</h1>

</body>
</html>
