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

if (!isset($input['bid_id']) || !is_numeric($input['bid_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID offre invalide']);
    exit();
}

$bidId = (int)$input['bid_id'];
$clientId = $_SESSION['user_id'];

try {
    // Get bid details and verify ownership
    $stmt = $conn->prepare("
        SELECT p.*, c.id_Client, c.statu as delivery_status, l.prenom, l.nom
        FROM proposition p
        JOIN commande c ON p.id_Commande = c.id_Commande
        JOIN livreur l ON p.id_Livreur = l.id_Livreur
        WHERE p.id = ? AND c.id_Client = ?
    ");
    $stmt->bind_param("ii", $bidId, $clientId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Offre non trouvée ou accès non autorisé']);
        exit();
    }

    $bid = $result->fetch_assoc();
    $stmt->close();

    if ($bid['statut'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Cette offre a déjà été traitée']);
        exit();
    }

    if ($bid['delivery_status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Cette livraison n\'est plus en attente']);
        exit();
    }

    // Update bid status to rejected
    $stmt = $conn->prepare("UPDATE proposition SET statut = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $bidId);

    if ($stmt->execute()) {
        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Offre rejetée avec succès',
            'delivery_id' => $bid['id_Commande']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors du rejet de l\'offre']);
    }
    
} catch (Exception $e) {
    error_log("Error in reject_bid.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur']);
}
?>
