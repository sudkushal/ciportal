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
        /* Simple hover effect for activity rows */
        .activity-row:hover { background-color: #f9fafb; } /* gray-50 */
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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

            <div class="md:col-span-1 space-y-8">
                <div class="bg-white p-6 rounded-lg shadow-md">
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

                 <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4 border-b pb-2">Overall Standings</h2>

                    <?php if (isset($currentUserRank) && $currentUserRank !== null && isset($currentUserPoints)): ?>
                        <p class="text-gray-700 mb-1">
                            Your Rank: <span class="font-bold text-indigo-600 text-lg"><?= esc($currentUserRank) ?></span>
                        </p>
                        <p class="text-gray-700 mb-4">
                            Your Total Points: <span class="font-bold text-indigo-600 text-lg"><?= esc(number_format($currentUserPoints, 2)) ?></span>
                        </p>
                    <?php else: ?>
                         <p class="text-gray-600 mb-4">Your rank and points will appear here once you have synced activities.</p>
                    <?php endif; ?>

                    <a href="<?= site_url('/leaderboard') ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 ease-in-out inline-block w-full text-center">
                        View Full Leaderboard
                    </a>
                </div>
            </div>


            <div class="md:col-span-2 space-y-8">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4 border-b pb-2">Recent Activities</h2>

                    <?php if (!empty($activities) && is_array($activities)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Distance</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($activities as $activity): ?>
                                        <?php
                                            // Format data (same as before)
                                            $distance_km = round(($activity->distance ?? 0) / 1000, 2);
                                            $moving_time_formatted = gmdate("H:i:s", $activity->moving_time ?? 0);
                                            $activity_date = 'N/A';
                                            $dateToParse = $activity->start_date ?? null;
                                            if (!empty($dateToParse)) { $timestamp = strtotime($dateToParse); if ($timestamp !== false) { try { $dateTimeObject = new \DateTime('@' . $timestamp); $activity_date = $dateTimeObject->format('M j, Y g:i A'); } catch (\Exception $e) { log_message('error', 'Exception formatting timestamp: '.$timestamp.' | '.$e->getMessage()); $activity_date = 'Error'; } } else { log_message('warning', 'strtotime failed for date string: ' . $dateToParse); $activity_date = 'Invalid Date'; } }
                                        ?>
                                        <tr class="activity-row transition duration-150 ease-in-out">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?= esc($activity_date) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <a href="https://www.strava.com/activities/<?= esc($activity->strava_activity_id, 'attr') ?>" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:text-indigo-800" title="View on Strava">
                                                    <?= esc($activity->name) ?>
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?= esc($activity->type) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?= esc($distance_km) ?> km</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?= esc($moving_time_formatted) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600">No recent activities found or synced yet.</p>
                    <?php endif; ?>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4 border-b pb-2">Points Breakdown</h2>

                    <?php if (isset($pointsBreakdown) && !empty($pointsBreakdown)): ?>
                        <div class="space-y-4">
                            <?php foreach ($pointsBreakdown as $stageNum => $stageData): ?>
                                <?php if (!empty($stageData)): // Only show stages where points were potentially earned ?>
                                <div class="border border-gray-200 p-3 rounded">
                                    <h3 class="text-lg font-medium text-gray-800 mb-2">Stage <?= esc($stageNum) ?>: <span class="text-indigo-600"><?= esc(number_format($stageData['total_stage_points'] ?? 0, 2)) ?> pts</span></h3>
                                    <ul class="list-disc list-inside text-sm text-gray-600 space-y-1 pl-2">
                                        <?php if (isset($stageData['daily']) && $stageData['daily'] > 0): ?>
                                            <li>Daily Challenge: <?= esc(number_format($stageData['daily'], 2)) ?> pts</li>
                                        <?php endif; ?>
                                        <?php if (isset($stageData['advanced']) && $stageData['advanced'] > 0): ?>
                                            <li>Advanced Challenge: <?= esc(number_format($stageData['advanced'], 2)) ?> pts</li>
                                        <?php endif; ?>
                                        <?php if (isset($stageData['extreme']) && $stageData['extreme'] > 0): ?>
                                            <li>Extreme Challenge: <?= esc(number_format($stageData['extreme'], 2)) ?> pts</li>
                                        <?php endif; ?>
                                         <?php if (isset($stageData['distance']) && $stageData['distance'] > 0): ?>
                                            <li>Distance Challenge: <?= esc(number_format($stageData['distance'], 2)) ?> pts</li>
                                        <?php endif; ?>
                                        <?php if (empty(array_filter($stageData, fn($v) => $v > 0 && $v !== $stageData['total_stage_points']))): // Check if only total is set or all sub-challenges are 0 ?>
                                            <?php if (($stageData['total_stage_points'] ?? 0) == 0): ?>
                                                <li>No challenge points earned this stage.</li>
                                            <?php else: ?>
                                                 <li>(Breakdown details not available or points not from sub-challenges)</li>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600">Points breakdown will appear here once you complete challenge activities.</p>
                    <?php endif; ?>
                    <p class="text-xs text-gray-500 mt-4">Note: Points for Daily/Advanced challenges are awarded based on qualifying activities after meeting the minimum day requirement for the stage.</p>
                </div> </div> </div> </div> <footer class="bg-gray-800 text-gray-400 py-8 mt-16">
        <div class="container mx-auto px-6 text-center">
            <p>&copy; <span id="current-year"></span> 100 Days Challenge. All rights reserved.</p>
            <script>
                document.getElementById('current-year').textContent = new Date().getFullYear();
            </script>
        </div>
    </footer>

</body>
</html>
