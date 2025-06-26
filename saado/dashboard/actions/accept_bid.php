<?php
session_start();
require_once '../../config/database.php';

// Check if user is client
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['bid_id']) || !is_numeric($input['bid_id']) || 
    !isset($input['delivery_id']) || !is_numeric($input['delivery_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    exit();
}

$bidId = (int)$input['bid_id'];
$deliveryId = (int)$input['delivery_id'];
$clientId = $_SESSION['user_id'];

try {
    // Start transaction
    $conn->begin_transaction();

    // Verify delivery ownership and status
    $stmt = $conn->prepare("SELECT id_Commande, statu, id_Livreur FROM commande WHERE id_Commande = ? AND id_Client = ?");
    $stmt->bind_param("ii", $deliveryId, $clientId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Livraison non trouvée ou accès non autorisé');
    }

    $delivery = $result->fetch_assoc();
    $stmt->close();

    if ($delivery['statu'] !== 'pending') {
        throw new Exception('Cette livraison n\'est plus en attente');
    }

    if ($delivery['id_Livreur']) {
        throw new Exception('Cette livraison a déjà été acceptée par un transporteur');
    }

    // Get bid details
    $stmt = $conn->prepare("
        SELECT p.*, l.prenom, l.nom
        FROM proposition p
        JOIN livreur l ON p.id_Livreur = l.id_Livreur
        WHERE p.id = ? AND p.id_Commande = ? AND p.statut = 'pending'
    ");
    $stmt->bind_param("ii", $bidId, $deliveryId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Offre non trouvée ou déjà traitée');
    }

    $bid = $result->fetch_assoc();
    $stmt->close();
    
    // Accept the bid and update delivery
    $stmt = $conn->prepare("
        UPDATE commande
        SET id_Livreur = ?, statu = 'accepted', prix_suggere = ?, date_acceptation = NOW()
        WHERE id_Commande = ?
    ");
    $stmt->bind_param("idi", $bid['id_Livreur'], $bid['montant'], $deliveryId);

    if (!$stmt->execute()) {
        throw new Exception('Erreur lors de l\'acceptation de la livraison');
    }
    $stmt->close();

    // Update the accepted bid status
    $stmt = $conn->prepare("UPDATE proposition SET statut = 'accepted' WHERE id = ?");
    $stmt->bind_param("i", $bidId);
    $stmt->execute();
    $stmt->close();

    // Reject all other bids for this delivery
    $stmt = $conn->prepare("UPDATE proposition SET statut = 'rejected' WHERE id_Commande = ? AND id != ?");
    $stmt->bind_param("ii", $deliveryId, $bidId);
    $stmt->execute();
    $stmt->close();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Offre acceptée avec succès',
        'transporter_name' => $bid['prenom'] . ' ' . $bid['nom'],
        'final_price' => $bid['montant']
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    error_log("Error in accept_bid.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
