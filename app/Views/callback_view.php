<!-- /app/Views/callback_view.php -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Callback Received</title>
</head>
<body>
    <h1>Callback Data</h1>
    <p>State: <?= esc($state) ?></p>
    <p>Code: <?= esc($code) ?></p>
    <p>Scope: <?= esc($scope) ?></p>
</body>
</html>
