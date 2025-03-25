<?php
require_once 'includes/db.php';

// Delete all workflows
$deleteStmt = $conn->prepare("DELETE FROM workflows");

if ($deleteStmt->execute()) {
    echo "All workflows have been deleted successfully.";
} else {
    echo "Error deleting workflows: " . $conn->error;
}

$deleteStmt->close();
$conn->close();

echo "\nAll workflows have been deleted from the database.";
?>
