<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login with Strava</title>
</head>
<body>

<h2>Login</h2>

<?php if (session()->getFlashdata('error')): ?>
    <div style="color:red;">
        <?= session()->getFlashdata('error') ?>
    </div>
<?php endif; ?>

<a href="<?= base_url('strava-redirect') ?>">
    <img src="https://developers.strava.com/assets/img/connect_with_strava.png" alt="Login with Strava">
</a>

</body>
</html>
