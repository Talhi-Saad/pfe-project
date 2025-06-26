<?php
// Session is already started by index.php
// Database connection is already included by index.php
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'livreur') {
    header('Location: ../auth/login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch livreur (transporter) profile information
$stmt = $conn->prepare("
    SELECT nom, prenom, email, telephone, ville, type_vehicule, statut, created_at
    FROM livreur
    WHERE id_Livreur = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$transporterData = $result->fetch_assoc();
$stmt->close();

$firstName = $transporterData['prenom'] ?? '';
$lastName = $transporterData['nom'] ?? '';
$email = $transporterData['email'] ?? '';
$phone = $transporterData['telephone'] ?? '';
$userStatus = $transporterData['statut'] ?? 'pending';
$vehicleType = $transporterData['type_vehicule'] ?? '';
$vehicleCapacity = ''; // Not in DB, unless you add it
$licenseNumber = '';   // Not in DB, unless you add it

// Additional profile variables
$verificationStatus = $transporterData['statut'] ?? 'pending'; // Use statut as verification status
$totalRatings = 0; // Default rating count (no rating system in DB)
$ratingAverage = 0; // Default rating average (no rating system in DB)

// Stats
$totalDeliveries = 0;
$completedDeliveries = 0;
$successRate = 0;
$currentMonthEarnings = 0;
$totalEarnings = 0;
// Total deliveries assigned to this livreur
$stmt = $conn->prepare("SELECT COUNT(*) FROM commande WHERE id_Livreur = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($totalDeliveries);
$stmt->fetch();
$stmt->close();

// Completed deliveries
$stmt = $conn->prepare("SELECT COUNT(*) FROM commande WHERE id_Livreur = ? AND statu = 'delivered'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($completedDeliveries);
$stmt->fetch();
$stmt->close();

$successRate = ($totalDeliveries > 0) ? round(($completedDeliveries / $totalDeliveries) * 100, 1) : 0;

// Current month earnings (sum of montant from proposition accepted for this livreur and delivered this month)
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(p.montant), 0)
    FROM commande c
    JOIN proposition p ON c.id_Commande = p.id_Commande AND p.id_Livreur = c.id_Livreur AND p.statut = 'accepted'
    WHERE c.id_Livreur = ? AND c.statu = 'delivered'
    AND MONTH(c.date_commande) = MONTH(CURRENT_DATE())
    AND YEAR(c.date_commande) = YEAR(CURRENT_DATE())
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($currentMonthEarnings);
$stmt->fetch();
$stmt->close();

// Total earnings
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(p.montant), 0)
    FROM commande c
    JOIN proposition p ON c.id_Commande = p.id_Commande AND p.id_Livreur = c.id_Livreur AND p.statut = 'accepted'
    WHERE c.id_Livreur = ? AND c.statu = 'delivered'
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($totalEarnings);
$stmt->fetch();
$stmt->close();

// Pending deliveries
$stmt = $conn->prepare("SELECT COUNT(*) FROM commande WHERE id_Livreur = ? AND statu IN ('accepted', 'picked_up', 'in_transit')");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($pendingDeliveries);
$stmt->fetch();
$stmt->close();

// Set availability status based on pending deliveries and verification status
$availabilityStatus = ($verificationStatus === 'active') && $pendingDeliveries < 5 ? 'available' : 'unavailable';

// Fetch available deliveries (commande not yet assigned to any livreur)
$availableDeliveries = [];
$stmt = $conn->prepare("
    SELECT c.id_Commande, c.adresse_livraison, c.adresse_depart, c.nom_destinataire,
           c.description_colis, c.poids, c.dimensions, c.fragile,
           c.date_commande, c.statu, c.id_Client, c.prix_suggere,
           c.distance_estimee, c.duree_estimee,
           cl.nom as client_nom, cl.prenom as client_prenom
    FROM commande c
    JOIN client cl ON c.id_Client = cl.id_Client
    WHERE c.id_Livreur IS NULL AND c.statu = 'pending'
    ORDER BY c.date_commande DESC
    LIMIT 50
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $availableDeliveries[] = $row;
}
$stmt->close();

// Fetch my deliveries
$myDeliveries = [];
$stmt = $conn->prepare("
    SELECT c.id_Commande, c.adresse_livraison, c.adresse_depart, c.nom_destinataire,
           c.telephone_destinataire, c.description_colis, c.poids, c.dimensions, c.fragile,
           c.date_commande, c.statu, c.prix_suggere,
           c.distance_estimee, c.duree_estimee,
           cl.nom as client_nom, cl.prenom as client_prenom
    FROM commande c
    JOIN client cl ON c.id_Client = cl.id_Client
    WHERE c.id_Livreur = ?
    ORDER BY c.date_commande DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $myDeliveries[] = $row;
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Transporteur - EzyLivraison</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">

    <!-- Emergency function definitions -->
    <script>
        console.log("🔧 Loading emergency function definitions...");

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

        window.showBidModal = window.showBidModal || function(deliveryId) {
            console.log("Emergency showBidModal called:", deliveryId);
            // Simple modal opening logic
            const modal = document.getElementById("bidModal");
            if (modal) {
                modal.classList.remove("hidden");
                const deliveryIdInput = document.getElementById("bidDeliveryId");
                if (deliveryIdInput) deliveryIdInput.value = deliveryId;
            }
        };

        window.openModal = window.openModal || function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.remove("hidden");
        };

        window.closeModal = window.closeModal || function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.add("hidden");
        };

        window.updateDeliveryStatus = window.updateDeliveryStatus || function(deliveryId, status) {
            console.log("Emergency updateDeliveryStatus called:", deliveryId, status);
            alert("Update delivery status: " + deliveryId + " to " + status);
        };

        window.viewDeliveryDetails = window.viewDeliveryDetails || function(deliveryId) {
            console.log("Emergency viewDeliveryDetails called:", deliveryId);
            alert("View delivery details: " + deliveryId);
        };

        console.log("✅ Emergency functions loaded and available");
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
                <button data-tab="available" class="tab-btn w-full flex items-center px-4 py-3 text-left rounded-lg transition-colors bg-blue-100 text-blue-700" onclick="switchTab('available')">
                    <i class="fas fa-box mr-3"></i>
                    Livraisons Disponibles
                    <span class="ml-auto bg-blue-500 text-white text-xs rounded-full px-2 py-1"><?= count($availableDeliveries) ?></span>
                </button>

                <button data-tab="my-deliveries" class="tab-btn w-full flex items-center px-4 py-3 text-left rounded-lg transition-colors text-gray-700 hover:bg-gray-100" onclick="switchTab('my-deliveries')">
                    <i class="fas fa-truck mr-3"></i>
                    Mes Livraisons
                    <?php if ($pendingDeliveries > 0): ?>
                        <span class="ml-auto bg-orange-500 text-white text-xs rounded-full px-2 py-1"><?= $pendingDeliveries ?></span>
                    <?php endif; ?>
                </button>

                <button data-tab="routes" class="tab-btn w-full flex items-center px-4 py-3 text-left rounded-lg transition-colors text-gray-700 hover:bg-gray-100" onclick="switchTab('routes')">
                    <i class="fas fa-route mr-3"></i>
                    Mes Trajets
                </button>

                <button data-tab="earnings" class="tab-btn w-full flex items-center px-4 py-3 text-left rounded-lg transition-colors text-gray-700 hover:bg-gray-100" onclick="switchTab('earnings')">
                    <i class="fas mr-3">MAD</i>
                    Mes Gains
                </button>

                <button data-tab="profile" class="tab-btn w-full flex items-center px-4 py-3 text-left rounded-lg transition-colors text-gray-700 hover:bg-gray-100" onclick="switchTab('profile')">
                    <i class="fas fa-user mr-3"></i>
                    Mon Profil
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
                <h1 class="text-3xl font-bold text-gray-900">Tableau de bord Transporteur</h1>
                <p class="text-gray-600 mt-1">Gérez vos livraisons et optimisez vos trajets</p>
            </div>
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                        <span class="text-white text-sm font-medium"><?= strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?></span>
                    </div>
                    <span class="text-gray-700 font-medium"><?= htmlspecialchars($firstName . ' ' . $lastName) ?></span>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Livraisons terminées</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $completedDeliveries ?></p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <i class="fas fa-box text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Gains ce mois</p>
                        <p class="text-3xl font-bold text-gray-900"><?= number_format($currentMonthEarnings, 2) ?>MAD</p>
                        <p class="text-xs text-gray-500">Total: <?= number_format($totalEarnings, 2) ?>MAD</p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <i class="fas text-green-600 text-xl">MAD</i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Taux de réussite</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $successRate ?>%</p>
                    </div>
                    <div class="bg-yellow-100 rounded-full p-3">
                        <i class="fas fa-star text-yellow-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">En cours</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $pendingDeliveries ?></p>
                        <p class="text-xs text-gray-500">Livraisons actives</p>
                    </div>
                    <div class="bg-orange-100 rounded-full p-3">
                        <i class="fas fa-clock text-orange-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content -->
        <div id="available-tab" class="tab-content">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-gray-900">Livraisons Disponibles</h2>
            </div>

            <div id="available-deliveries" class="grid gap-6">
                <?php foreach ($availableDeliveries as $delivery): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                    <i class="fas fa-route text-blue-600 mr-2"></i>
                                    <?php if (!empty($delivery['adresse_depart'])): ?>
                                        <?= htmlspecialchars($delivery['adresse_depart']) ?> →
                                    <?php endif; ?>
                                    <?= htmlspecialchars($delivery['adresse_livraison']) ?>
                                </h3>

                                <div class="grid md:grid-cols-2 gap-4 text-sm text-gray-600 mb-3">
                                    <div>
                                        <p><i class="fas fa-user mr-2"></i>Client: <?= htmlspecialchars($delivery['client_prenom'] . ' ' . $delivery['client_nom']) ?></p>
                                        <?php if (!empty($delivery['nom_destinataire'])): ?>
                                            <p><i class="fas fa-user-tag mr-2"></i>Destinataire: <?= htmlspecialchars($delivery['nom_destinataire']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if (!empty($delivery['description_colis'])): ?>
                                            <p><i class="fas fa-box mr-2"></i><?= htmlspecialchars(substr($delivery['description_colis'], 0, 50)) ?><?= strlen($delivery['description_colis']) > 50 ? '...' : '' ?></p>
                                        <?php endif; ?>

                                        <?php
                                        $details = [];
                                        if (!empty($delivery['poids'])) $details[] = number_format($delivery['poids'], 1) . ' kg';
                                        if (!empty($delivery['dimensions'])) $details[] = $delivery['dimensions'];
                                        if ($delivery['fragile']) $details[] = '⚠️ FRAGILE';
                                        if (!empty($details)):
                                        ?>
                                            <p><i class="fas fa-info-circle mr-2"></i><?= implode(', ', $details) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="flex items-center text-sm text-gray-500 space-x-4">
                                    <span><i class="fas fa-calendar mr-1"></i>Créée le <?= date('d/m/Y', strtotime($delivery['date_commande'])) ?></span>
                                    <?php if (!empty($delivery['distance_estimee'])): ?>
                                        <span><i class="fas fa-road mr-1"></i><?= number_format($delivery['distance_estimee'], 1) ?> km</span>
                                    <?php endif; ?>
                                    <?php if (!empty($delivery['duree_estimee'])): ?>
                                        <span><i class="fas fa-clock mr-1"></i><?= $delivery['duree_estimee'] ?> min</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right ml-4">
                                <div class="text-2xl font-bold text-green-600"><?= number_format($delivery['prix_suggere'], 2) ?>MAD</div>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm"
                                    onclick="showBidModal(<?= $delivery['id_Commande'] ?>)">
                                Faire une offre
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($availableDeliveries)): ?>
                    <div class="bg-white rounded-lg shadow-md p-8 text-center">
                        <i class="fas fa-box text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune livraison disponible</h3>
                        <p class="text-gray-600">Revenez plus tard pour voir les nouvelles opportunités de livraison.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="my-deliveries-tab" class="tab-content hidden">
            <h2 class="text-2xl font-semibold text-gray-900 mb-6">Mes Livraisons</h2>
            <div id="my-deliveries" class="grid gap-6">
                <?php foreach ($myDeliveries as $delivery): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">
                                    <?= htmlspecialchars($delivery['adresse_livraison']) ?>
                                </h3>
                                <p class="text-sm text-gray-600">
                                    Client: <?= htmlspecialchars($delivery['client_prenom'] . ' ' . $delivery['client_nom']) ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="text-xl font-bold text-green-600"><?= number_format($delivery['prix_suggere'], 2) ?>MAD</div>
                                <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full capitalize">
                                    <?= htmlspecialchars($delivery['statu']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="text-sm text-gray-500 mb-2">
                            Créée le <?= date('d/m/Y', strtotime($delivery['date_commande'])) ?>
                        </div>
                        <div class="flex justify-end space-x-2">
                            <?php if ($delivery['statu'] === 'accepted'): ?>
                                <button class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm"
                                        onclick="updateDeliveryStatus(<?= $delivery['id_Commande'] ?>, 'picked_up')">
                                    Marquer récupéré
                                </button>
                            <?php elseif ($delivery['statu'] === 'picked_up'): ?>
                                <button class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm"
                                        onclick="updateDeliveryStatus(<?= $delivery['id_Commande'] ?>, 'delivered')">
                                    Marquer livré
                                </button>
                            <?php endif; ?>
                            <button class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm"
                                    onclick="viewDeliveryDetails(<?= $delivery['id_Commande'] ?>)">
                                Détails
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($myDeliveries)): ?>
                    <div class="bg-white rounded-lg shadow-md p-8 text-center">
                        <i class="fas fa-truck text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune livraison en cours</h3>
                        <p class="text-gray-600">Consultez les livraisons disponibles pour commencer à gagner de l'argent.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="routes-tab" class="tab-content hidden">
            <h2 class="text-2xl font-semibold text-gray-900 mb-6">Mes Trajets</h2>
            <div class="bg-white rounded-lg shadow-md p-6">
                <p class="text-gray-600">Planifiez et optimisez vos trajets pour maximiser vos gains.</p>
            </div>
        </div>

        

        <div id="earnings-tab" class="tab-content hidden">
            <h2 class="text-2xl font-semibold text-gray-900 mb-6">Mes Gains</h2>

            <!-- Earnings Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Gains ce mois</p>
                            <p class="text-2xl font-bold text-green-600"><?= number_format($currentMonthEarnings, 2) ?>MAD</p>
                        </div>
                        <div class="bg-green-100 rounded-full p-3">
                            <i class="fas fa-calendar-alt text-green-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Gains totaux</p>
                            <p class="text-2xl font-bold text-blue-600"><?= number_format($totalEarnings, 2) ?>MAD</p>
                        </div>
                        <div class="bg-blue-100 rounded-full p-3">
                            <i class="fas text-blue-600">MAD</i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Gain moyen/livraison</p>
                            <p class="text-2xl font-bold text-purple-600">
                                <?= $completedDeliveries > 0 ? number_format($totalEarnings / $completedDeliveries, 2) : '0.00' ?>MAD
                            </p>
                        </div>
                        <div class="bg-purple-100 rounded-full p-3">
                            <i class="fas fa-chart-bar text-purple-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Earnings -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Livraisons récentes payées</h3>
                <?php
                // Fetch recent paid deliveries
                $recentEarnings = [];
                $stmt = $conn->prepare("
                    SELECT c.id_Commande, c.adresse_livraison, c.prix_suggere, c.date_commande,
                           cl.prenom as client_prenom, cl.nom as client_nom
                    FROM commande c
                    JOIN client cl ON c.id_Client = cl.id_Client
                    WHERE c.id_Livreur = ? AND c.statu = 'delivered' AND c.prix_suggere > 0
                    ORDER BY c.date_commande DESC
                    LIMIT 10
                ");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $recentEarnings[] = $row;
                }
                $stmt->close();
                ?>

                <?php if (!empty($recentEarnings)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Livraison</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recentEarnings as $earning): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($earning['adresse_livraison']) ?>
                                            </div>
                                            <div class="text-sm text-gray-500">#<?= $earning['id_Commande'] ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($earning['client_prenom'] . ' ' . $earning['client_nom']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('d/m/Y', strtotime($earning['date_commande'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-medium text-green-600">
                                                <?= number_format($earning['prix_suggere'], 2) ?>MAD
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas  text-gray-400 text-4xl mb-4">MAD</i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun gain enregistré</h3>
                        <p class="text-gray-600">Vos gains apparaîtront ici une fois que vous aurez livré des colis.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="profile-tab" class="tab-content hidden">
            <h2 class="text-2xl font-semibold text-gray-900 mb-6">Mon Profil</h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Personal Information -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informations personnelles</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Prénom</label>
                            <div class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($firstName) ?></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nom</label>
                            <div class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($lastName) ?></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <div class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($email) ?></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Téléphone</label>
                            <div class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($phone) ?></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Statut du compte</label>
                            <div class="mt-1">
                                <span class="inline-block px-2 py-1 text-xs font-medium rounded-full
                                    <?= $userStatus === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                    <?= $userStatus === 'active' ? 'Actif' : 'En attente' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Professional Information -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informations professionnelles</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Type de véhicule</label>
                            <div class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($vehicleType ?: 'Non renseigné') ?></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Capacité de charge</label>
                            <div class="mt-1 text-sm text-gray-900"><?= $vehicleCapacity ? $vehicleCapacity . ' kg' : 'Non renseigné' ?></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Numéro de permis</label>
                            <div class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($licenseNumber ?: 'Non renseigné') ?></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Statut de vérification</label>
                            <div class="mt-1">
                                <span class="inline-block px-2 py-1 text-xs font-medium rounded-full
                                    <?php
                                    switch($verificationStatus) {
                                        case 'active': echo 'bg-green-100 text-green-800'; break;
                                        case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'suspended': echo 'bg-orange-100 text-orange-800'; break;
                                        case 'banned': echo 'bg-red-100 text-red-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php
                                    $verificationLabels = [
                                        'active' => 'Actif',
                                        'pending' => 'En attente',
                                        'suspended' => 'Suspendu',
                                        'banned' => 'Banni'
                                    ];
                                    echo $verificationLabels[$verificationStatus] ?? 'Inconnu';
                                    ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Disponibilité</label>
                            <div class="mt-1">
                                <span class="inline-block px-2 py-1 text-xs font-medium rounded-full
                                    <?= $availabilityStatus === 'available' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= $availabilityStatus === 'available' ? 'Disponible' : 'Indisponible' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="mt-8 bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Statistiques</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600"><?= $completedDeliveries ?></div>
                        <div class="text-sm text-gray-600">Livraisons terminées</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600"><?= number_format($totalEarnings, 0) ?>MAD</div>
                        <div class="text-sm text-gray-600">Gains totaux</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-yellow-600">
                            <?= $totalRatings > 0 ? number_format($ratingAverage, 1) : 'N/A' ?>
                        </div>
                        <div class="text-sm text-gray-600">Note moyenne</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-600">
                            <?= $totalDeliveries > 0 ? number_format($successRate, 1) . '%' : 'N/A' ?>
                        </div>
                        <div class="text-sm text-gray-600">Taux de réussite</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bid Modal -->
    <div id="bidModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="flex justify-between items-center p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Faire une offre</h3>
                    <button onclick="closeModal('bidModal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6">
                    <form id="bidForm">
                        <input type="hidden" id="bidDeliveryId" name="delivery_id">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Montant de votre offre (MAD)</label>
                            <input type="number" id="bidAmount" name="bid_amount" step="0.01" min="1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   required>
                            <p class="text-xs text-gray-500 mt-1">Prix suggéré: <span id="suggestedPrice"></span>MAD</p>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Message (optionnel)</label>
                            <textarea id="bidMessage" name="message" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="Présentez-vous au client..."></textarea>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal('bidModal')"
                                    class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                                Annuler
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                                Envoyer l'offre
                            </button>
                        </div>
                    </form>
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

    <script src="../assets/js/transporter.js"></script>
</body>
</html>
