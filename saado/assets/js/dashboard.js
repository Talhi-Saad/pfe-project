// Client dashboard JavaScript
console.log("🚀 Loading dashboard.js");

// Global variables
let activeTab = "deliveries";

// Define all functions immediately (not waiting for DOMContentLoaded)
console.log("📝 Defining global functions...");

// Switch tab function
function switchTab(tabName) {
  activeTab = tabName;

  // Update tab buttons
  document.querySelectorAll(".tab-btn").forEach((btn) => {
    if (btn.dataset.tab === tabName) {
      btn.className = btn.className.replace(
        "text-gray-700 hover:bg-gray-100",
        "bg-blue-100 text-blue-700"
      );
    } else {
      btn.className = btn.className.replace(
        "bg-blue-100 text-blue-700",
        "text-gray-700 hover:bg-gray-100"
      );
    }
  });

  // Update tab content
  document.querySelectorAll(".tab-content").forEach((content) => {
    if (content.id === `${tabName}-tab`) {
      content.classList.remove("hidden");
    } else {
      content.classList.add("hidden");
    }
  });
}

// Make switchTab globally available
window.switchTab = switchTab;
console.log("✅ switchTab function defined");

// Modal functions
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove("hidden");

    // Add click outside to close
    modal.addEventListener("click", function (e) {
      if (e.target === modal) {
        closeModal(modalId);
      }
    });
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add("hidden");
  }
}

// Make modal functions globally available
window.openModal = openModal;
window.closeModal = closeModal;
console.log("✅ Modal functions defined");

