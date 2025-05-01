<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Points Leaderboard - 100 Days Challenge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .rank-1 { background-color: #fffbeb; } /* yellow-50 */
        .rank-2 { background-color: #f9fafb; } /* gray-50 */
        .rank-3 { background-color: #fff7ed; } /* orange-50 */
        .leaderboard-row:hover { background-color: #f3f4f6; } /* gray-100 */
    </style>
</head>
<body class="bg-gray-100">

    <nav class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-6 py-3 flex justify-between items-center">
            <a href="<?= site_url('/') ?>" class="text-2xl font-bold text-indigo-600">100 Days Challenge</a>
            <div>
                <?php if (session()->get('isLoggedIn')): ?>
                    <a href="<?= site_url('dashboard') ?>" class="text-gray-600 hover:text-indigo-600 mr-4">Dashboard</a>
                    <a href="<?= site_url('logout') ?>" class="bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 ease-in-out inline-block">
                        Logout
                    </a>
                <?php else: ?>
                     <a href="<?= site_url('login') ?>" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 ease-in-out inline-block">
                        Login with Strava
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mx-auto mt-10 px-6">

        <h1 class="text-3xl font-bold text-gray-800 mb-2 text-center">Challenge Leaderboard</h1>
        <p class="text-center text-gray-600 mb-8">Points based on Walk/Run (5 pts/km) and Ride (1.25 pts/km)</p>

        <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md max-w-3xl mx-auto">

            <?php if (session()->has('error')): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline"><?= esc(session('error')) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($leaderboardData) && is_array($leaderboardData)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">Rank</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Points</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($leaderboardData as $index => $data): ?>
                                <?php
                                    $rank = $index + 1;
                                    $rankClass = '';
                                    if ($rank === 1) $rankClass = 'rank-1 font-semibold';
                                    if ($rank === 2) $rankClass = 'rank-2';
                                    if ($rank === 3) $rankClass = 'rank-3';
                                ?>
                                <tr class="leaderboard-row transition duration-150 ease-in-out <?= $rankClass ?>">
                                    <td class="px-4 py-4 whitespace-nowrap text-center text-sm text-gray-700"><?= $rank ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= esc($data['user']->firstname ?? 'Unknown') ?> <?= esc($data['user']->lastname ?? '') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 text-right font-medium">
                                        <?= esc(number_format($data['total_points'], 2)) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600 text-center py-10">No leaderboard data available yet. Get moving!</p>
            <?php endif; ?>

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
