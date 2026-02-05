<?php
require_once 'db.php';

echo "Creating 'avatars' table...\n";

$sql = "CREATE TABLE IF NOT EXISTS avatars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emoji VARCHAR(10) NOT NULL UNIQUE
)";

if (mysqli_query($conn, $sql)) {
    echo "Table 'avatars' created/checked.\n";
    
    // Seed default avatars if empty
    $check = mysqli_query($conn, "SELECT COUNT(*) as c FROM avatars");
    $row = mysqli_fetch_assoc($check);
    if ($row['c'] == 0) {
        $defaults = ['🐱', '🐶', '🦁', '🦊', '🐸', '🐼', '🐨', '🐷', '🐵', '🦄', '🐙', '👾', '🤖', '👽', '👻', '🤡'];
        foreach ($defaults as $av) {
            $av = mysqli_real_escape_string($conn, $av);
            mysqli_query($conn, "INSERT IGNORE INTO avatars (emoji) VALUES ('$av')");
        }
        echo "Seeded default avatars.\n";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}
?>
