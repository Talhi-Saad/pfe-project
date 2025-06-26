<?php
session_start();
require_once '../config/database.php';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input data
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $userType = $_POST['userType'] ?? 'client';
    $acceptTerms = isset($_POST['acceptTerms']);

    // Basic validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!$acceptTerms) {
        $error = "Vous devez accepter les conditions d'utilisation.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";
    } elseif (strlen($password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif ($password !== $confirmPassword) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        if ($userType === 'transporter') {
            $vehicleType = $_POST['vehicleType'] ?? '';
            $vehicleCapacity = $_POST['vehicleCapacity'] ?? '';
            $licenseNumber = trim($_POST['licenseNumber'] ?? '');
            $ville = '';
            if (isset($_POST['ville'])) {
                $ville = trim($_POST['ville']);
            }
            if (empty($vehicleType) || empty($vehicleCapacity) || empty($licenseNumber)) {
                $error = "Veuillez remplir tous les champs obligatoires pour les transporteurs.";
            } elseif (!is_numeric($vehicleCapacity) || $vehicleCapacity <= 0) {
                $error = "La capacité du véhicule doit être un nombre positif.";
            } elseif (strlen($licenseNumber) < 5) {
                $error = "Le numéro de permis doit contenir au moins 5 caractères.";
            }
        }
        // If no validation errors, proceed with database operations
        if (empty($error)) {
            // Check if email already exists in client, livreur, or admin
            $exists = false;
            $stmt = $conn->prepare("SELECT id_Client FROM client WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $exists = true;
            $stmt->close();
            if (!$exists) {
                $stmt = $conn->prepare("SELECT id_Livreur FROM livreur WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) $exists = true;
                $stmt->close();
            }
            if (!$exists) {
                $stmt = $conn->prepare("SELECT id_Admin FROM admin WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) $exists = true;
                $stmt->close();
            }
            if ($exists) {
                $error = "Cet email est déjà utilisé.";
            } else {
                // Hash password securely
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                if ($userType === 'transporter') {
                    // Insert into livreur
                    $stmt = $conn->prepare("INSERT INTO livreur (nom, prenom, email, mot_de_passe, telephone, ville, type_vehicule, statut) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->bind_param("sssssss", $lastName, $firstName, $email, $passwordHash, $phone, $ville, $vehicleType);
                    if ($stmt->execute()) {
                        // Get the new livreur ID
                        $livreur_id = $stmt->insert_id;
                        // Create upload folder
                        $uploadDir = __DIR__ . "/../uploads/livreur_" . $livreur_id;
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        // Handle document uploads
                        $docTypes = [
                            'driver_license' => 'permis',
                            'car_card' => 'carte_grise',
                            'car_photo' => 'photo_vehicule'
                        ];
                        foreach ($docTypes as $inputName => $typeDoc) {
                            if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
                                $tmpName = $_FILES[$inputName]['tmp_name'];
                                $originalName = basename($_FILES[$inputName]['name']);
                                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                                $safeName = $typeDoc . '_' . time() . '.' . $ext;
                                $destPath = $uploadDir . '/' . $safeName;
                                if (move_uploaded_file($tmpName, $destPath)) {
                                    // Insert into documents table
                                    $stmtDoc = $conn->prepare("INSERT INTO documents (id_Livreur, type_document, fichier_path, date_upload, statut_validation) VALUES (?, ?, ?, NOW(), 'pending')");
                                    $relPath = "uploads/livreur_" . $livreur_id . "/" . $safeName;
                                    $stmtDoc->bind_param("iss", $livreur_id, $typeDoc, $relPath);
                                    $stmtDoc->execute();
                                    $stmtDoc->close();
                                }
                            }
                        }
                        $success = "Votre compte livreur a été créé avec succès ! Il sera vérifié sous 24-48h.";
                    } else {
                        $error = "Erreur lors de la création du compte livreur: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    // Insert into client
                    $stmt = $conn->prepare("INSERT INTO client (nom, prenom, email, mot_de_passe, statut, numero) VALUES (?, ?, ?, ?, 'active', ?)");
                    $stmt->bind_param("sssss", $lastName, $firstName, $email, $passwordHash, $phone);
                    if ($stmt->execute()) {
                        $success = "Votre compte client a été créé avec succès ! Vous pouvez maintenant vous connecter.";
                    } else {
                        $error = "Erreur lors de la création du compte client: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Function to sanitize input data
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Function to validate phone number format
function validatePhoneNumber($phone) {
    // Remove all non-numeric characters except + and spaces
    $cleanPhone = preg_replace('/[^\d+\s\-\(\)]/', '', $phone);
    // Check if it matches a basic international phone format
    return preg_match('/^[+]?[0-9\s\-\(\)]{10,}$/', $cleanPhone);
}

// Function to validate password strength
function validatePasswordStrength($password) {
    // At least 8 characters, contains at least one letter and one number
    return strlen($password) >= 8 &&
           preg_match('/[A-Za-z]/', $password) &&
           preg_match('/[0-9]/', $password);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - EzyLivraison</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="./assets/css/styles.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl w-full space-y-8">
        <div>
            <div class="flex justify-center">
                <div class="flex items-center">
                    <img src="../assets/logo/logo.png" alt="Logo EzyLivraison" class="w-100 h-10">
                </div>
            </div>
            <h2 class="mt-6 text-center text-3xl font-bold text-gray-900">Rejoignez notre communauté</h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Ou 
                <a href="login.php" class="font-medium text-blue-600 hover:text-blue-500">
                    connectez-vous à votre compte existant
                </a>
            </p>
        </div>
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php elseif (!empty($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Tab Selection -->
        <div class="flex bg-gray-100 rounded-lg p-1">
            <button id="client-tab" class="flex-1 flex items-center justify-center py-3 px-4 rounded-md text-sm font-medium transition-colors bg-white text-blue-600 shadow-sm">
                <i class="fas fa-user mr-2"></i>
                Je suis un Client
            </button>
            <button id="transporter-tab" class="flex-1 flex items-center justify-center py-3 px-4 rounded-md text-sm font-medium transition-colors text-gray-600 hover:text-gray-900">
                <i class="fas fa-truck mr-2"></i>
                Je suis un Transporteur
            </button>
        </div>

        <form id="signup-form" class="mt-8 space-y-6 bg-white rounded-lg shadow-md p-8" method="post" autocomplete="off" enctype="multipart/form-data">
            <input type="hidden" name="userType" id="userType" value="client">
            <div class="space-y-4">
                <!-- Common Fields -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="firstName" class="block text-sm font-medium text-gray-700">Prénom *</label>
                        <input id="firstName" name="firstName" type="text" required 
                               class="mt-1 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               placeholder="Jean">
                    </div>
                    <div>
                        <label for="lastName" class="block text-sm font-medium text-gray-700">Nom *</label>
                        <input id="lastName" name="lastName" type="text" required 
                               class="mt-1 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               placeholder="Dupont">
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Adresse email *</label>
                    <input id="email" name="email" type="email" autocomplete="email" required 
                           class="mt-1 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                           placeholder="jean.dupont@email.com">
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">Téléphone *</label>
                    <input id="phone" name="phone" type="tel" required 
                           class="mt-1 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                           placeholder="+212 6 12 34 56 78">
                </div>

                <!-- Transporter Specific Fields -->
                <div id="transporter-fields" class="border-t border-gray-200 pt-6 hidden">
                    <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-car text-blue-600 mr-2"></i>
                        Informations véhicule
                    </h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="vehicleType" class="block text-sm font-medium text-gray-700">Type de véhicule *</label>
                            <select id="vehicleType" name="vehicleType" 
                                    class="mt-1 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Sélectionnez</option>
                                <option value="car">Voiture</option>
                                <option value="van">Camionnette</option>
                                <option value="truck">Camion</option>
                                <option value="motorcycle">Moto</option>
                            </select>
                        </div>
                        <div>
                            <label for="vehicleCapacity" class="block text-sm font-medium text-gray-700">Capacité (kg) *</label>
                            <input id="vehicleCapacity" name="vehicleCapacity" type="number" 
                                   class="mt-1 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                   placeholder="500">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="licenseNumber" class="block text-sm font-medium text-gray-700">Numéro de permis *</label>
                        <input id="licenseNumber" name="licenseNumber" type="text" 
                               class="mt-1 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               placeholder="123456789">
                    </div>

                    <div class="mt-4">
                        <label for="experience" class="block text-sm font-medium text-gray-700">Expérience en transport</label>
                        <textarea id="experience" name="experience" rows="3" 
                                  class="mt-1 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                  placeholder="Décrivez votre expérience en transport..."></textarea>
                    </div>

                    <div class="mt-4">
                        <label for="driver_license" class="block text-sm font-medium text-gray-700">Permis de conduire *</label>
                        <input id="driver_license" name="driver_license" type="file" accept=".jpg,.jpeg,.png,.pdf"
                               class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="mt-4">
                        <label for="car_card" class="block text-sm font-medium text-gray-700">Carte grise du véhicule *</label>
                        <input id="car_card" name="car_card" type="file" accept=".jpg,.jpeg,.png,.pdf"
                               class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="mt-4">
                        <label for="car_photo" class="block text-sm font-medium text-gray-700">Photo du véhicule *</label>
                        <input id="car_photo" name="car_photo" type="file" accept=".jpg,.jpeg,.png"
                               class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Mot de passe *</label>
                    <div class="mt-1 relative">
                        <input id="password" name="password" type="password" autocomplete="new-password" required 
                               class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               placeholder="Minimum 8 caractères">
                        <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-eye text-gray-400"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label for="confirmPassword" class="block text-sm font-medium text-gray-700">Confirmer le mot de passe *</label>
                    <div class="mt-1 relative">
                        <input id="confirmPassword" name="confirmPassword" type="password" autocomplete="new-password" required 
                               class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               placeholder="Répétez votre mot de passe">
                        <button type="button" id="toggle-confirm-password" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-eye text-gray-400"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex items-center">
                <input id="acceptTerms" name="acceptTerms" type="checkbox" required 
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="acceptTerms" class="ml-2 block text-sm text-gray-900">
                    J'accepte les 
                    <a href="#" class="text-blue-600 hover:text-blue-500">conditions d'utilisation</a>
                    et la 
                    <a href="#" class="text-blue-600 hover:text-blue-500">politique de confidentialité</a>
                </label>
            </div>

            <div>
                <button type="submit" id="submit-btn" 
                        class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <i class="fas fa-user mr-2"></i>
                    Créer mon compte client
                </button>
            </div>

            <div id="transporter-notice" class="bg-blue-50 border border-blue-200 rounded-lg p-4 hidden">
                <div class="flex items-start">
                    <i class="fas fa-shield-alt text-blue-600 mt-0.5 mr-3"></i>
                    <div class="text-sm text-blue-800">
                        <p class="font-medium">Vérification requise</p>
                        <p class="mt-1">
                            Votre compte sera vérifié sous 24-48h. Vous recevrez un email de confirmation une fois approuvé.
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="../assets/js/signup.js"></script>
</body>
</html>
