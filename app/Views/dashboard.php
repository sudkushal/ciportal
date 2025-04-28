<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Welcome to your Dashboard, <?= esc($user['firstname']) ?> <?= esc($user['lastname']) ?>!</h1>

        <div class="row mt-4">
            <div class="col-md-4">
                <img src="<?= esc($user['profile_pic']) ?>" alt="Profile Picture" class="img-fluid rounded-circle">
            </div>
            <div class="col-md-8">
                <h3>Profile Information:</h3>
                <ul>
                    <li><strong>First Name:</strong> <?= esc($user['firstname']) ?></li>
                    <li><strong>Last Name:</strong> <?= esc($user['lastname']) ?></li>
                    <li><strong>Strava ID:</strong> <?= esc($user['strava_id']) ?></li>
                </ul>
            </div>
        </div>

        <hr>

        <h3>Your Recent Activities:</h3>
        <?php if (!empty($activities)): ?>
            <ul>
                <?php foreach ($activities as $activity): ?>
                    <li>
                        <strong><?= esc($activity['name']) ?></strong><br>
                        <?= esc(date('Y-m-d H:i', strtotime($activity['start_date']))) ?><br>
                        Type: <?= esc($activity['type']) ?><br>
                        Distance: <?= esc($activity['distance']) / 1000 ?> km<br>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No activities found.</p>
        <?php endif; ?>

        <hr>

        <a href="/logout" class="btn btn-danger">Logout</a>
    </div>

    <!-- Optional: Add any JavaScript libraries if needed -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
