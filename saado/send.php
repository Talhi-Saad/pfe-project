<?php
session_start();
require_once 'config/database.php';

$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'client';
$senderName = '';
$senderPhone = '';
$senderEmail = '';
$readonly = '';
$orderCreated = false;
$generatedPassword = '';
$clientId = null;

// Gérer la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
      // Obtenir les données du formulaire
        $fromAddress = trim($_POST['fromAddress'] ?? '');
        $toAddress = trim($_POST['toAddress'] ?? '');
        $pickupDate = $_POST['pickupDate'] ?? null;
        $deliveryDate = $_POST['deliveryDate'] ?? null;
        $description = trim($_POST['description'] ?? '');
        $weight = $_POST['weight'] ?? null;
        $dimensions = trim($_POST['dimensions'] ?? '');
        $value = $_POST['value'] ?? null;
        $fragile = isset($_POST['fragile']) ? 1 : 0;
        $senderName = trim($_POST['senderName'] ?? '');
        $senderPhone = trim($_POST['senderPhone'] ?? '');
        $senderEmail = trim($_POST['senderEmail'] ?? '');
        $recipientName = trim($_POST['recipientName'] ?? '');
        $recipientPhone = trim($_POST['recipientPhone'] ?? '');
        $recipientEmail = trim($_POST['recipientEmail'] ?? '');
        $suggestedPrice = $_POST['suggestedPrice'] ?? 0;
        $maxPrice = $_POST['maxPrice'] ?? null;

        // Validate required fields
        if (empty($fromAddress) || empty($toAddress) || empty($description) ||
            empty($senderName) || empty($senderPhone) || empty($senderEmail) ||
            empty($recipientName) || empty($recipientPhone) || empty($suggestedPrice)) {
            throw new Exception("Veuillez remplir tous les champs obligatoires.");
        }

        // Validate email format
        if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("L'adresse email de l'expéditeur n'est pas valide.");
        }

        // Validate recipient email if provided
        if (!empty($recipientEmail) && !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("L'adresse email du destinataire n'est pas valide.");
        }

        // Validate price
        if (!is_numeric($suggestedPrice) || $suggestedPrice <= 0) {
            throw new Exception("Le prix suggéré doit être un nombre positif.");
        }

        // Validate weight if provided
        if (!empty($weight) && (!is_numeric($weight) || $weight <= 0)) {
            throw new Exception("Le poids doit être un nombre positif.");
        }

        // Validate value if provided
        if (!empty($value) && (!is_numeric($value) || $value < 0)) {
            throw new Exception("La valeur déclarée doit être un nombre positif ou zéro.");
        }

        // Validate dates if provided
        if (!empty($pickupDate) && strtotime($pickupDate) < strtotime('today')) {
            throw new Exception("La date de récupération ne peut pas être dans le passé.");
        }

        if (!empty($deliveryDate) && !empty($pickupDate) && strtotime($deliveryDate) < strtotime($pickupDate)) {
            throw new Exception("La date de livraison doit être après la date de récupération.");
        }

        $conn->begin_transaction();

        // Check if client exists or create new one
        if ($isLoggedIn) {
            $clientId = $_SESSION['user_id'];
        } else {
            // Check if client already exists by email
            $stmt = $conn->prepare("SELECT id_Client FROM client WHERE email = ?");
            $stmt->bind_param("s", $senderEmail);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $clientId = $row['id_Client'];
            } else {
                // Create new client account
                $generatedPassword = generateRandomPassword();
                $hashedPassword = password_hash($generatedPassword, PASSWORD_DEFAULT);

                // Split sender name into first and last name
                $nameParts = explode(' ', $senderName, 2);
                $prenom = $nameParts[0];
                $nom = isset($nameParts[1]) ? $nameParts[1] : '';

                $stmt = $conn->prepare("INSERT INTO client (nom, prenom, numero, email, mot_de_passe, statut) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmt->bind_param("sssss", $nom, $prenom, $senderPhone, $senderEmail, $hashedPassword);
                $stmt->execute();
                $clientId = $conn->insert_id;
            }
            $stmt->close();
        }

        // Create the order in commande table using proper columns
        // Clean delivery address - only the actual address
        $cleanDeliveryAddress = $toAddress;
        $cleanPickupAddress = $fromAddress;

        // Convert dates to proper format
        $pickupDateFormatted = !empty($pickupDate) ? date('Y-m-d', strtotime($pickupDate)) : null;
        $deliveryDateFormatted = !empty($deliveryDate) ? date('Y-m-d', strtotime($deliveryDate)) : null;

        // Basic distance and duration estimation (simplified)
        $estimatedDistance = null;
        $estimatedDuration = null;

        if (!empty($suggestedPrice)) {
            // Rough estimation: 1MAD per km + base cost
            $estimatedDistance = max(1, ($suggestedPrice - 10) / 1.5); // Assuming 1.5MAD/km + 10MAD base
            $estimatedDuration = $estimatedDistance * 2; // Assuming 30km/h average speed = 2 minutes per km
        }

        $stmt = $conn->prepare("
            INSERT INTO commande (
                date_commande,
                adresse_livraison,
                adresse_depart,
                nom_destinataire,
                telephone_destinataire,
                email_destinataire,
                description_colis,
                poids,
                dimensions,
                valeur_declaree,
                fragile,
                date_ramassage_souhaitee,
                date_livraison_souhaitee,
                statu,
                id_Client,
                prix_suggere,
                distance_estimee,
                duree_estimee
            ) VALUES (
                CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?
            )
        ");

        $stmt->bind_param(
            "ssssssdsdissiddi",
            $cleanDeliveryAddress,      // adresse_livraison
            $cleanPickupAddress,        // adresse_depart
            $recipientName,             // nom_destinataire
            $recipientPhone,            // telephone_destinataire
            $recipientEmail,            // email_destinataire
            $description,               // description_colis
            $weight,                    // poids
            $dimensions,                // dimensions
            $value,                     // valeur_declaree
            $fragile,                   // fragile
            $pickupDateFormatted,       // date_ramassage_souhaitee
            $deliveryDateFormatted,     // date_livraison_souhaitee
            $clientId,                  // id_Client
            $suggestedPrice,            // prix_suggere
            $estimatedDistance,         // distance_estimee
            $estimatedDuration          // duree_estimee
        );
        $stmt->execute();
        $orderId = $conn->insert_id;
        $stmt->close();

        $conn->commit();
        $orderCreated = true;

    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Get user info if logged in
if ($isLoggedIn) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT prenom, nom, numero, email FROM client WHERE id_Client = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($prenom, $nom, $numero, $email);
    if ($stmt->fetch()) {
        $senderName = htmlspecialchars($prenom . ' ' . $nom);
        $senderPhone = htmlspecialchars($numero ?? '');
        $senderEmail = htmlspecialchars($email);
        $readonly = 'readonly';
    }
    $stmt->close();
}

