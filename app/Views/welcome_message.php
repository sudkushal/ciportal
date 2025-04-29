<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>100 Days Challenge: Walk, Run, Cycle!</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Apply Inter font if loaded */
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Simple gradient background */
        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        /* Add subtle animation */
        .fade-in {
            animation: fadeIn 1s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <nav class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-6 py-3 flex justify-between items-center">
            <div class="text-2xl font-bold text-indigo-600">100 Days Challenge</div>
            <div>
            <a href="<?= site_url('strava/login') ?>" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 ease-in-out inline-block">
    Login with Strava
</a>
            </div>
        </div>
    </nav>

    <header class="hero-gradient text-white py-20 md:py-32 fade-in">
        <div class="container mx-auto px-6 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-4 leading-tight">Join the 100 Day Challenge!</h1>
            <p class="text-lg md:text-2xl mb-8 text-indigo-100">Walk, Run, or Cycle your way to the top.</p>
            <p class="text-md md:text-lg mb-10 text-indigo-200">Connect your Strava account and let the journey begin!</p>
            <a href="#about" class="bg-white text-indigo-600 font-semibold py-3 px-8 rounded-lg hover:bg-gray-100 transition duration-300 ease-in-out">
                Learn More
            </a>
        </div>
    </header>

    <section id="about" class="py-16 bg-white">
        <div class="container mx-auto px-6 fade-in" style="animation-delay: 0.2s;">
            <h2 class="text-3xl font-bold text-center mb-10 text-gray-800">What is the Challenge?</h2>
            <div class="grid md:grid-cols-3 gap-8 text-center">
                <div class="bg-gray-50 p-6 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-300">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <h3 class="text-xl font-semibold mb-2">100 Days</h3>
                    <p class="text-gray-600">Commit to consistent activity over 100 days. Every effort counts!</p>
                </div>
                <div class="bg-gray-50 p-6 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-300">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /> </svg>
                    <h3 class="text-xl font-semibold mb-2">Walk, Run, Cycle</h3>
                    <p class="text-gray-600">Log your walks, runs, and bike rides through Strava to earn points.</p>
                </div>
                <div class="bg-gray-50 p-6 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.539 1.118l-3.975-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                    </svg>
                    <h3 class="text-xl font-semibold mb-2">Earn Points</h3>
                    <p class="text-gray-600">A unique scoring system rewards consistency and effort. See how you rank!</p>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="py-16 bg-gray-100">
        <div class="container mx-auto px-6 fade-in" style="animation-delay: 0.4s;">
            <h2 class="text-3xl font-bold text-center mb-10 text-gray-800">How It Works</h2>
            <div class="max-w-3xl mx-auto text-center space-y-6">
                <div class="flex items-center justify-center space-x-4 p-4 bg-white rounded-lg shadow">
                    <div class="bg-orange-500 text-white rounded-full h-8 w-8 flex items-center justify-center font-bold">1</div>
                    <p class="text-lg text-gray-700">Connect your Strava account (coming soon!).</p>
                </div>
                 <div class="flex items-center justify-center space-x-4 p-4 bg-white rounded-lg shadow">
                    <div class="bg-indigo-500 text-white rounded-full h-8 w-8 flex items-center justify-center font-bold">2</div>
                    <p class="text-lg text-gray-700">Your walking, running, and cycling activities are automatically synced.</p>
                </div>
                 <div class="flex items-center justify-center space-x-4 p-4 bg-white rounded-lg shadow">
                    <div class="bg-green-500 text-white rounded-full h-8 w-8 flex items-center justify-center font-bold">3</div>
                    <p class="text-lg text-gray-700">Earn points based on the challenge rules and climb the leaderboard!</p>
                </div>
            </div>
        </div>
    </section>

    <section id="leaderboard" class="py-16 bg-white">
        <div class="container mx-auto px-6 text-center fade-in" style="animation-delay: 0.6s;">
            <h2 class="text-3xl font-bold mb-6 text-gray-800">Challenge Standings</h2>
            <p class="text-lg text-gray-600 mb-8">The leaderboard will be displayed here once the challenge begins and users start syncing data.</p>
            <div class="bg-gray-200 h-64 rounded-lg flex items-center justify-center text-gray-500">
                Leaderboard Coming Soon...
            </div>
        </div>
    </section>

    <footer class="bg-gray-800 text-gray-400 py-8">
        <div class="container mx-auto px-6 text-center">
            <p>&copy; <span id="current-year"></span> 100 Days Challenge. All rights reserved.</p>
            <script>
                document.getElementById('current-year').textContent = new Date().getFullYear();
            </script>
        </div>
    </footer>

</body>
</html>
