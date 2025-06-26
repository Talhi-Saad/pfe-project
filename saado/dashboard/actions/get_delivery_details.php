<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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
    // Get delivery details with ownership check
    $stmt = $conn->prepare("
        SELECT c.*,
               cl.prenom as client_prenom, cl.nom as client_nom,
               l.prenom as livreur_prenom, l.nom as livreur_nom, l.telephone as livreur_telephone
        FROM commande c
        JOIN client cl ON c.id_Client = cl.id_Client
        LEFT JOIN livreur l ON c.id_Livreur = l.id_Livreur
        WHERE c.id_Commande = ? AND (c.id_Client = ? OR c.id_Livreur = ?)
    ");
    $stmt->bind_param("iii", $deliveryId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Livraison non trouvée ou accès non autorisé']);
        exit();
    }

    $delivery = $result->fetch_assoc();
    $stmt->close();
    
    // Generate HTML
    ob_start();
    ?>
    <div class="space-y-6">
        <!-- Status and Basic Info -->
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <h4 class="text-lg font-semibold text-gray-900 mb-3">Informations générales</h4>
                <div class="space-y-2 text-sm">
                    <div><span class="font-medium">Livraison:</span> #<?= $delivery['id_Commande'] ?></div>
                    <div><span class="font-medium">Statut:</span>
                        <span class="px-2 py-1 rounded-full text-xs <?php
                            switch($delivery['statu']) {
                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                case 'accepted': echo 'bg-blue-100 text-blue-800'; break;
                                case 'picked_up': echo 'bg-purple-100 text-purple-800'; break;
                                case 'delivered': echo 'bg-green-100 text-green-800'; break;
                                case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                default: echo 'bg-gray-100 text-gray-800';
                            }
                        ?>">
                            <?php
                            $statusLabels = [
                                'pending' => 'En attente',
                                'accepted' => 'Acceptée',
                                'picked_up' => 'Récupérée',
                                'delivered' => 'Livrée',
                                'cancelled' => 'Annulée'
                            ];
                            echo $statusLabels[$delivery['statu']] ?? ucfirst($delivery['statu']);
                            ?>
                        </span>
                    </div>
                    <div><span class="font-medium">Prix suggéré:</span> <?= number_format($delivery['prix_suggere'], 2) ?>MAD</div>
                    <div><span class="font-medium">Créée le:</span> <?= date('d/m/Y', strtotime($delivery['date_commande'])) ?></div>
                </div>
            </div>

            <div>
                <h4 class="text-lg font-semibold text-gray-900 mb-3">Client</h4>
                <div class="p-3 bg-blue-50 rounded">
                    <div class="font-medium text-blue-900">
                        <?= htmlspecialchars($delivery['client_prenom'] . ' ' . $delivery['client_nom']) ?>
                    </div>
                </div>

                <?php if ($delivery['id_Livreur']): ?>
                    <h4 class="text-lg font-semibold text-gray-900 mb-3 mt-4">Transporteur assigné</h4>
                    <div class="p-3 bg-green-50 rounded">
                        <div class="font-medium text-green-900">
                            <?= htmlspecialchars($delivery['livreur_prenom'] . ' ' . $delivery['livreur_nom']) ?>
                        </div>
                        <?php if ($delivery['livreur_telephone']): ?>
                            <div class="text-sm text-green-800">
                                <i class="fas fa-phone mr-1"></i>
                                <?= htmlspecialchars($delivery['livreur_telephone']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Addresses and Route -->
        <div>
            <h4 class="text-lg font-semibold text-gray-900 mb-3">Itinéraire</h4>
            <div class="space-y-3">
                <?php if (!empty($delivery['adresse_depart'])): ?>
                <div class="p-3 bg-green-50 rounded text-sm">
                    <div class="font-medium text-green-800 mb-1">
                        <i class="fas fa-map-marker-alt mr-2"></i>Adresse de départ
                    </div>
                    <div class="text-green-700"><?= htmlspecialchars($delivery['adresse_depart']) ?></div>
                </div>
                <?php endif; ?>

                <div class="p-3 bg-red-50 rounded text-sm">
                    <div class="font-medium text-red-800 mb-1">
                        <i class="fas fa-map-marker-alt mr-2"></i>Adresse de livraison
                    </div>
                    <div class="text-red-700"><?= htmlspecialchars($delivery['adresse_livraison']) ?></div>
                </div>
            </div>
        </div>

        <!-- Recipient Information -->
        <?php if (!empty($delivery['nom_destinataire'])): ?>
        <div>
            <h4 class="text-lg font-semibold text-gray-900 mb-3">Destinataire</h4>
            <div class="p-3 bg-purple-50 rounded text-sm">
                <div class="font-medium text-purple-800 mb-1">
                    <i class="fas fa-user-tag mr-2"></i><?= htmlspecialchars($delivery['nom_destinataire']) ?>
                </div>
                <?php if (!empty($delivery['telephone_destinataire'])): ?>
                    <div class="text-purple-700">
                        <i class="fas fa-phone mr-2"></i><?= htmlspecialchars($delivery['telephone_destinataire']) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($delivery['email_destinataire'])): ?>
                    <div class="text-purple-700">
                        <i class="fas fa-envelope mr-2"></i><?= htmlspecialchars($delivery['email_destinataire']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Package Information -->
        <?php if (!empty($delivery['description_colis']) || !empty($delivery['poids']) || !empty($delivery['dimensions']) || !empty($delivery['valeur_declaree']) || $delivery['fragile']): ?>
        <div>
            <h4 class="text-lg font-semibold text-gray-900 mb-3">Informations du colis</h4>
            <div class="p-4 bg-gray-50 rounded">
                <?php if (!empty($delivery['description_colis'])): ?>
                    <div class="mb-3">
                        <span class="font-medium text-gray-900">Description:</span>
                        <p class="text-gray-700 mt-1"><?= htmlspecialchars($delivery['description_colis']) ?></p>
                    </div>
                <?php endif; ?>

                <div class="grid md:grid-cols-2 gap-4 text-sm">
                    <?php if (!empty($delivery['poids'])): ?>
                        <div><span class="font-medium">Poids:</span> <?= number_format($delivery['poids'], 1) ?> kg</div>
                    <?php endif; ?>
                    <?php if (!empty($delivery['dimensions'])): ?>
                        <div><span class="font-medium">Dimensions:</span> <?= htmlspecialchars($delivery['dimensions']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($delivery['valeur_declaree'])): ?>
                        <div><span class="font-medium">Valeur déclarée:</span> <?= number_format($delivery['valeur_declaree'], 2) ?>MAD</div>
                    <?php endif; ?>
                    <?php if ($delivery['fragile']): ?>
                        <div class="text-orange-600 font-medium">⚠️ FRAGILE - Manipulation délicate requise</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Desired Dates -->
        <?php if (!empty($delivery['date_ramassage_souhaitee']) || !empty($delivery['date_livraison_souhaitee'])): ?>
        <div>
            <h4 class="text-lg font-semibold text-gray-900 mb-3">Dates souhaitées</h4>
            <div class="p-3 bg-indigo-50 rounded text-sm space-y-2">
                <?php if (!empty($delivery['date_ramassage_souhaitee'])): ?>
                    <div class="text-indigo-700">
                        <i class="fas fa-calendar mr-2"></i>
                        <span class="font-medium">Récupération souhaitée:</span>
                        <?= date('d/m/Y', strtotime($delivery['date_ramassage_souhaitee'])) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($delivery['date_livraison_souhaitee'])): ?>
                    <div class="text-indigo-700">
                        <i class="fas fa-calendar mr-2"></i>
                        <span class="font-medium">Livraison souhaitée:</span>
                        <?= date('d/m/Y', strtotime($delivery['date_livraison_souhaitee'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Additional Info -->
        <?php if (!empty($delivery['distance_estimee']) || !empty($delivery['duree_estimee'])): ?>
        <div>
            <h4 class="text-lg font-semibold text-gray-900 mb-3">Informations supplémentaires</h4>
            <div class="p-4 bg-gray-50 rounded text-sm space-y-2">
                <?php if (!empty($delivery['distance_estimee'])): ?>
                    <div><strong>Distance estimée:</strong> <?= number_format($delivery['distance_estimee'], 1) ?> km</div>
                <?php endif; ?>
                <?php if (!empty($delivery['duree_estimee'])): ?>
                    <div><strong>Durée estimée:</strong> <?= $delivery['duree_estimee'] ?> minutes</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Timeline -->
        <div>
            <h4 class="text-lg font-semibold text-gray-900 mb-3">Chronologie</h4>
            <div class="space-y-2 text-sm">
                <div class="flex items-center text-gray-600">
                    <i class="fas fa-clock mr-2"></i>
                    Créée le <?= date('d/m/Y', strtotime($delivery['date_commande'])) ?>
                </div>

                <?php if (!empty($delivery['date_acceptation'])): ?>
                <div class="flex items-center text-blue-600">
                    <i class="fas fa-check mr-2"></i>
                    Acceptée le <?= date('d/m/Y à H:i', strtotime($delivery['date_acceptation'])) ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($delivery['date_ramassage'])): ?>
                <div class="flex items-center text-purple-600">
                    <i class="fas fa-box mr-2"></i>
                    Récupérée le <?= date('d/m/Y à H:i', strtotime($delivery['date_ramassage'])) ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($delivery['date_livraison'])): ?>
                <div class="flex items-center text-green-600">
                    <i class="fas fa-truck mr-2"></i>
                    Livrée le <?= date('d/m/Y à H:i', strtotime($delivery['date_livraison'])) ?>
                </div>
                <?php endif; ?>
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