// View bids function
function viewBids(deliveryId) {
  console.log("viewBids called with deliveryId:", deliveryId);

  fetch(`./actions/get_delivery_bids.php?id=${deliveryId}`)
    .then((response) => {
      console.log("Bids response received:", response);
      return response.json();
    })
    .then((data) => {
      console.log("Bids response data:", data);
      if (data.success) {
        const bidsContent = document.getElementById("bidsContent");
        if (bidsContent) {
          bidsContent.innerHTML = data.html;
          openModal("bidsModal");
        } else {
          console.error("bidsContent element not found");
          alert("Erreur: Élément de contenu non trouvé");
        }
      } else {
        alert("Erreur: " + data.message);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      alert("Une erreur est survenue");
    });
}

// Make viewBids globally available
window.viewBids = viewBids;
console.log("✅ viewBids function defined");

// Utility function for notifications
function showNotification(message, type = "info") {
  // Create notification element
  const notification = document.createElement("div");
  notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm ${
    type === "success"
      ? "bg-green-500 text-white"
      : type === "error"
      ? "bg-red-500 text-white"
      : type === "warning"
      ? "bg-yellow-500 text-white"
      : "bg-blue-500 text-white"
  }`;
  notification.innerHTML = `
    <div class="flex items-center justify-between">
      <span>${message}</span>
      <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
        <i class="fas fa-times"></i>
      </button>
    </div>
  `;

  document.body.appendChild(notification);

  // Auto remove after 5 seconds
  setTimeout(() => {
    if (notification.parentElement) {
      notification.remove();
    }
  }, 5000);
}

// All functions are now defined and available globally
console.log("🎉 All client dashboard functions loaded successfully!");
console.log("Available functions:", {
  switchTab: typeof window.switchTab,
  openModal: typeof window.openModal,
  closeModal: typeof window.closeModal,
  viewBids: typeof window.viewBids,
});

document.addEventListener("DOMContentLoaded", () => {
  // Event listeners for tab switching
  document.querySelectorAll(".tab-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      switchTab(btn.dataset.tab);
    });
  });

  // Add click handlers for delivery action buttons
  function setupDeliveryActionButtons() {
    // For each delivery card
    document.querySelectorAll("[data-delivery-id]").forEach((card) => {
      // Annuler button
      const cancelBtn = card.querySelector(".cancel-delivery");
      if (cancelBtn) {
        cancelBtn.addEventListener("click", function (e) {
          e.preventDefault();
          const deliveryId = card.getAttribute("data-delivery-id");
          if (
            deliveryId &&
            confirm("Êtes-vous sûr de vouloir annuler cette livraison ?")
          ) {
            fetch("./actions/cancel_delivery.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ delivery_id: deliveryId }),
            })
              .then((response) => response.json())
              .then((data) => {
                if (data.success) {
                  location.reload();
                } else {
                  alert("Erreur: " + data.message);
                }
              })
              .catch((error) => {
                console.error("Error:", error);
                alert("Une erreur est survenue");
              });
          }
        });
      }

      // Détails complets button
      const detailsBtn = card.querySelector(".details-delivery");
      if (detailsBtn) {
        detailsBtn.addEventListener("click", function (e) {
          e.preventDefault();
          const deliveryId = card.getAttribute("data-delivery-id");
          if (deliveryId) {
            fetch(`./actions/get_delivery_details.php?id=${deliveryId}`)
              .then((response) => response.json())
              .then((data) => {
                if (data.success) {
                  document.getElementById("deliveryDetailsContent").innerHTML =
                    data.html;
                  document
                    .getElementById("deliveryDetailsModal")
                    .classList.remove("hidden");
                } else {
                  alert("Erreur: " + data.message);
                }
              })
              .catch((error) => {
                console.error("Error:", error);
                alert("Une erreur est survenue");
              });
          }
        });
      }
    });
  }

  // Call setup after DOM loaded
  setupDeliveryActionButtons();

  // Modal functions
  window.openModal = function (modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove("hidden");

    // Add click outside to close
    modal.addEventListener("click", function (e) {
      if (e.target === modal) {
        closeModal(modalId);
      }
    });
  };

  window.closeModal = function (modalId) {
    document.getElementById(modalId).classList.add("hidden");
  };

  // Add escape key to close modals
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      const openModals = document.querySelectorAll(
        ".fixed.inset-0:not(.hidden)"
      );
      openModals.forEach((modal) => {
        if (modal.id) {
          closeModal(modal.id);
        }
      });
    }
  });

  // View bids function
  window.viewBids = function (deliveryId) {
    fetch(`./actions/get_delivery_bids.php?id=${deliveryId}`)
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          document.getElementById("bidsContent").innerHTML = data.html;
          openModal("bidsModal");
        } else {
          alert("Erreur: " + data.message);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("Une erreur est survenue");
      });
  };

  // Accept bid function
  window.acceptBid = function (bidId, deliveryId) {
    if (confirm("Êtes-vous sûr de vouloir accepter cette offre ?")) {
      fetch("./actions/accept_bid.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ bid_id: bidId, delivery_id: deliveryId }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            alert("Offre acceptée avec succès !");
            closeModal("bidsModal");
            location.reload();
          } else {
            alert("Erreur: " + data.message);
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          alert("Une erreur est survenue");
        });
    }
  };

  // Reject bid function
  window.rejectBid = function (bidId) {
    if (confirm("Êtes-vous sûr de vouloir rejeter cette offre ?")) {
      fetch("./actions/reject_bid.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ bid_id: bidId }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            alert("Offre rejetée");
            // Refresh the bids modal content
            const deliveryId = data.delivery_id;
            viewBids(deliveryId);
          } else {
            alert("Erreur: " + data.message);
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          alert("Une erreur est survenue");
        });
    }
  };

  // Form submission success handler (after PHP processes the form)
  const createForm = document.getElementById("create-delivery-form");
  if (createForm) {
    createForm.addEventListener("submit", function (e) {
      // Let PHP handle the form submission
      // Add loading state to submit button
      const submitBtn = this.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML =
          '<i class="fas fa-spinner fa-spin mr-2"></i>Création en cours...';
      }
    });
  }
});
