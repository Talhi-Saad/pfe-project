<?php
// Session is already started by index.php
// require_once '../config/database.php'; // Already included by index.php
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    header('Location: ../auth/login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Handle new delivery creation
$createSuccess = '';
$createError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_delivery'])) {
    // Get all form data
    $pickupAddress = trim($_POST['pickup_address'] ?? '');
    $pickupCity = trim($_POST['pickup_city'] ?? '');
    $pickupPostalCode = trim($_POST['pickup_postal_code'] ?? '');
    $pickupDate = $_POST['pickup_date'] ?? null;

    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $deliveryCity = trim($_POST['delivery_city'] ?? '');
    $deliveryPostalCode = trim($_POST['delivery_postal_code'] ?? '');
    $deliveryContactName = trim($_POST['delivery_contact_name'] ?? '');
    $deliveryContactPhone = trim($_POST['delivery_contact_phone'] ?? '');

    $price = $_POST['price'] ?? 0;
    $packageWeight = $_POST['package_weight'] ?? null;
    $description = trim($_POST['description'] ?? '');
    $size = $_POST['size'] ?? '';
    $packageValue = $_POST['package_value'] ?? null;
    $isFragile = isset($_POST['is_fragile']) ? 1 : 0;

    $dateCommande = date('Y-m-d');

    // Basic validation
    if (empty($pickupAddress) || empty($pickupCity) || empty($deliveryAddress) ||
        empty($deliveryCity) || empty($deliveryContactName) || empty($deliveryContactPhone) ||
        empty($description)) {
        $createError = "Veuillez remplir tous les champs obligatoires marqués d'un astérisque (*).";
    } else {
        try {
            // Construct full addresses
            $fullPickupAddress = $pickupAddress . ', ' . $pickupCity;
            if (!empty($pickupPostalCode)) {
                $fullPickupAddress .= ' ' . $pickupPostalCode;
            }

            $fullDeliveryAddress = $deliveryAddress . ', ' . $deliveryCity;
            if (!empty($deliveryPostalCode)) {
                $fullDeliveryAddress .= ' ' . $deliveryPostalCode;
            }

            // Convert size to dimensions
            $dimensions = '';
            switch($size) {
                case 'small': $dimensions = '< 30cm'; break;
                case 'medium': $dimensions = '30-60cm'; break;
                case 'large': $dimensions = '> 60cm'; break;
            }

            // Format pickup date
            $pickupDateFormatted = !empty($pickupDate) ? date('Y-m-d', strtotime($pickupDate)) : null;

            // Insert into database with all fields
            $stmt = $conn->prepare("
                INSERT INTO commande (
                    date_commande,
                    adresse_livraison,
                    adresse_depart,
                    nom_destinataire,
                    telephone_destinataire,
                    description_colis,
                    poids,
                    dimensions,
                    valeur_declaree,
                    fragile,
                    date_ramassage_souhaitee,
                    statu,
                    id_Client,
                    prix_suggere
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
            ");

            $stmt->bind_param(
                "ssssssdsdssid",
                $dateCommande,              // date_commande
                $fullDeliveryAddress,       // adresse_livraison
                $fullPickupAddress,         // adresse_depart
                $deliveryContactName,       // nom_destinataire
                $deliveryContactPhone,      // telephone_destinataire
                $description,               // description_colis
                $packageWeight,             // poids
                $dimensions,                // dimensions
                $packageValue,              // valeur_declaree
                $isFragile,                 // fragile
                $pickupDateFormatted,       // date_ramassage_souhaitee
                $userId,                    // id_Client
                $price                      // prix_suggere
            );

            if ($stmt->execute()) {
                $createSuccess = "Livraison créée avec succès ! Votre demande est maintenant visible par les transporteurs.";
                // Clear form data by redirecting to avoid resubmission
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit;
            } else {
                $createError = "Erreur lors de la création de la livraison: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $createError = "Erreur lors de la création de la livraison: " . $e->getMessage();
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $createSuccess = "Livraison créée avec succès ! Votre demande est maintenant visible par les transporteurs.";
}

// Fetch deliveries for this client
$deliveries = [];
$stmt = $conn->prepare("
    SELECT c.id_Commande, c.adresse_livraison, c.adresse_depart, c.nom_destinataire,
           c.telephone_destinataire, c.email_destinataire, c.description_colis,
           c.poids, c.dimensions, c.valeur_declaree, c.fragile,
           c.date_ramassage_souhaitee, c.date_livraison_souhaitee,
           c.date_commande, c.statu, c.id_Livreur,
           l.nom as livreur_nom, l.prenom as livreur_prenom,
           c.prix_suggere AS suggested_price,
           c.date_acceptation AS accepted_at,
           c.date_ramassage AS picked_up_at,
           c.date_livraison AS delivered_at,
           c.distance_estimee AS estimated_distance,
           c.duree_estimee AS estimated_duration,
           (SELECT COUNT(*) FROM proposition b WHERE b.id_Commande = c.id_Commande) AS bid_count
    FROM commande c
    LEFT JOIN livreur l ON c.id_Livreur = l.id_Livreur
    WHERE c.id_Client = ?
    ORDER BY c.date_commande DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Ensure all expected keys exist and set default values if null
    $row['suggested_price'] = $row['suggested_price'] ?? 0;
    $row['bid_count'] = $row['bid_count'] ?? 0;
    $row['accepted_at'] = $row['accepted_at'] ?? null;
    $row['picked_up_at'] = $row['picked_up_at'] ?? null;
    $row['delivered_at'] = $row['delivered_at'] ?? null;
    $row['estimated_distance'] = $row['estimated_distance'] ?? null;
    $row['estimated_duration'] = $row['estimated_duration'] ?? null;
    $deliveries[] = $row;
}
$stmt->close();

// Fetch account info
$stmt = $conn->prepare("SELECT prenom, nom, email FROM client WHERE id_Client = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($firstName, $lastName, $email);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Client - EzyLivraison</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">

    <!-- Emergency function definitions for client dashboard -->
    <script>
        console.log("🔧 Loading emergency client function definitions...");

        // Emergency function definitions to ensure they're available immediately
        window.switchTab = window.switchTab || function(tabName) {
            console.log("Emergency switchTab called:", tabName);
            document.querySelectorAll(".tab-content").forEach(content => content.classList.add("hidden"));
            const selectedTab = document.getElementById(tabName + "-tab");
            if (selectedTab) selectedTab.classList.remove("hidden");

            document.querySelectorAll(".tab-btn").forEach(btn => {
                if (btn.dataset.tab === tabName) {
                    btn.classList.remove("text-gray-700", "hover:bg-gray-100");
                    btn.classList.add("bg-blue-100", "text-blue-700");
                } else {
                    btn.classList.remove("bg-blue-100", "text-blue-700");
                    btn.classList.add("text-gray-700", "hover:bg-gray-100");
                }
            });
        };

        window.viewBids = window.viewBids || function(deliveryId) {
            console.log("Emergency viewBids called:", deliveryId);
            alert("View bids function called for delivery: " + deliveryId);
        };

        window.openModal = window.openModal || function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.remove("hidden");
        };

        window.closeModal = window.closeModal || function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.add("hidden");
        };

        console.log("✅ Emergency client functions loaded and available");
    </script>
</head>
<body class="min-h-screen bg-gray-50 flex">
    <!-- Sidebar -->
    <div class="w-64 bg-white shadow-lg">
        <div class="p-6">
            <div class="flex items-center">
                <img src="../assets/logo/logo.png" alt="Logo EzyLivraison" class="w-100 h-10">
            </div>
        </div>

        <nav class="mt-6">
            <div class="px-6 space-y-2">
                <button data-tab="deliveries" class="tab-btn w-full flex items-center px-4 py-3 text-left rounded-lg transition-colors bg-blue-100 text-blue-700">
                    <i class="fas fa-box mr-3"></i>
                    Mes Livraisons
                    <?php if (count($deliveries) > 0): ?>
                        <span class="ml-auto bg-blue-500 text-white text-xs rounded-full px-2 py-1"><?= count($deliveries) ?></span>
                    <?php endif; ?>
                </button>

                <button data-tab="create" class="tab-btn w-full flex items-center px-4 py-3 text-left rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-plus mr-3"></i>
                    Créer une Livraison
                </button>
                <button data-tab="account" class="tab-btn w-full flex items-center px-4 py-3 text-left rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-user mr-3"></i>
                    Mon Compte
                </button>
            </div>

            <div class="px-6 mt-8 pt-8 border-t border-gray-200">
                <a href="../auth/logout.php" class="w-full flex items-center px-4 py-3 text-left rounded-lg transition-colors text-red-600 hover:bg-red-50">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Déconnexion
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-1 p-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Tableau de bord</h1>
                <p class="text-gray-600 mt-1">Gérez vos livraisons en toute simplicité</p>
            </div>
            <div class="flex items-center space-x-4">
                <!-- <button class="relative p-2 text-gray-600 hover:text-gray-900">
                    <i class="fas fa-bell text-xl"></i>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full px-1">2</span>
                </button> -->
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                        <span class="text-white text-sm font-medium"><?= strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?></span>
                    </div>
                    <span class="text-gray-700 font-medium"><?= htmlspecialchars($firstName . ' ' . $lastName) ?></span>
                </div>
            </div>
        </div>

        <!-- Tab Content -->
        <div id="deliveries-tab" class="tab-content">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-gray-900">Mes Livraisons</h2>
                <button data-tab="create" class="tab-btn bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-plus mr-2"></i>
                    Nouvelle Livraison
                </button>
            </div>

            <div id="deliveries-list" class="grid gap-6">
                <?php foreach ($deliveries as $delivery): ?>
                    <div class="bg-white rounded-lg shadow-md p-6" data-delivery-id="<?= $delivery['id_Commande'] ?>">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900 mr-3">
                                        <i class="fas fa-map-marker-alt text-green-600 mr-2"></i>
                                        <?= htmlspecialchars($delivery['adresse_depart'] ?? 'Adresse de départ non spécifiée') ?>
                                        <i class="fas fa-arrow-right text-gray-400 mx-2"></i>
                                        <i class="fas fa-map-marker-alt text-red-600 mr-2"></i>
                                        <?= htmlspecialchars($delivery['adresse_livraison']) ?>
                                    </h3>
                                    <span class="inline-block px-3 py-1 text-xs font-medium rounded-full
                                        <?php
                                        switch($delivery['statu']) {
                                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'accepted': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'picked_up': echo 'bg-purple-100 text-purple-800'; break;
                                            case 'in_transit': echo 'bg-indigo-100 text-indigo-800'; break;
                                            case 'delivered': echo 'bg-green-100 text-green-800'; break;
                                            case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                            case 'disputed': echo 'bg-orange-100 text-orange-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php
                                        $statusLabels = [
                                            'pending' => 'En attente',
                                            'accepted' => 'Acceptée',
                                            'picked_up' => 'Récupérée',
                                            'in_transit' => 'En transit',
                                            'delivered' => 'Livrée',
                                            'cancelled' => 'Annulée',
                                            'disputed' => 'Litige'
                                        ];
                                        echo $statusLabels[$delivery['statu']] ?? ucfirst($delivery['statu']);
                                        ?>
                                    </span>
                                    <?php if ($delivery['id_Livreur']): ?>
                                        <span class="ml-2 inline-block px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                            Assignée
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="text-sm text-gray-600 mb-3">
                                    Livraison #<?= $delivery['id_Commande'] ?> • Créée le <?= date('d/m/Y', strtotime($delivery['date_commande'])) ?>
                                </div>
                            </div>

                            <div class="text-right">
                                <div class="text-2xl font-bold text-green-600">
                                    <?= number_format($delivery['suggested_price'], 0) ?>MAD
                                </div>
                                <?php if ($delivery['bid_count'] > 0): ?>
                                    <div class="text-sm text-blue-600 font-medium">
                                        <?= $delivery['bid_count'] ?> offre<?= $delivery['bid_count'] > 1 ? 's' : '' ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Delivery Details -->
                        <div class="grid md:grid-cols-2 gap-4 mb-4">
                            <!-- Addresses -->
                            <div class="space-y-3">
                                <?php if (!empty($delivery['adresse_depart'])): ?>
                                <div class="flex items-start text-sm">
                                    <i class="fas fa-map-marker-alt text-green-500 mr-3 mt-1"></i>
                                    <div>
                                        <div class="font-medium text-gray-900">Départ</div>
                                        <div class="text-gray-600"><?= htmlspecialchars($delivery['adresse_depart']) ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="flex items-start text-sm">
                                    <i class="fas fa-map-marker-alt text-red-500 mr-3 mt-1"></i>
                                    <div>
                                        <div class="font-medium text-gray-900">Livraison</div>
                                        <div class="text-gray-600"><?= htmlspecialchars($delivery['adresse_livraison']) ?></div>
                                    </div>
                                </div>

                                <?php if (!empty($delivery['nom_destinataire'])): ?>
                                <div class="flex items-start text-sm">
                                    <i class="fas fa-user mr-3 mt-1 text-blue-500"></i>
                                    <div>
                                        <div class="font-medium text-gray-900">Destinataire</div>
                                        <div class="text-gray-600">
                                            <?= htmlspecialchars($delivery['nom_destinataire']) ?>
                                            <?php if (!empty($delivery['telephone_destinataire'])): ?>
                                                <br><i class="fas fa-phone text-xs mr-1"></i><?= htmlspecialchars($delivery['telephone_destinataire']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Package Details -->
                            <div class="space-y-3">
                                <?php if (!empty($delivery['description_colis'])): ?>
                                <div class="flex items-start text-sm">
                                    <i class="fas fa-box mr-3 mt-1 text-purple-500"></i>
                                    <div>
                                        <div class="font-medium text-gray-900">Description</div>
                                        <div class="text-gray-600"><?= htmlspecialchars($delivery['description_colis']) ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($delivery['poids']) || !empty($delivery['dimensions']) || !empty($delivery['valeur_declaree'])): ?>
                                <div class="flex items-start text-sm">
                                    <i class="fas fa-info-circle mr-3 mt-1 text-gray-500"></i>
                                    <div>
                                        <div class="font-medium text-gray-900">Détails du colis</div>
                                        <div class="text-gray-600 space-y-1">
                                            <?php if (!empty($delivery['poids'])): ?>
                                                <div>Poids: <?= number_format($delivery['poids'], 1) ?> kg</div>
                                            <?php endif; ?>
                                            <?php if (!empty($delivery['dimensions'])): ?>
                                                <div>Dimensions: <?= htmlspecialchars($delivery['dimensions']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($delivery['valeur_declaree'])): ?>
                                                <div>Valeur: <?= number_format($delivery['valeur_declaree'], 2) ?>MAD</div>
                                            <?php endif; ?>
                                            <?php if ($delivery['fragile']): ?>
                                                <div class="text-orange-600 font-medium">⚠️ FRAGILE</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($delivery['date_ramassage_souhaitee']) || !empty($delivery['date_livraison_souhaitee'])): ?>
                                <div class="flex items-start text-sm">
                                    <i class="fas fa-calendar mr-3 mt-1 text-indigo-500"></i>
                                    <div>
                                        <div class="font-medium text-gray-900">Dates souhaitées</div>
                                        <div class="text-gray-600 space-y-1">
                                            <?php if (!empty($delivery['date_ramassage_souhaitee'])): ?>
                                                <div>Récupération: <?= date('d/m/Y', strtotime($delivery['date_ramassage_souhaitee'])) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($delivery['date_livraison_souhaitee'])): ?>
                                                <div>Livraison: <?= date('d/m/Y', strtotime($delivery['date_livraison_souhaitee'])) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Transporter Information -->
                        <?php if ($delivery['id_Livreur']): ?>
                            <div class="bg-blue-50 rounded-lg p-4 mb-4">
                                <h4 class="font-medium text-gray-900 mb-2 flex items-center">
                                    <i class="fas fa-truck mr-2"></i>
                                    Transporteur assigné
                                </h4>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900">
                                            <?= htmlspecialchars($delivery['livreur_prenom'] . ' ' . $delivery['livreur_nom']) ?>
                                        </div>
                                    </div>
                                    <button class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                                        <i class="fas fa-comment mr-1"></i>
                                        Contacter
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Timeline -->
                        <?php if ($delivery['accepted_at'] || $delivery['picked_up_at'] || $delivery['delivered_at']): ?>
                            <div class="border-t border-gray-200 pt-4 mb-4">
                                <h4 class="font-medium text-gray-900 mb-3">Suivi de la livraison</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex items-center text-gray-600">
                                        <i class="fas fa-clock mr-2"></i>
                                        Créée le <?= date('d/m/Y à H:i', strtotime($delivery['date_commande'])) ?>
                                    </div>
                                    <?php if ($delivery['accepted_at']): ?>
                                        <div class="flex items-center text-blue-600">
                                            <i class="fas fa-check mr-2"></i>
                                            Acceptée le <?= date('d/m/Y à H:i', strtotime($delivery['accepted_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($delivery['picked_up_at']): ?>
                                        <div class="flex items-center text-purple-600">
                                            <i class="fas fa-hand-paper mr-2"></i>
                                            Récupérée le <?= date('d/m/Y à H:i', strtotime($delivery['picked_up_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($delivery['delivered_at']): ?>
                                        <div class="flex items-center text-green-600">
                                            <i class="fas fa-check-circle mr-2"></i>
                                            Livrée le <?= date('d/m/Y à H:i', strtotime($delivery['delivered_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                            <div class="text-sm text-gray-500">
                                <?php if ($delivery['estimated_distance']): ?>
                                    Distance estimée: <?= $delivery['estimated_distance'] ?> km
                                <?php endif; ?>
                                <?php if ($delivery['estimated_duration']): ?>
                                    • Durée estimée: <?= $delivery['estimated_duration'] ?> min
                                <?php endif; ?>
                            </div>
                            <div class="space-x-2">
                                <?php if ($delivery['statu'] === 'pending' && $delivery['bid_count'] > 0): ?>
                                    <button onclick="viewBids(<?= $delivery['id_Commande'] ?>)" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                                        Voir les offres (<?= $delivery['bid_count'] ?>)
                                    </button>
                                <?php endif; ?>
                                <?php if (in_array($delivery['statu'], ['pending', 'accepted'])): ?>
                                    <button class="cancel-delivery bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                                        Annuler
                                    </button>
                                <?php endif; ?>
                                <button class="details-delivery bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm">
                                    Détails complets
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($deliveries)): ?>
                    <div class="bg-white rounded-lg shadow-md p-8 text-center">
                        <i class="fas fa-box text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune livraison trouvée</h3>
                        <p class="text-gray-600 mb-4">Vous n'avez pas encore créé de demande de livraison.</p>
                        <button data-tab="create" class="tab-btn bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg">
                            <i class="fas fa-plus mr-2"></i>
                            Créer ma première livraison
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="create-tab" class="tab-content hidden">
            <h2 class="text-2xl font-semibold text-gray-900 mb-6">Créer une Nouvelle Livraison</h2>

            <div class="bg-white rounded-lg shadow-md p-6">
                <form id="create-delivery-form" class="space-y-6" method="post" autocomplete="off">
                    <input type="hidden" name="create_delivery" value="1">
                    <!-- Pickup Information -->
                    <div class="border border-gray-200 rounded-lg p-4 mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-map-marker-alt text-green-500 mr-2"></i>
                            Point de départ
                        </h3>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Adresse complète *</label>
                                <input type="text" name="pickup_address" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="123 Rue de la Paix">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Ville *</label>
                                <input type="text" name="pickup_city" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="Tetouan">
                            </div>
                        </div>
                        <div class="grid md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Code postal</label>
                                <input type="text" name="pickup_postal_code"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="75001">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date souhaitée</label>
                                <input type="date" name="pickup_date"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Delivery Information -->
                    <div class="border border-gray-200 rounded-lg p-4 mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                            Destination
                        </h3>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Adresse complète *</label>
                                <input type="text" name="delivery_address" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="456 Avenue des Champs">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Ville *</label>
                                <input type="text" name="delivery_city" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="Tanger">
                            </div>
                        </div>
                        <div class="grid md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Code postal</label>
                                <input type="text" name="delivery_postal_code"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="69000">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Contact *</label>
                                <input type="text" name="delivery_contact_name" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="Nom du destinataire">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone *</label>
                                <input type="tel" name="delivery_contact_phone" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="+212 6 12 34 56 78">
                            </div>
                        </div>
                    </div>

                    <!-- Package and Pricing Information -->
                    <div class="border border-gray-200 rounded-lg p-4 mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-box text-blue-500 mr-2"></i>
                            Informations du colis
                        </h3>
                        <div class="grid md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Prix proposé (MAD)</label>
                                <div class="relative"> 
                                    <input type="number" name="price" step="0.01" min="0"
                                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           placeholder="0.00">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Prix suggéré pour attirer les transporteurs</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Poids estimé (kg)</label>
                                <input type="number" name="package_weight" step="0.1" min="0"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="1.5">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description du colis *</label>
                            <textarea name="description" rows="3" required
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="Décrivez votre colis (contenu, fragilité, instructions spéciales...)"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Taille du colis</label>
                            <div class="grid grid-cols-3 gap-4">
                                <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" name="size" value="small" class="mr-3 text-blue-600">
                                    <div>
                                        <div class="font-medium">Petit</div>
                                        <div class="text-sm text-gray-500">&lt; 30cm</div>
                                    </div>
                                </label>
                                <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" name="size" value="medium" class="mr-3 text-blue-600">
                                    <div>
                                        <div class="font-medium">Moyen</div>
                                        <div class="text-sm text-gray-500">30-60cm</div>
                                    </div>
                                </label>
                                <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" name="size" value="large" class="mr-3 text-blue-600">
                                    <div>
                                        <div class="font-medium">Grand</div>
                                        <div class="text-sm text-gray-500">&gt; 60cm</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Valeur déclarée (MAD)</label>
                                <input type="number" name="package_value" step="0.01" min="0"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="100.00">
                                <p class="text-xs text-gray-500 mt-1">Pour l'assurance (optionnel)</p>
                            </div>
                            <div class="flex items-center">
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_fragile" value="1" class="mr-3 text-blue-600">
                                    <div>
                                        <div class="font-medium text-gray-900">Colis fragile</div>
                                        <div class="text-sm text-gray-500">Nécessite une manipulation délicate</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Instructions spéciales</label>
                            <textarea name="special_instructions" rows="2"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="Instructions particulières pour le transporteur (optionnel)"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4">
                        <button type="button" data-tab="deliveries" class="tab-btn px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            Annuler
                        </button>
                        <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                            Publier la livraison
                        </button>
                    </div>

                    <?php if ($createSuccess): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                            <?= htmlspecialchars($createSuccess) ?>
                        </div>
                    <?php elseif ($createError): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                            <?= htmlspecialchars($createError) ?>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div id="messages-tab" class="tab-content hidden">
            <h2 class="text-2xl font-semibold text-gray-900 mb-6">Messages</h2>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-center py-12">
                    <i class="fas fa-comment text-gray-400 text-5xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun message</h3>
                    <p class="text-gray-600">Vos conversations avec les transporteurs apparaîtront ici.</p>
                </div>
            </div>
        </div>

        <div id="account-tab" class="tab-content hidden">
            <h2 class="text-2xl font-semibold text-gray-900 mb-6">Mon Compte</h2>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Informations personnelles</h3>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Prénom</label>
                                <input type="text" value="<?= htmlspecialchars($firstName) ?>" name="first_name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nom</label>
                                <input type="text" value="<?= htmlspecialchars($lastName) ?>" name="last_name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                <input type="email" value="<?= htmlspecialchars($email) ?>" name="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                                <input type="tel" value="<?= htmlspecialchars($phone) ?>" name="phone" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-200">
                        <button class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg">
                            Sauvegarder les modifications
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delivery Details Modal -->
    <div id="deliveryDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Détails de la livraison</h3>
                    <button onclick="closeModal('deliveryDetailsModal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="deliveryDetailsContent" class="p-6">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Bids Modal -->
    <div id="bidsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Offres reçues</h3>
                    <button onclick="closeModal('bidsModal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="bidsContent" class="p-6">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
