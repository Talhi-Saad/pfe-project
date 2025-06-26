<?php
session_start();
require_once '../../config/database.php';

// Check if user is transporter (livreur)
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'livreur') {
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

if (!isset($input['delivery_id']) || !is_numeric($input['delivery_id']) || 
    !isset($input['bid_amount']) || !is_numeric($input['bid_amount'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    exit();
}

$deliveryId = (int)$input['delivery_id'];
$bidAmount = (float)$input['bid_amount'];
$message = isset($input['message']) ? trim($input['message']) : '';
$transporterId = $_SESSION['user_id'];

try {
    // Check if transporter exists
    $stmt = $conn->prepare("SELECT statut FROM livreur WHERE id_Livreur = ?");
    $stmt->bind_param("i", $transporterId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Profil transporteur non trouvé']);
        exit();
    }

    $transporter = $result->fetch_assoc();
    $stmt->close();

    // Get delivery info
    $stmt = $conn->prepare("
        SELECT c.id_Commande, c.statu, c.id_Client, c.id_Livreur, c.prix_suggere, c.adresse_livraison
        FROM commande c
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

    if ($delivery['statu'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Cette livraison n\'est plus disponible']);
        exit();
    }

    if ($delivery['id_Livreur']) {
        echo json_encode(['success' => false, 'message' => 'Cette livraison a déjà été acceptée']);
        exit();
    }

    // Validate bid amount
    if ($bidAmount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Le montant de l\'offre doit être positif']);
        exit();
    }

    // Create a proposition (bid)
    $stmt = $conn->prepare("
        INSERT INTO proposition (id_Commande, id_Livreur, montant, message, statut)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param("iids", $deliveryId, $transporterId, $bidAmount, $message);

    if ($stmt->execute()) {
        $bidId = $conn->insert_id;
        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Votre offre a été envoyée avec succès',
            'bid_id' => $bidId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'offre']);
    }
    
} catch (Exception $e) {
    error_log("Error in submit_bid.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur']);
}
?>
