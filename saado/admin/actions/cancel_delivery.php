<?php
session_start();
require_once '../../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
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

if (!isset($input['delivery_id']) || !is_numeric($input['delivery_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID livraison invalide']);
    exit();
}

$deliveryId = (int)$input['delivery_id'];

try {
    // Get delivery info from commande
    $stmt = $conn->prepare("SELECT id_Commande, statu FROM commande WHERE id_Commande = ?");
    $stmt->bind_param("i", $deliveryId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Livraison non trouvée']);
        exit();
    }
    $delivery = $result->fetch_assoc();
    $stmt->close();

    if ($delivery['statu'] === 'suspended' || $delivery['statu'] === 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'Cette livraison est déjà annulée']);
        exit();
    }
    if ($delivery['statu'] === 'delivered') {
        echo json_encode(['success' => false, 'message' => 'Impossible d\'annuler une livraison déjà terminée']);
        exit();
    }

    // Update delivery status to cancelled
    $stmt = $conn->prepare("UPDATE commande SET statu = 'cancelled' WHERE id_Commande = ?");
    $stmt->bind_param("i", $deliveryId);
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'Livraison annulée avec succès'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'annulation']);
    }
} catch (Exception $e) {
    error_log("Error in cancel_delivery.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur']);
}
?>
