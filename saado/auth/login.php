<?php
session_start();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/database.php';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        // Try client
        $stmt = $conn->prepare("SELECT id_Client, nom, prenom, email, mot_de_passe, statut FROM client WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['mot_de_passe'])) {
                // For client
                if ($user['statut'] !== 'active') {
                    $error = "Votre compte client n'est pas actif.";
                } else {
                    $_SESSION['user_id'] = $user['id_Client'];
                    $_SESSION['user_type'] = 'client';
                    $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
                    header('Location: ../dashboard/index.php');
                    exit;
                }
            } else {
                $error = "Email ou mot de passe incorrect.";
            }
        } else {
            // Try livreur
            $stmt = $conn->prepare("SELECT id_Livreur, nom, prenom, email, mot_de_passe, statut FROM livreur WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['mot_de_passe'])) {
                    // For livreur
                    if ($user['statut'] !== 'verified') {
                        $error = "Votre compte livreur n'est pas actif ou en attente de vérification.";
                    } else {
                        $_SESSION['user_id'] = $user['id_Livreur'];
                        $_SESSION['user_type'] = 'livreur';
                        $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
                        header('Location: ../dashboard/index.php');
                        exit;
                    }
                } else {
                    $error = "Email ou mot de passe incorrect.";
                }
            } else {
                // Try admin
                $stmt = $conn->prepare("SELECT id_Admin, email, mot_de_passe FROM admin WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['mot_de_passe'])) {
                        $_SESSION['user_id'] = $user['id_Admin'];
                        $_SESSION['user_type'] = 'admin';
                        $_SESSION['user_name'] = $user['email'];
                        header('Location: ../admin/index.php');
                        exit;
                    } else {
                        $error = "Email ou mot de passe incorrect.";
                    }
                } else {
                    $error = "Email ou mot de passe incorrect.";
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - EzyLivraison</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="./assets/css/styles.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <div class="flex justify-center">
                <div class="flex items-center">
                    <img src="../assets/logo/logo.png" alt="Logo EzyLivraison" class="w-100 h-10">
                </div>
            </div>
            <h2 class="mt-6 text-center text-3xl font-bold text-gray-900">Connectez-vous à votre compte</h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Ou 
                <a href="signup.php" class="font-medium text-blue-600 hover:text-blue-500">
                    créez un nouveau compte
                </a>
            </p>
        </div>
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <form id="login-form" class="mt-8 space-y-6" method="post" autocomplete="off">
            <div class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Adresse email</label>
                    <input id="email" name="email" type="email" autocomplete="email" required 
                           class="mt-1 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                           placeholder="votre@email.com">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Mot de passe</label>
                    <div class="mt-1 relative">
                        <input id="password" name="password" type="password" autocomplete="current-password" required 
                               class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               placeholder="Votre mot de passe">
                        <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-eye text-gray-400"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input id="remember-me" name="remember-me" type="checkbox" 
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="remember-me" class="ml-2 block text-sm text-gray-900">Se souvenir de moi</label>
                </div>

                <div class="text-sm">
                    <a href="#" class="font-medium text-blue-600 hover:text-blue-500">Mot de passe oublié ?</a>
                </div>
            </div>

            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    Se connecter
                </button>
            </div>
        </form>
    </div>

    <script src="../assets/js/login.js"></script>
</body>
</html>
