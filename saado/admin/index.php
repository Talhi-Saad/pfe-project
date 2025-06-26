<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Statistiques du tableau de bord
$stats = [];

// Nombre total d'utilisateurs (clients + livreurs)
$result = $conn->query("SELECT 
    (SELECT COUNT(*) FROM client) + (SELECT COUNT(*) FROM livreur) AS total");
$stats['total_users'] = $result->fetch_assoc()['total'];

// Nombre de livraisons actives (commande.statu)
$result = $conn->query("SELECT COUNT(*) as total FROM commande WHERE statu IN ('pending', 'accepted', 'picked_up', 'in_transit')");
$stats['active_deliveries'] = $result->fetch_assoc()['total'];

// Revenu mensuel (pas de champ de commission, donc utilisez la somme prix_suggere pour la livraison)
$result = $conn->query("SELECT COALESCE(SUM(prix_suggere), 0) as total FROM commande WHERE statu = 'delivered' AND MONTH(date_livraison) = MONTH(CURRENT_DATE()) AND YEAR(date_livraison) = YEAR(CURRENT_DATE())");
$stats['monthly_revenue'] = $result->fetch_assoc()['total'];

// Note moyenne (pas de tableau de notes, donc définir la valeur par défaut ou ignorer)
$stats['avg_rating'] = 4.8;

// Nombre de clients et de transporteurs
$result = $conn->query("SELECT 'client' as type, COUNT(*) as count FROM client
    UNION ALL
    SELECT 'livreur' as type, COUNT(*) as count FROM livreur");
$userCounts = ['client' => 0, 'transporter' => 0];
while ($row = $result->fetch_assoc()) {
    if ($row['type'] === 'client') $userCounts['client'] = $row['count'];
    if ($row['type'] === 'livreur') $userCounts['transporter'] = $row['count'];
}

// Activité récente (10 dernières du client/livreur/commande)
$recentActivity = [];
$stmt = $conn->prepare("
    SELECT 'user_registered' as type, CONCAT('Nouvel client inscrit: ', prenom, ' ', nom) as message,
           created_at as time, 'fas fa-user-plus' as icon, 'text-green-600' as color
    FROM client
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT 'user_registered' as type, CONCAT('Nouveau livreur inscrit: ', prenom, ' ', nom) as message,
           created_at as time, 'fas fa-truck' as icon, 'text-green-600' as color
    FROM livreur
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT 'delivery_completed' as type, CONCAT('Livraison terminée: ', adresse_livraison) as message,
           date_livraison as time, 'fas fa-check-circle' as icon, 'text-blue-600' as color
    FROM commande
    WHERE statu = 'delivered' AND date_livraison >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY time DESC
    LIMIT 10
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $timeAgo = time_elapsed_string($row['time']);
    $recentActivity[] = [
        'type' => $row['type'],
        'message' => $row['message'],
        'time' => $timeAgo,
        'icon' => $row['icon'],
        'color' => $row['color']
    ];
}

// Fonction d'aide pour calculer le temps écoulé
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

    $string = [];

    if ($diff->y) $string['y'] = $diff->y . ' année' . ($diff->y > 1 ? 's' : '');
    if ($diff->m) $string['m'] = $diff->m . ' mois';
    if ($weeks) $string['w'] = $weeks . ' semaine' . ($weeks > 1 ? 's' : '');
    if ($days) $string['d'] = $days . ' jour' . ($days > 1 ? 's' : '');
    if ($diff->h) $string['h'] = $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
    if ($diff->i) $string['i'] = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
    if ($diff->s) $string['s'] = $diff->s . ' seconde' . ($diff->s > 1 ? 's' : '');

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'Il y a ' . implode(', ', $string) : 'À l\'instant';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - EzyLivraison</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="./styles.css" rel="stylesheet">
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
                <button data-tab="overview" class="tab-btn w-full flex items-center px-4 py-3 text-left rounded-lg transition-colors bg-blue-100 text-blue-700">
                    <i class="fas fa-chart-pie mr-3"></i>
                    Vue d'ensemble
                </button>

                <button data-tab="users" class="tab-btn w-full flex items-center px-4 py-3 text-left rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-users mr-3"></i>
                    Utilisateurs
                    <span class="ml-auto bg-blue-500 text-white text-xs rounded-full px-2 py-1"><?php echo $stats['total_users']; ?></span>
                </button>

                <button data-tab="deliveries" class="tab-btn w-full flex items-center px-4 py-3 text-left rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-box mr-3"></i>
                    Livraisons
                    <span class="ml-auto bg-green-500 text-white text-xs rounded-full px-2 py-1"><?php echo $stats['active_deliveries']; ?></span>
                </button>

                <button data-tab="transporters" class="tab-btn w-full flex items-center px-4 py-3 text-left rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-truck mr-3"></i>
                    Transporteurs
                    <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full px-2 py-1"><?php echo $userCounts['transporter']; ?></span>
                </button>


                <button data-tab="reports" class="tab-btn w-full flex items-center px-4 py-3 text-left rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Rapports
                </button>

                <button data-tab="settings" class="tab-btn w-full flex items-center px-4 py-3 text-left rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-cog mr-3"></i>
                    Paramètres
                </button>
            </div>
        </nav>

        <div class="absolute bottom-0 w-64 p-6 border-t border-gray-200">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-red-600 rounded-full flex items-center justify-center">
                    <span class="text-white text-sm font-medium">AD</span>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-900">Admin</p>
                    <p class="text-xs text-gray-500">Administrateur</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 p-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Tableau de bord Admin</h1>
                <p class="text-gray-600 mt-1">Gérez votre plateforme de livraison</p>
            </div>
            <div class="flex items-center space-x-4">
                <button class="relative p-2 text-gray-600 hover:text-gray-900">
                    <i class="fas fa-bell text-xl"></i>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full px-1">5</span>
                </button>
                <a href="../auth/logout.php" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium">
                    <i class="fas fa-sign-out-alt mr-2"></i>
                    Déconnexion
                </a>
            </div>
        </div>

        <!-- Overview Tab -->
        <div id="overview-tab" class="tab-content">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Utilisateurs Total</p>
                            <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_users']; ?></p>
                            <p class="text-sm text-green-600">Actifs</p>
                        </div>
                        <div class="bg-blue-100 rounded-full p-3">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Livraisons Actives</p>
                            <p class="text-3xl font-bold text-gray-900"><?php echo $stats['active_deliveries']; ?></p>
                            <p class="text-sm text-blue-600">En cours</p>
                        </div>
                        <div class="bg-green-100 rounded-full p-3">
                            <i class="fas fa-box text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Revenus ce mois</p>
                            <p class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['monthly_revenue'], 0, ',', ' '); ?>MAD</p>
                            <p class="text-sm text-green-600">Commission</p>
                        </div>
                        <div class="bg-yellow-100 rounded-full p-3">
                            <i class="fas  text-yellow-600 text-xl">MAD</i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Taux de Satisfaction</p>
                            <p class="text-3xl font-bold text-gray-900"><?php echo $stats['avg_rating']; ?>/5</p>
                            <p class="text-sm text-green-600">Moyenne</p>
                        </div>
                        <div class="bg-purple-100 rounded-full p-3">
                            <i class="fas fa-star text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Évolution des inscriptions</h3>
                    <div class="h-64 flex items-end justify-between space-x-2">
                        <div class="bg-blue-500 rounded-t" style="height: 60%; width: 12%;"></div>
                        <div class="bg-blue-500 rounded-t" style="height: 80%; width: 12%;"></div>
                        <div class="bg-blue-500 rounded-t" style="height: 45%; width: 12%;"></div>
                        <div class="bg-blue-500 rounded-t" style="height: 90%; width: 12%;"></div>
                        <div class="bg-blue-500 rounded-t" style="height: 70%; width: 12%;"></div>
                        <div class="bg-blue-500 rounded-t" style="height: 85%; width: 12%;"></div>
                        <div class="bg-blue-500 rounded-t" style="height: 95%; width: 12%;"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500 mt-2">
                        <span>Jan</span>
                        <span>Fév</span>
                        <span>Mar</span>
                        <span>Avr</span>
                        <span>Mai</span>
                        <span>Jun</span>
                        <span>Jul</span>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Répartition des utilisateurs</h3>
                    <div class="flex items-center justify-center h-64">
                        <div class="relative w-48 h-48">
                            <div class="absolute inset-0 rounded-full border-8 border-blue-500" style="border-right-color: transparent; transform: rotate(45deg);"></div>
                            <div class="absolute inset-4 rounded-full border-8 border-green-500" style="border-left-color: transparent; transform: rotate(-90deg);"></div>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_users']; ?></p>
                                    <p class="text-sm text-gray-600">Total</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-center space-x-6 mt-4">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                            <span class="text-sm text-gray-600">Clients (<?php echo $userCounts['client']; ?>)</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                            <span class="text-sm text-gray-600">Transporteurs (<?php echo $userCounts['transporter']; ?>)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Activité récente</h3>
                <div id="recent-activity" class="space-y-4">
                    <?php if (empty($recentActivity)): ?>
                        <p class="text-gray-500 text-center py-4">Aucune activité récente</p>
                    <?php else: ?>
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="flex items-center space-x-3 p-3 hover:bg-gray-50 rounded-lg">
                                <div class="flex-shrink-0">
                                    <i class="<?php echo $activity['icon']; ?> <?php echo $activity['color']; ?> text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($activity['message']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo $activity['time']; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
      // Récupérer les données des utilisateurs (clients et livreurs)
        $users = [];
        // Clients
        $result = $conn->query("SELECT id_Client as id, prenom, nom, email, 'client' as user_type, statut as status, created_at FROM client");
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        // Livreurs
        $result = $conn->query("SELECT id_Livreur as id, prenom, nom, email, 'transporter' as user_type, statut as status, created_at FROM livreur");
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
// Trier éventuellement $users par created_at décroissant        usort($users, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
        ?>

        <!-- Users Tab -->
        <div id="users-tab" class="tab-content hidden">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-gray-900">Gestion des utilisateurs</h2>
                <div class="flex space-x-4">
                    <button class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        <i class="fas fa-filter mr-2"></i>
                        Filtrer
                    </button>
                    <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                        <i class="fas fa-download mr-2"></i>
                        Exporter
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Utilisateur</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Inscription</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="users-table" class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-blue-600 flex items-center justify-center">
                                                <span class="text-sm font-medium text-white">
                                                    <?php echo strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $user['user_type'] === 'client' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                        <i class="fas <?php echo $user['user_type'] === 'client' ? 'fa-user' : 'fa-truck'; ?> mr-1"></i>
                                        <?php echo $user['user_type'] === 'client' ? 'Client' : 'Transporteur'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusClass = '';
                                    $statusText = '';
                                    switch ($user['status']) {
                                        case 'active':
                                            $statusClass = 'bg-green-100 text-green-800';
                                            $statusText = 'Actif';
                                            break;
                                        case 'pending':
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            $statusText = 'En attente';
                                            break;
                                        case 'suspended':
                                            $statusClass = 'bg-red-100 text-red-800';
                                            $statusText = 'Suspendu';
                                            break;
                                        default:
                                            $statusClass = 'bg-gray-100 text-gray-800';
                                            $statusText = ucfirst($user['status']);
                                    }
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-blue-600 hover:text-blue-900 mr-3" onclick="viewUser(<?php echo $user['id']; ?>)">
                                        Voir
                                    </button>
                                    <button class="text-red-600 hover:text-red-900" onclick="suspendUser(<?php echo $user['id']; ?>)">
                                        <?php echo $user['status'] === 'suspended' ? 'Réactiver' : 'Suspendre'; ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php
        // Fetch deliveries data (commande)
        $deliveries = [];
        $result = $conn->query("
            SELECT c.id_Commande as id, c.adresse_livraison as delivery_address, c.statu as status, c.date_commande as created_at, c.prix_suggere,
                   c.date_livraison, cl.prenom as client_first_name, cl.nom as client_last_name,
                   l.prenom as transporter_first_name, l.nom as transporter_last_name
            FROM commande c
            LEFT JOIN client cl ON c.id_Client = cl.id_Client
            LEFT JOIN livreur l ON c.id_Livreur = l.id_Livreur
            ORDER BY c.date_commande DESC
            LIMIT 50
        ");
        while ($row = $result->fetch_assoc()) {
            $deliveries[] = $row;
        }
        ?>

        <!-- Deliveries Tab -->
        <div id="deliveries-tab" class="tab-content hidden">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-gray-900">Gestion des livraisons</h2>
                <div class="flex space-x-4">
                    <select class="px-4 py-2 border border-gray-300 rounded-lg">
                        <option>Tous les statuts</option>
                        <option>En attente</option>
                        <option>En cours</option>
                        <option>Terminé</option>
                        <option>Annulé</option>
                    </select>
                    <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                        <i class="fas fa-download mr-2"></i>
                        Exporter
                    </button>
                </div>
            </div>

            <div id="deliveries-list" class="grid gap-6">
                <?php if (empty($deliveries)): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 text-center">
                        <p class="text-gray-500">Aucune livraison trouvée</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($deliveries as $delivery): ?>
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-lg">
                                        <?php echo htmlspecialchars($delivery['delivery_address']); ?>
                                    </h3>
                                    <p class="text-gray-600">Client: <?php echo htmlspecialchars($delivery['client_first_name'] . ' ' . $delivery['client_last_name']); ?></p>
                                    <?php if ($delivery['transporter_first_name']): ?>
                                        <p class="text-gray-600">Transporteur: <?php echo htmlspecialchars($delivery['transporter_first_name'] . ' ' . $delivery['transporter_last_name']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php
                                $statusClass = '';
                                $statusText = '';
                                switch ($delivery['status']) {
                                    case 'pending':
                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                        $statusText = 'En attente';
                                        break;
                                    case 'accepted':
                                        $statusClass = 'bg-blue-100 text-blue-800';
                                        $statusText = 'Accepté';
                                        break;
                                    case 'picked_up':
                                        $statusClass = 'bg-purple-100 text-purple-800';
                                        $statusText = 'Récupéré';
                                        break;
                                    case 'in_transit':
                                        $statusClass = 'bg-indigo-100 text-indigo-800';
                                        $statusText = 'En transit';
                                        break;
                                    case 'delivered':
                                        $statusClass = 'bg-green-100 text-green-800';
                                        $statusText = 'Livré';
                                        break;
                                    case 'cancelled':
                                        $statusClass = 'bg-red-100 text-red-800';
                                        $statusText = 'Annulé';
                                        break;
                                    default:
                                        $statusClass = 'bg-gray-100 text-gray-800';
                                        $statusText = ucfirst($delivery['status']);
                                }
                                ?>
                                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600">
                                <div class="flex items-center">
                                    <i class="fas fa-calendar mr-2"></i>
                                    <?php echo date('d/m/Y', strtotime($delivery['created_at'])); ?>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas mr-2">MAD</i>
                                    <?php echo number_format($delivery['prix_suggere'], 2); ?>MAD
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-percentage mr-2"></i>
                                    Commission: 0.00MAD
                                </div>
                                <div class="flex space-x-2">
                                    <button class="text-blue-600 hover:text-blue-800 text-sm font-medium" onclick="viewDelivery(<?php echo $delivery['id']; ?>)">Détails</button>
                                    <?php if ($delivery['status'] !== 'delivered' && $delivery['status'] !== 'cancelled'): ?>
                                        <button class="text-red-600 hover:text-red-800 text-sm font-medium" onclick="cancelDelivery(<?php echo $delivery['id']; ?>)">Annuler</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php
        // Fetch transporters data (livreur)
        $transporters = [];
        $result = $conn->query("
            SELECT id_Livreur as id, prenom, nom, email, telephone, statut as status, created_at, type_vehicule
            FROM livreur
            ORDER BY created_at DESC
        ");
        while ($row = $result->fetch_assoc()) {
            $transporters[] = $row;
        }
        ?>
        <!-- Transporters Tab -->
        <div id="transporters-tab" class="tab-content hidden">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-gray-900">Gestion des transporteurs</h2>
                <div class="flex space-x-4">
                    <button class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        <i class="fas fa-user-check mr-2"></i>
                        Demandes en attente (3)
                    </button>
                    <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                        <i class="fas fa-download mr-2"></i>
                        Exporter
                    </button>
                </div>
            </div>

            <div id="transporters-list" class="grid gap-6">
                <?php if (empty($transporters)): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 text-center">
                        <p class="text-gray-500">Aucun transporteur trouvé</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($transporters as $transporter): ?>
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center space-x-4">
                                    <div class="h-12 w-12 rounded-full bg-green-600 flex items-center justify-center">
                                        <span class="text-white font-medium">
                                            <?php echo strtoupper(substr($transporter['prenom'], 0, 1) . substr($transporter['nom'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($transporter['prenom'] . ' ' . $transporter['nom']); ?></h3>
                                        <p class="text-gray-600"><?php echo htmlspecialchars($transporter['email']); ?></p>
                                        <p class="text-gray-600"><?php echo htmlspecialchars($transporter['telephone']); ?></p>
                                    </div>
                                </div>
                                <?php
                                $statusClass = '';
                                $statusText = '';
                                switch ($transporter['verification_status'] ?? '') {
                                    case 'verified':
                                        $statusClass = 'bg-green-100 text-green-800';
                                        $statusText = 'Vérifié';
                                        break;
                                    case 'pending':
                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                        $statusText = 'En attente';
                                        break;
                                    case 'rejected':
                                        $statusClass = 'bg-red-100 text-red-800';
                                        $statusText = 'Rejeté';
                                        break;
                                    default:
                                        $statusClass = 'bg-gray-100 text-gray-800';
                                        $statusText = ucfirst($transporter['verification_status'] ?? '');
                                }
                                if (($transporter['status'] ?? '') === 'suspended') {
                                    $statusClass = 'bg-red-100 text-red-800';
                                    $statusText = 'Suspendu';
                                }
                                ?>
                                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600 mb-4">
                                <div class="flex items-center">
                                    <i class="fas fa-car mr-2"></i>
                                    <?php echo ucfirst($transporter['vehicle_type'] ?? ''); ?>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-weight mr-2"></i>
                                    <?php echo $transporter['vehicle_capacity'] ?? ''; ?>kg
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-id-card mr-2"></i>
                                    <?php echo htmlspecialchars($transporter['license_number'] ?? ''); ?>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-truck mr-2"></i>
                                    <?php echo $transporter['completed_deliveries'] ?? '0'; ?> livraisons
                                </div>
                            </div>

                            <?php if (($transporter['rating_average'] ?? 0) && $transporter['rating_average'] > 0): ?>
                                <div class="flex items-center mb-4">
                                    <span class="text-sm text-gray-600 mr-2">Note:</span>
                                    <div class="flex items-center">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= floor($transporter['rating_average'] ?? 0) ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                        <?php endfor; ?>
                                        <span class="ml-1 text-sm text-gray-600"><?php echo number_format($transporter['rating_average'] ?? 0, 1); ?>/5 (<?php echo $transporter['total_ratings'] ?? '0'; ?> avis)</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="flex space-x-2">
                                <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm"
                                        onclick="viewTransporter(<?php echo $transporter['id']; ?>)">
                                    Voir détails
                                </button>
                                <?php if (($transporter['verification_status'] ?? '') === 'pending'): ?>
                                    <button class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm"
                                            onclick="verifyTransporter(<?php echo $transporter['id']; ?>)">
                                        Vérifier
                                    </button>
                                <?php endif; ?>
                                <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50"
                                        onclick="suspendTransporter(<?php echo $transporter['id']; ?>)">
                                    <?php echo $transporter['status'] === 'suspended' ? 'Réactiver' : 'Suspendre'; ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payments Tab -->
        

        <!-- Reports Tab -->
        <div id="reports-tab" class="tab-content hidden">
            <h2 class="text-2xl font-semibold text-gray-900 mb-6">Rapports et analyses</h2>
            <div class="bg-white rounded-lg shadow-md p-6">
                <p class="text-gray-600">Section des rapports détaillés et analyses de performance.</p>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings-tab" class="tab-content hidden">
            <h2 class="text-2xl font-semibold text-gray-900 mb-6">Paramètres de la plateforme</h2>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Paramètres généraux</h3>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nom de la plateforme</label>
                                <input type="text" value="EzyLivraison" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Commission (%)</label>
                                <input type="number" value="10" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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

    <!-- User Details Modal -->
    <div id="userModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Détails de l'utilisateur</h3>
                    <button onclick="closeModal('userModal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="userModalContent" class="p-6">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Delivery Details Modal -->
    <div id="deliveryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Détails de la livraison</h3>
                    <button onclick="closeModal('deliveryModal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="deliveryModalContent" class="p-6">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Transporter Details Modal -->
    <div id="transporterModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Détails du transporteur</h3>
                    <button onclick="closeModal('transporterModal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="transporterModalContent" class="p-6">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="./js/admin.js"></script>
</body>
</html>
