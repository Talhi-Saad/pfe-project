<?php
session_start();
require_once '../../config/database.php';

// Check if user is transporter
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'transporter') {
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
$transporterId = $_SESSION['user_id'];
$bidAmount = isset($input['bid_amount']) ? (float)$input['bid_amount'] : null;

try {
    // Check if transporter is verified and active
    $stmt = $conn->prepare("
        SELECT u.status, tp.verification_status, tp.availability_status
        FROM users u
        JOIN transporter_profiles tp ON u.id = tp.user_id
        WHERE u.id = ? AND u.user_type = 'transporter'
    ");
    $stmt->bind_param("i", $transporterId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Profil transporteur non trouvé']);
        exit();
    }
    
    $transporter = $result->fetch_assoc();
    $stmt->close();
    
    if ($transporter['status'] !== 'active') {
        echo json_encode(['success' => false, 'message' => 'Votre compte n\'est pas actif']);
        exit();
    }
    
    if ($transporter['verification_status'] !== 'verified') {
        echo json_encode(['success' => false, 'message' => 'Votre compte n\'est pas encore vérifié']);
        exit();
    }
    
    // Get delivery info
    $stmt = $conn->prepare("
        SELECT d.id, d.status, d.client_id, d.transporter_id, d.suggested_price, d.max_price,
               d.pickup_city, d.delivery_city
        FROM deliveries d
        WHERE d.id = ?
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
    
    if ($delivery['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Cette livraison n\'est plus disponible']);
        exit();
    }
    
    if ($delivery['transporter_id']) {
        echo json_encode(['success' => false, 'message' => 'Cette livraison a déjà été acceptée']);
        exit();
    }
    
    // Validate bid amount if provided
    $finalPrice = $delivery['suggested_price'];
    if ($bidAmount !== null) {
        if ($delivery['max_price'] && $bidAmount > $delivery['max_price']) {
            echo json_encode(['success' => false, 'message' => 'Le montant proposé dépasse le prix maximum']);
            exit();
        }
        $finalPrice = $bidAmount;
    }
    
    // Calculate platform commission (10%)
    $platformCommission = $finalPrice * 0.10;
    
    // Accept the delivery
    $stmt = $conn->prepare("
        UPDATE deliveries 
        SET transporter_id = ?, status = 'accepted', final_price = ?, platform_commission = ?, accepted_at = NOW() 
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->bind_param("iddi", $transporterId, $finalPrice, $platformCommission, $deliveryId);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $stmt->close();
        
        // Update transporter availability
        $stmt = $conn->prepare("UPDATE transporter_profiles SET availability_status = 'busy' WHERE user_id = ?");
        $stmt->bind_param("i", $transporterId);
        $stmt->execute();
        $stmt->close();
        
        // Create notification for client
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type, related_id, created_at) 
            VALUES (?, 'Livraison acceptée', 'Votre livraison #{$deliveryId} a été acceptée par un transporteur.', 'delivery', ?, NOW())
        ");
        $stmt->bind_param("ii", $delivery['client_id'], $deliveryId);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Livraison acceptée avec succès',
            'final_price' => $finalPrice
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'acceptation ou livraison déjà prise']);
    }
    
} catch (Exception $e) {
    error_log("Error in accept_delivery.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur']);
}
?>
