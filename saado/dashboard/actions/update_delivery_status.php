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
    !isset($input['status']) || !in_array($input['status'], ['picked_up', 'in_transit', 'delivered'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    exit();
}

$deliveryId = (int)$input['delivery_id'];
$newStatus = $input['status'];
$transporterId = $_SESSION['user_id'];

try {
    // Get delivery info and verify ownership
    $stmt = $conn->prepare("
        SELECT c.id_Commande, c.statu, c.id_Client, c.id_Livreur, c.adresse_livraison, c.prix_suggere,
               cl.prenom as client_prenom, cl.nom as client_nom
        FROM commande c
        JOIN client cl ON c.id_Client = cl.id_Client
        WHERE c.id_Commande = ? AND c.id_Livreur = ?
    ");
    $stmt->bind_param("ii", $deliveryId, $transporterId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Livraison non trouvée ou accès non autorisé']);
        exit();
    }

    $delivery = $result->fetch_assoc();
    $stmt->close();
    
    // Validate status transition
    $validTransitions = [
        'accepted' => ['picked_up'],
        'picked_up' => ['delivered'],
        'in_transit' => ['delivered']
    ];

    if (!isset($validTransitions[$delivery['statu']]) ||
        !in_array($newStatus, $validTransitions[$delivery['statu']])) {
        echo json_encode(['success' => false, 'message' => 'Transition de statut invalide']);
        exit();
    }

    // Update delivery status
    $stmt = $conn->prepare("UPDATE commande SET statu = ? WHERE id_Commande = ?");
    $stmt->bind_param("si", $newStatus, $deliveryId);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Status updated successfully
        
        echo json_encode([
            'success' => true, 
            'message' => 'Statut mis à jour avec succès',
            'new_status' => $newStatus
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du statut']);
    }
    
} catch (Exception $e) {
    error_log("Error in update_delivery_status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur']);
}
?>
