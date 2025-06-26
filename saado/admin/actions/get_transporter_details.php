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
    echo json_encode(['success' => false, 'message' => 'ID transporteur invalide']);
    exit();
}

$transporterId = (int)$_GET['id'];

try {
    // Get transporter details from livreur
    $stmt = $conn->prepare("SELECT * FROM livreur WHERE id_Livreur = ?");
    $stmt->bind_param("i", $transporterId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Transporteur non trouvé']);
        exit();
    }
    $transporter = $result->fetch_assoc();
    $stmt->close();

    // Get recent deliveries from commande
    $recentDeliveries = [];
    $stmt = $conn->prepare("SELECT * FROM commande WHERE id_Livreur = ? ORDER BY date_commande DESC LIMIT 10");
    $stmt->bind_param("i", $transporterId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentDeliveries[] = $row;
    }
    $stmt->close();

    // Get transporter documents
    $documents = [];
    $stmt = $conn->prepare("SELECT * FROM documents WHERE id_Livreur = ?");
    $stmt->bind_param("i", $transporterId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    $stmt->close();

    // Generate HTML (with documents and verify button)
    ob_start();
    ?>
    <div class="space-y-6">
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <h4>Informations personnelles</h4>
                <div>Nom: <?= htmlspecialchars($transporter['nom'] . ' ' . $transporter['prenom']) ?></div>
                <div>Email: <?= htmlspecialchars($transporter['email']) ?></div>
                <div>Téléphone: <?= htmlspecialchars($transporter['telephone']) ?></div>
                <div>Statut: <?= htmlspecialchars($transporter['statut']) ?></div>
                <div>Inscrit le: <?= htmlspecialchars($transporter['created_at']) ?></div>
            </div>
            <div>
                <h4>Informations professionnelles</h4>
                <div>Ville: <?= htmlspecialchars($transporter['ville']) ?></div>
                <div>Type véhicule: <?= htmlspecialchars($transporter['type_vehicule']) ?></div>
            </div>
        </div>
        <div>
            <h4>Documents du transporteur</h4>
            <div class="space-y-2">
                <?php if (empty($documents)): ?>
                    <div class="text-gray-500">Aucun document fourni</div>
                <?php else: ?>
                    <?php foreach ($documents as $doc): ?>
                        <div class="flex items-center space-x-3 p-2 bg-gray-50 rounded">
                            <span class="font-medium capitalize"><?= htmlspecialchars(str_replace('_', ' ', $doc['type_document'])) ?></span>
                            <a href="./../<?= htmlspecialchars($doc['fichier_path']) ?>" target="_blank" class="text-blue-600 underline">Voir</a>
                            <span class="text-xs px-2 py-1 rounded-full <?php
                                switch($doc['statut_validation']) {
                                    case 'valide': echo 'bg-green-100 text-green-800'; break;
                                    case 'rejeté': echo 'bg-red-100 text-red-800'; break;
                                    default: echo 'bg-yellow-100 text-yellow-800';
                                }
                            ?>">
                                <?= htmlspecialchars($doc['statut_validation']) ?>
                            </span>
                            <?php if ($doc['commentaire']): ?>
                                <span class="text-xs text-gray-500">(<?= htmlspecialchars($doc['commentaire']) ?>)</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
        <?php if ($transporter['statut'] !== 'verified'): ?>
            <div class="pt-4">
                <button class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm" onclick="verifyTransporter(<?= $transporterId ?>)">
                    Vérifier ce transporteur et activer le compte
                </button>
            </div>
        <?php endif; ?>
    </div>
    <?php
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
} catch (Exception $e) {
    error_log("Error in get_transporter_details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur']);
}
?>
