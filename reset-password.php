<?php
$token = $_GET["token"] ?? "";
$type = $_GET["type"] ?? "";

if (empty($token) || empty($type)) {
    die("Invalid or missing token/type parameter.");
}

$token_hash = hash("sha256", $token);

$mysqli = require __DIR__ . "/db/config.php";

// Determine table based on user type
$table = ($type === "admin") ? "admins" : "credentials";

$sql = "SELECT * FROM $table WHERE reset_token = ?";

$stmt = $mysqli->prepare($sql);

$stmt->bind_param("s", $token_hash);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

if ($user === null) {
    die("Token not found.");
}

if (strtotime($user["token_expiry"]) <= time()) {
    die("Token has expired.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/water.css">
</head>
<body>
    <h1>Reset Password</h1>
    <form method="post" action="process-reset-password.php">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
        <label for="password">New password</label>
        <input type="password" id="password" name="password" required>
        <label for="password_confirmation">Repeat password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required>
        <button>Send</button>
    </form>
</body>
</html>
<?php
$stmt->close();
$mysqli->close();
?>