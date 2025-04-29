<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Dashboard - 100 Days Challenge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100">

    <nav class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-6 py-3 flex justify-between items-center">
            <div class="text-2xl font-bold text-indigo-600">100 Days Challenge</div>
            <div>
                 <span class="text-gray-700 mr-4">
                    Welcome, <?= esc($firstname ?? 'User') ?>!
                 </span>
                 <a href="<?= site_url('logout') ?>" class="bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 ease-in-out inline-block">
                    Logout
                 </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto mt-10 px-6">

        <h1 class="text-3xl font-bold text-gray-800 mb-6">Your Dashboard</h1>

        <div class="bg-white p-6 rounded-lg shadow-md max-w-md mb-8">
            <h2 class="text-xl font-semibold mb-4 border-b pb-2">Strava Profile</h2>
            <div class="flex items-center space-x-4">
                <?php if (!empty($profile_picture_url)): ?>
                    <img src="<?= esc($profile_picture_url, 'attr') ?>" alt="Profile Picture" class="h-16 w-16 rounded-full border border-gray-300">
                <?php else: ?>
                    <div class="h-16 w-16 rounded-full bg-gray-300 flex items-center justify-center text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                <?php endif; ?>

                <div>
                    <p class="text-lg font-medium text-gray-900">
                        <?= esc($firstname ?? '') ?> <?= esc($lastname ?? '') ?>
                    </p>
                    <p class="text-sm text-gray-500">
                        Strava ID: <?= esc($strava_id ?? 'N/A') ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-xl font-semibold mb-4 border-b pb-2">Challenge Progress</h2>
            <p class="text-gray-600">Your synced activities and points calculation will appear here soon.</p>
            <div class="mt-4 p-4 bg-gray-100 rounded text-center text-gray-500">
                Activity data loading...
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4 border-b pb-2">Overall Standings</h2>
            <p class="text-gray-600 mb-4">Check out the public leaderboard to see how you rank!</p>
            <a href="<?= site_url('/') ?>#leaderboard" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 ease-in-out inline-block">
                View Leaderboard
            </a>
        </div>

    </div> <footer class="bg-gray-800 text-gray-400 py-8 mt-16">
        <div class="container mx-auto px-6 text-center">
            <p>&copy; <span id="current-year"></span> 100 Days Challenge. All rights reserved.</p>
            <script>
                document.getElementById('current-year').textContent = new Date().getFullYear();
            </script>
        </div>
    </footer>

</body>
</html>
