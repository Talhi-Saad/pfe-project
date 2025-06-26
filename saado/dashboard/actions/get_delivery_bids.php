<?php
session_start();
require_once '../../config/database.php';

// Check if user is client
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID livraison invalide']);
    exit();
}

$deliveryId = (int)$_GET['id'];
$userId = $_SESSION['user_id'];

try {
    // Verify delivery ownership
    $stmt = $conn->prepare("SELECT id_Commande, statu FROM commande WHERE id_Commande = ? AND id_Client = ?");
    $stmt->bind_param("ii", $deliveryId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Livraison non trouvée ou accès non autorisé']);
        exit();
    }

    $delivery = $result->fetch_assoc();
    $stmt->close();

    // Get delivery bids (propositions)
    $stmt = $conn->prepare("
        SELECT p.*, l.prenom, l.nom, l.email, l.telephone, l.type_vehicule
        FROM proposition p
        JOIN livreur l ON p.id_Livreur = l.id_Livreur
        WHERE p.id_Commande = ?
        ORDER BY p.created_at ASC
    ");
    $stmt->bind_param("i", $deliveryId);
    $stmt->execute();
    $result = $stmt->get_result();

    $bids = [];
    while ($row = $result->fetch_assoc()) {
        $bids[] = $row;
    }
    $stmt->close();
    
    // Generate HTML
    ob_start();
    ?>
    <?php if (empty($bids)): ?>
        <div class="text-center py-8">
            <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune offre reçue</h3>
            <p class="text-gray-600">Votre livraison n'a pas encore reçu d'offres de transporteurs.</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($bids as $bid): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors">
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-medium">
                                    <?= strtoupper(substr($bid['first_name'], 0, 1) . substr($bid['last_name'], 0, 1)) ?>
                                </span>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">
                                    <?= htmlspecialchars($bid['prenom'] . ' ' . $bid['nom']) ?>
                                </div>
                                <div class="text-sm text-gray-600">
                                    Transporteur professionnel
                                    <?php if ($bid['telephone']): ?>
                                        <span class="mx-2">•</span>
                                        <span><?= htmlspecialchars($bid['telephone']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-green-600">
                                <?= number_format($bid['montant'], 2) ?>MAD
                            </div>
                            <div class="text-sm text-gray-500">
                                <?= date('d/m/Y H:i', strtotime($bid['created_at'])) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle Info -->
                    <?php if ($bid['type_vehicule']): ?>
                        <div class="flex items-center text-sm text-gray-600 mb-3">
                            <i class="fas fa-truck mr-2"></i>
                            <span><?= ucfirst($bid['type_vehicule']) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Bid Message -->
                    <?php if ($bid['message']): ?>
                        <div class="bg-gray-50 rounded p-3 mb-3">
                            <div class="text-sm text-gray-700">
                                <i class="fas fa-comment mr-2"></i>
                                <?= htmlspecialchars($bid['message']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Estimated Times -->
                    <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 mb-3">
                        <?php if ($bid['heure_estimee_ramassage']): ?>
                            <div>
                                <i class="fas fa-clock mr-1"></i>
                                Récupération: <?= date('d/m/Y H:i', strtotime($bid['heure_estimee_ramassage'])) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($bid['heure_estimee_livraison']): ?>
                            <div>
                                <i class="fas fa-flag-checkered mr-1"></i>
                                Livraison: <?= date('d/m/Y H:i', strtotime($bid['heure_estimee_livraison'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-between items-center pt-3 border-t border-gray-200">
                        <div class="text-sm">
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php
                                switch($bid['statut']) {
                                    case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                    case 'accepted': echo 'bg-green-100 text-green-800'; break;
                                    case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                            ?>">
                                <?php
                                $statusLabels = [
                                    'pending' => 'En attente',
                                    'accepted' => 'Acceptée',
                                    'rejected' => 'Rejetée'
                                ];
                                echo $statusLabels[$bid['statut']] ?? ucfirst($bid['statut']);
                                ?>
                            </span>
                        </div>

                        <?php if ($bid['statut'] === 'pending' && $delivery['statu'] === 'pending'): ?>
                            <div class="space-x-2">
                                <button onclick="acceptBid(<?= $bid['id'] ?>, <?= $deliveryId ?>)"
                                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm">
                                    <i class="fas fa-check mr-1"></i>
                                    Accepter
                                </button>
                                <button onclick="rejectBid(<?= $bid['id'] ?>)"
                                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm">
                                    <i class="fas fa-times mr-1"></i>
                                    Rejeter
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($delivery['statu'] === 'pending'): ?>
            <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                    <div class="text-sm text-blue-800">
                        <p class="font-medium mb-1">Comment choisir une offre ?</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Vérifiez la note et le nombre de livraisons du transporteur</li>
                            <li>Comparez les prix proposés</li>
                            <li>Regardez les horaires de récupération et livraison</li>
                            <li>Lisez les messages des transporteurs</li>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php
    $html = ob_get_clean();
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    error_log("Error in get_delivery_bids.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur']);
}
?>
