<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EzyLivraison - Plateforme de Livraison Collaborative</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="./assets/css/styles.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-white">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex items-center">
                            <img src="./assets/logo/logo.png" alt="Logo EzyLivraison" class="w-100 h-10">
                        </div>
                    </div>
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden md:flex space-x-8">
                    <a href="index.html" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Accueil</a>
                    <a href="#how-it-works" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Comment ça marche</a>
                    <a href="#transporter" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Devenir transporteur</a>
                </nav>

                <div class="hidden md:flex items-center space-x-4">
                    <a href="./auth/login.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Connexion</a>
                    <a href="./auth/signup.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">S'inscrire</a>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button id="mobile-menu-btn" class="text-gray-700 hover:text-blue-600">
                        <i class="fas fa-bars h-6 w-6"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <div id="mobile-menu" class="md:hidden bg-white border-t border-gray-100 hidden">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="index.html" class="block px-3 py-2 text-gray-700 hover:text-blue-600">Accueil</a>
                <a href="#how-it-works" class="block px-3 py-2 text-gray-700 hover:text-blue-600">Comment ça marche</a>
                <a href="#transporter" class="block px-3 py-2 text-gray-700 hover:text-blue-600">Devenir transporteur</a>
                <a href="login.html" class="block px-3 py-2 text-gray-700 hover:text-blue-600">Connexion</a>
                <a href="signup.html" class="block px-3 py-2 bg-blue-600 text-white rounded-lg mx-3 text-center">S'inscrire</a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-50 to-indigo-100 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6">
                    Envoyez ou transportez
                    <span class="text-blue-600 block">tout en Maroc</span>
                    <span class="text-2xl md:text-4xl font-normal text-gray-600 block mt-2">à petit prix</span>
                </h1>
                <p class="text-xl text-gray-600 mb-8 max-w-3xl mx-auto">
                Envoyez (presque) tout, partout en Maroc grâce à des trajets déjà prévus . Bienvenue sur EzyLivraison !
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="send.php" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-lg text-lg font-medium transition-colors inline-flex items-center justify-center">
                        Envoyer un colis
                        <i class="fas fa-chevron-right ml-2"></i>
                    </a>
                    <a href="#how-it-works" class="border border-gray-300 hover:border-gray-400 text-gray-700 px-8 py-4 rounded-lg text-lg font-medium transition-colors">
                        En savoir plus
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- How it works -->
    <section id="how-it-works" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Comment ça marche</h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Trois étapes simples pour envoyer ou transporter vos colis
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="bg-blue-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-box text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">1. Publiez</h3>
                    <p class="text-gray-600">
                        Décrivez votre colis, indiquez les points de départ et d'arrivée, et fixez votre prix.
                    </p>
                </div>

                <div class="text-center">
                    <div class="bg-green-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-users text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">2. Connectez</h3>
                    <p class="text-gray-600">
                        Recevez des propositions de transporteurs vérifiés et choisissez celui qui vous convient.
                    </p>
                </div>

                <div class="text-center">
                    <div class="bg-purple-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-truck text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">3. Livrez</h3>
                    <p class="text-gray-600">
                        Suivez votre colis en temps réel et recevez une confirmation de livraison.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Ce que disent nos utilisateurs</h2>
            </div>

            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-2xl shadow-lg p-8 text-center">
                    <div id="testimonial-stars" class="flex justify-center mb-4">
                        <!-- Stars will be populated by JavaScript -->
                    </div>
                    <p id="testimonial-content" class="text-lg text-gray-600 mb-6 italic"></p>
                    <div>
                        <p id="testimonial-name" class="font-semibold text-gray-900"></p>
                        <p id="testimonial-role" class="text-gray-500"></p>
                    </div>
                </div>

                <div id="testimonial-dots" class="flex justify-center mt-8 space-x-2">
                    <!-- Dots will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        <img src="./assets/logo/logo.png" alt="Logo EzyLivraison" class="w-100 h-10">
                    </div>
                    <p class="text-gray-400">
                        La plateforme de livraison collaborative qui connecte expéditeurs et transporteurs.
                    </p>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-4">Liens rapides</h3>
                    <ul class="space-y-2">
                        <li><a href="index.html" class="text-gray-400 hover:text-white">Accueil</a></li>
                        <li><a href="#how-it-works" class="text-gray-400 hover:text-white">Comment ça marche</a></li>
                        <li><a href="#transporter" class="text-gray-400 hover:text-white">Devenir transporteur</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-4">Support</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white">Centre d'aide</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Contact</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">FAQ</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-4">Légal</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white">Conditions d'utilisation</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Politique de confidentialité</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Mentions légales</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-800 mt-8 pt-8 text-center">
                <p class="text-gray-400">© 2025 EzyLivraison. Tous droits réservés.</p>
            </div>
        </div>
    </footer>
    <script src="./assets/js/main.js"></script>
</body>
</html>