// Function to generate random password
function generateRandomPassword($length = 8) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoyer un colis - EzyLivraison</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="./assets/css/styles.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="index.php" class="flex items-center">
                    <img src="./assets/logo/logo.png" alt="Logo EzyLivraison" class="w-100 h-10">
                </a>
                <div class="flex items-center space-x-4">
                    <?php if ($isLoggedIn): ?>
                        <a href="dashboard/" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">
                            <i class="fas fa-tachometer-alt mr-1"></i>Tableau de bord
                        </a>
                        <a href="auth/logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            <i class="fas fa-sign-out-alt mr-1"></i>Déconnexion
                        </a>
                    <?php else: ?>
                        <a href="./auth/login.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Connexion</a>
                        <a href="./auth/signup.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">S'inscrire</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <?php if ($orderCreated): ?>
            <!-- Success Page -->
            <div class="text-center mb-12">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-6">
                    <i class="fas fa-check text-green-600 text-2xl"></i>
                </div>
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Commande créée avec succès !</h1>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Votre demande de livraison a été publiée. Les transporteurs vont pouvoir faire leurs offres.
                </p>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-8 space-y-6">
                <div class="text-center">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-4">Commande #<?= $orderId ?></h2>
                    <p class="text-gray-600">Vous recevrez des notifications par email lorsque des transporteurs feront des offres.</p>
                </div>

                <!-- Order Summary -->
                <div class="bg-gray-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Récapitulatif de votre commande</h3>
                    <div class="grid md:grid-cols-2 gap-6 text-sm">
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Itinéraire</h4>
                            <p class="text-gray-600">
                                <i class="fas fa-map-marker-alt text-green-600 mr-2"></i>
                                <strong>Départ:</strong> <?= htmlspecialchars($_POST['fromAddress'] ?? '') ?>
                            </p>
                            <p class="text-gray-600 mt-1">
                                <i class="fas fa-map-marker-alt text-red-600 mr-2"></i>
                                <strong>Arrivée:</strong> <?= htmlspecialchars($_POST['toAddress'] ?? '') ?>
                            </p>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Prix et détails</h4>
                            <p class="text-gray-600">
                                <strong>Prix suggéré:</strong> <?= number_format($suggestedPrice, 2) ?>MAD
                            </p>
                            <?php if (!empty($_POST['weight'])): ?>
                                <p class="text-gray-600"><strong>Poids:</strong> <?= htmlspecialchars($_POST['weight']) ?> kg</p>
                            <?php endif; ?>
                            <?php if (!empty($_POST['dimensions'])): ?>
                                <p class="text-gray-600"><strong>Dimensions:</strong> <?= htmlspecialchars($_POST['dimensions']) ?> cm</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($generatedPassword)): ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                        <div class="flex items-center mb-4">
                            <i class="fas fa-key text-blue-600 text-xl mr-3"></i>
                            <h3 class="text-lg font-semibold text-blue-900">Compte créé automatiquement</h3>
                        </div>
                        <p class="text-blue-800 mb-4">
                            Un compte client a été créé pour vous avec les informations fournies.
                            Voici vos identifiants de connexion :
                        </p>
                        <div class="bg-white rounded border p-4 space-y-2">
                            <div><strong>Email :</strong> <?= htmlspecialchars($senderEmail) ?></div>
                            <div><strong>Mot de passe :</strong>
                                <span class="font-mono bg-gray-100 px-2 py-1 rounded text-lg"><?= htmlspecialchars($generatedPassword) ?></span>
                            </div>
                        </div>
                        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                            <p class="text-yellow-800 text-sm">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <strong>Important :</strong> Notez bien ce mot de passe, il ne sera plus affiché.
                                Vous pourrez le changer après votre première connexion.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="grid md:grid-cols-2 gap-6">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 mb-2">Prochaines étapes</h4>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>• Les transporteurs vont consulter votre demande</li>
                            <li>• Vous recevrez leurs offres par email</li>
                            <li>• Connectez-vous pour choisir le meilleur transporteur</li>
                            <li>• Suivez votre colis en temps réel</li>
                        </ul>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 mb-2">Besoin d'aide ?</h4>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>• Consultez notre FAQ</li>
                            <li>• Contactez notre support</li>
                            <li>• Suivez vos commandes en ligne</li>
                        </ul>
                    </div>
                </div>

                <div class="text-center space-x-4">
                    <a href="dashboard/" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                        <i class="fas fa-tachometer-alt mr-2"></i>
                        Accéder au tableau de bord
                    </a>
                    <a href="send.php" class="inline-flex items-center px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium">
                        <i class="fas fa-plus mr-2"></i>
                        Nouvelle commande
                    </a>
                </div>
            </div>

        <?php elseif (isset($error)): ?>
            <!-- Error Message -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-8">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-600 text-xl mr-3"></i>
                    <div>
                        <h3 class="text-lg font-semibold text-red-900">Erreur</h3>
                        <p class="text-red-800"><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            </div>

            <!-- Hero Section -->
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Envoyez votre colis</h1>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Trouvez un transporteur de confiance pour livrer votre colis partout en Maroc à petit prix
                </p>
            </div>

        <?php else: ?>
            <!-- Hero Section -->
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Envoyez votre colis</h1>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Trouvez un transporteur de confiance pour livrer votre colis partout en Maroc à petit prix
                </p>
            </div>
        <?php endif; ?>

        <?php if (!$orderCreated): ?>
        <!-- Progress Steps -->
        <div class="mb-12">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div id="step-1-icon" class="flex items-center justify-center w-12 h-12 rounded-full border-2 bg-blue-600 border-blue-600 text-white">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="ml-4 hidden sm:block">
                        <p class="text-sm font-medium text-blue-600">Étape 1</p>
                        <p class="text-sm text-gray-900">Détails du colis</p>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400 mx-4 hidden sm:block"></i>
                </div>
                <div class="flex items-center">
                    <div id="step-2-icon" class="flex items-center justify-center w-12 h-12 rounded-full border-2 border-gray-300 text-gray-500">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="ml-4 hidden sm:block">
                        <p class="text-sm font-medium text-gray-500">Étape 2</p>
                        <p class="text-sm text-gray-500">Expéditeur</p>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400 mx-4 hidden sm:block"></i>
                </div>
                <div class="flex items-center">
                    <div id="step-3-icon" class="flex items-center justify-center w-12 h-12 rounded-full border-2 border-gray-300 text-gray-500">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="ml-4 hidden sm:block">
                        <p class="text-sm font-medium text-gray-500">Étape 3</p>
                        <p class="text-sm text-gray-500">Destinataire</p>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400 mx-4 hidden sm:block"></i>
                </div>
                <div class="flex items-center">
                    <div id="step-4-icon" class="flex items-center justify-center w-12 h-12 rounded-full border-2 border-gray-300 text-gray-500">
                        MAD
                    </div>
                    <div class="ml-4 hidden sm:block">
                        <p class="text-sm font-medium text-gray-500">Étape 4</p>
                        <p class="text-sm text-gray-500">Prix et confirmation</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="bg-white rounded-lg shadow-lg p-8">
            <form id="send-form" method="post" autocomplete="off" novalidate>
                <!-- Step 1: Package Details -->
                <div id="step-1" class="space-y-6">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-6">Détails du colis</h2>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adresse de départ *</label>
                            <div class="relative">
                                <i class="fas fa-map-marker-alt absolute left-3 top-3 text-gray-400"></i>
                                <input type="text" name="fromAddress" required 
                                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       placeholder="Ville, code postal">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adresse de destination *</label>
                            <div class="relative">
                                <i class="fas fa-map-marker-alt absolute left-3 top-3 text-gray-400"></i>
                                <input type="text" name="toAddress" required 
                                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       placeholder="Ville, code postal">
                            </div>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date de récupération souhaitée</label>
                            <div class="relative">
                                <i class="fas fa-calendar absolute left-3 top-3 text-gray-400"></i>
                                <input type="date" name="pickupDate" 
                                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date de livraison souhaitée</label>
                            <div class="relative">
                                <i class="fas fa-calendar absolute left-3 top-3 text-gray-400"></i>
                                <input type="date" name="deliveryDate" 
                                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description du colis *</label>
                        <textarea name="description" required rows="4" 
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                  placeholder="Décrivez votre colis (contenu, taille, poids approximatif...)"></textarea>
                    </div>

                    <div class="grid md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Poids approximatif (kg)</label>
                            <input type="number" name="weight" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                   placeholder="5">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Dimensions (cm)</label>
                            <input type="text" name="dimensions" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                   placeholder="30x20x15">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Valeur déclarée (MAD)</label>
                            <input type="number" name="value" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                   placeholder="100">
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" name="fragile" id="fragile" 
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="fragile" class="ml-2 block text-sm text-gray-900">
                            Colis fragile (nécessite une attention particulière)
                        </label>
                    </div>
                </div>

                <!-- Step 2: Sender Details -->
                <div id="step-2" class="space-y-6 hidden">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-6">Informations expéditeur</h2>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nom complet *</label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-3 top-3 text-gray-400"></i>
                            <input type="text" name="senderName" required value="<?= $senderName ?>" <?= $readonly ?>
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Jean Dupont">
                        </div>
                    </div>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone *</label>
                            <div class="relative">
                                <i class="fas fa-phone absolute left-3 top-3 text-gray-400"></i>
                                <input type="tel" name="senderPhone" required value="<?= $senderPhone ?>" <?= $readonly ?>
                                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="+212 6 12 34 56 78">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                            <div class="relative">
                                <i class="fas fa-envelope absolute left-3 top-3 text-gray-400"></i>
                                <input type="email" name="senderEmail" required value="<?= $senderEmail ?>" <?= $readonly ?>
                                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="jean.dupont@email.com">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Recipient Details -->
                <div id="step-3" class="space-y-6 hidden">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-6">Informations destinataire</h2>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nom complet *</label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-3 top-3 text-gray-400"></i>
                            <input type="text" name="recipientName" required 
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                   placeholder="Marie Martin">
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone *</label>
                            <div class="relative">
                                <i class="fas fa-phone absolute left-3 top-3 text-gray-400"></i>
                                <input type="tel" name="recipientPhone" required 
                                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       placeholder="+212 6 12 34 56 78">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <div class="relative">
                                <i class="fas fa-envelope absolute left-3 top-3 text-gray-400"></i>
                                <input type="email" name="recipientEmail" 
                                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       placeholder="marie.martin@email.com">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Pricing and Confirmation -->
                <div id="step-4" class="space-y-6 hidden">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-6">Prix et confirmation</h2>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Prix suggéré (MAD) *</label>
                            <div class="relative">
                                <i class="absolute left-3 top-3 text-gray-400"></i>
                                <input type="number" name="suggestedPrice" required 
                                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       placeholder="45">
                            </div>
                            <p class="text-sm text-gray-500 mt-1">Prix que vous proposez aux transporteurs</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Prix maximum accepté (MAD)</label>
                            <div class="relative">
                                <i class="fas absolute left-3 top-3 text-gray-400">MAD</i>
                                <input type="number" name="maxPrice" 
                                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       placeholder="60">
                            </div>
                            <p class="text-sm text-gray-500 mt-1">Prix maximum que vous êtes prêt à payer</p>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Récapitulatif de votre envoi</h3>
                        <div id="summary" class="space-y-3 text-sm">
                            <!-- Summary will be populated by JavaScript -->
                        </div>
                    </div>

                    <!-- Benefits -->
                    <div class="grid md:grid-cols-3 gap-4">
                        <div class="flex items-center p-4 bg-blue-50 rounded-lg">
                            <i class="fas fa-shield-alt text-blue-600 text-2xl mr-3"></i>
                            <div>
                                <p class="font-medium text-blue-900">Sécurisé</p>
                                <p class="text-sm text-blue-700">Transporteurs vérifiés</p>
                            </div>
                        </div>
                        <div class="flex items-center p-4 bg-green-50 rounded-lg">
                            <i class="fas fa-clock text-green-600 text-2xl mr-3"></i>
                            <div>
                                <p class="font-medium text-green-900">Rapide</p>
                                <p class="text-sm text-green-700">Livraison express</p>
                            </div>
                        </div>
                        <div class="flex items-center p-4 bg-purple-50 rounded-lg">
                            <i class="fas fa-check-circle text-purple-600 text-2xl mr-3"></i>
                            <div>
                                <p class="font-medium text-purple-900">Suivi</p>
                                <p class="text-sm text-purple-700">Temps réel</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex justify-between pt-8 border-t border-gray-200">
                    <button type="button" id="prev-btn" 
                            class="px-6 py-3 rounded-lg font-medium bg-gray-100 text-gray-400 cursor-not-allowed" 
                            disabled>
                        Précédent
                    </button>

                    <button type="button" id="next-btn" 
                            class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                        Suivant
                    </button>

                    <button type="submit" id="submit-btn" 
                            class="px-8 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium items-center hidden">
                        <i class="fas fa-truck mr-2"></i>
                        Publier ma demande
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script src="./assets/js/send.js"></script>
</body>
</html>
