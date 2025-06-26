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

if (!isset($input['user_id']) || !is_numeric($input['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID utilisateur invalide']);
    exit();
}

$userId = (int)$input['user_id'];

try {
    // Try to get as client
    $stmt = $conn->prepare("SELECT statut, nom, prenom FROM client WHERE id_Client = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userType = 'client';
    } else {
        // Try to get as livreur
        $stmt = $conn->prepare("SELECT statut, nom, prenom FROM livreur WHERE id_Livreur = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $userType = 'livreur';
        } else {
            echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
            exit();
        }
    }
    $stmt->close();

    // Determine new status
    $newStatus = ($user['statut'] === 'suspended') ? 'active' : 'suspended';

    // Update user status
    if ($userType === 'client') {
        $stmt = $conn->prepare("UPDATE client SET statut = ? WHERE id_Client = ?");
        $stmt->bind_param("si", $newStatus, $userId);
    } else {
        $stmt = $conn->prepare("UPDATE livreur SET statut = ? WHERE id_Livreur = ?");
        $stmt->bind_param("si", $newStatus, $userId);
    }
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => true,
            'message' => "Utilisateur " . ($newStatus === 'suspended' ? 'suspendu' : 'réactivé') . " avec succès",
            'new_status' => $newStatus
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du statut']);
    }
} catch (Exception $e) {
    error_log("Error in suspend_user.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur']);
}
?>
