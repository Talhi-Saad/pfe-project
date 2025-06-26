<?php
session_start();
require_once '../../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID utilisateur invalide']);
    exit();
}

$userId = (int)$_GET['id'];

try {
    // First determine if this is a client or livreur by checking both tables
    $user = null;
    $userType = null;

    // Try to get as client first
    $stmt = $conn->prepare("SELECT *, 'client' as type FROM client WHERE id_Client = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userType = 'client';
    }
    $stmt->close();

    // If not found as client, try as livreur
    if (!$user) {
        $stmt = $conn->prepare("SELECT *, 'livreur' as type FROM livreur WHERE id_Livreur = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $userType = 'livreur';
        }
        $stmt->close();
    }

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        exit();
    }

    // Get recent deliveries
    $recentDeliveries = [];
    if ($userType === 'client') {
        $stmt = $conn->prepare("SELECT * FROM commande WHERE id_Client = ? ORDER BY date_commande DESC LIMIT 5");
        $stmt->bind_param("i", $userId);
    } else {
        $stmt = $conn->prepare("SELECT * FROM commande WHERE id_Livreur = ? ORDER BY date_commande DESC LIMIT 5");
        $stmt->bind_param("i", $userId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentDeliveries[] = $row;
    }
    $stmt->close();

    // Generate HTML (simplified for your schema)
    ob_start();
    ?>
    <div class="space-y-6">
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <h4>Informations personnelles</h4>
                <div>Nom: <?= htmlspecialchars($user['nom'] . ' ' . $user['prenom']) ?></div>
                <div>Email: <?= htmlspecialchars($user['email']) ?></div>
                <?php if ($userType === 'livreur'): ?>
                <div>Téléphone: <?= htmlspecialchars($user['telephone']) ?></div>
                <div>Ville: <?= htmlspecialchars($user['ville']) ?></div>
                <div>Type véhicule: <?= htmlspecialchars($user['type_vehicule']) ?></div>
                <?php endif; ?>
                <div>Statut: <?= htmlspecialchars($user['statut']) ?></div>
                <div>Inscrit le: <?= htmlspecialchars($user['created_at']) ?></div>
            </div>
        </div>
        <div>
            <h4>Livraisons récentes</h4>
            <div>
                <?php foreach ($recentDeliveries as $delivery): ?>
                    <div>
                        <span>#<?= $delivery['id_Commande'] ?> - <?= htmlspecialchars($delivery['adresse_livraison']) ?></span> -
                        <span><?= htmlspecialchars($delivery['statu']) ?></span> -
                        <span><?= htmlspecialchars($delivery['date_commande']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
} catch (Exception $e) {
    error_log("Error in get_user_details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur']);
}
?>
