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

if (!isset($input['transporter_id']) || !is_numeric($input['transporter_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID transporteur invalide']);
    exit();
}

$transporterId = (int)$input['transporter_id'];

try {
    // Get transporter info
    $stmt = $conn->prepare("SELECT nom, prenom, email, statut FROM livreur WHERE id_Livreur = ?");
    $stmt->bind_param("i", $transporterId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Transporteur non trouvé']);
        exit();
    }
    $transporter = $result->fetch_assoc();
    $stmt->close();

    if ($transporter['statut'] === 'verified') {
        echo json_encode(['success' => false, 'message' => 'Ce transporteur est déjà vérifié']);
        exit();
    }

    // Update transporter verification status
    $stmt = $conn->prepare("UPDATE livreur SET statut = 'verified' WHERE id_Livreur = ?");
    $stmt->bind_param("i", $transporterId);
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'Transporteur vérifié avec succès'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la vérification']);
    }
} catch (Exception $e) {
    error_log("Error in verify_transporter.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur']);
}
?>
