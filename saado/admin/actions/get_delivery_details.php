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
    echo json_encode(['success' => false, 'message' => 'ID livraison invalide']);
    exit();
}

$deliveryId = (int)$_GET['id'];

try {
    // Get delivery details from commande, client, and livreur
    $stmt = $conn->prepare("
        SELECT c.*, 
               cl.nom as client_nom, cl.prenom as client_prenom, cl.email as client_email,
               l.nom as livreur_nom, l.prenom as livreur_prenom, l.email as livreur_email
        FROM commande c
        JOIN client cl ON c.id_Client = cl.id_Client
        LEFT JOIN livreur l ON c.id_Livreur = l.id_Livreur
        WHERE c.id_Commande = ?
    ");
    $stmt->bind_param("i", $deliveryId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Livraison non trouvée']);
        exit();
    }
    $delivery = $result->fetch_assoc();
    $stmt->close();

    // Get delivery bids from proposition
    $bids = [];
    $stmt = $conn->prepare("
        SELECT p.*, l.nom, l.prenom, l.email
        FROM proposition p
        JOIN livreur l ON p.id_Livreur = l.id_Livreur
        WHERE p.id_Commande = ?
        ORDER BY p.created_at ASC
    ");
    $stmt->bind_param("i", $deliveryId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bids[] = $row;
    }
    $stmt->close();

    // Generate HTML (simplified for your schema)
    ob_start();
    ?>
    <div class="space-y-6">
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <h4>Informations générales</h4>
                <div>ID: #<?= $delivery['id_Commande'] ?></div>
                <div>Statut: <?= htmlspecialchars($delivery['statu']) ?></div>
                <div>Prix suggéré: <?= number_format($delivery['prix_suggere'], 2) ?>MAD</div>
                <div>Créée le: <?= htmlspecialchars($delivery['date_commande']) ?></div>
            </div>
            <div>
                <h4>Participants</h4>
                <div>Client: <?= htmlspecialchars($delivery['client_nom'] . ' ' . $delivery['client_prenom']) ?> (<?= htmlspecialchars($delivery['client_email']) ?>)</div>
                <?php if ($delivery['id_Livreur']): ?>
                <div>Livreur: <?= htmlspecialchars($delivery['livreur_nom'] . ' ' . $delivery['livreur_prenom']) ?> (<?= htmlspecialchars($delivery['livreur_email']) ?>)</div>
                <?php else: ?>
                <div>Livreur: Aucun livreur assigné</div>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <h4>Offres reçues (<?= count($bids) ?>)</h4>
            <div>
                <?php foreach ($bids as $bid): ?>
                    <div>
                        <span><?= htmlspecialchars($bid['nom'] . ' ' . $bid['prenom']) ?> (<?= htmlspecialchars($bid['email']) ?>)</span> -
                        <span><?= number_format($bid['montant'], 2) ?>MAD</span> -
                        <span><?= htmlspecialchars($bid['statut']) ?></span>
                        <?php if ($bid['message']): ?>
                            <div><?= htmlspecialchars($bid['message']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
} catch (Exception $e) {
    error_log("Error in get_delivery_details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur']);
}
?>
