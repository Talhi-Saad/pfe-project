// Transporter dashboard JavaScript
console.log("🚀 Loading transporter.js");

// Global variables and function declarations
let activeTab = "available";

// Immediately test that we can define global functions
console.log("📝 Defining global functions...");

// Make all functions available globally
window.switchTab = function (tabName) {
  console.log("Switching to tab:", tabName); // Debug log

  // Hide all tab contents
  document.querySelectorAll(".tab-content").forEach((content) => {
    content.classList.add("hidden");
  });

  // Show selected tab content
  const selectedTab = document.getElementById(`${tabName}-tab`);
  if (selectedTab) {
    selectedTab.classList.remove("hidden");
    console.log("Tab content shown:", `${tabName}-tab`); // Debug log
  } else {
    console.error("Tab content not found:", `${tabName}-tab`); // Debug log
  }

  // Update tab buttons
  document.querySelectorAll(".tab-btn").forEach((btn) => {
    if (btn.dataset.tab === tabName) {
      btn.classList.remove("text-gray-700", "hover:bg-gray-100");
      btn.classList.add("bg-blue-100", "text-blue-700");
    } else {
      btn.classList.remove("bg-blue-100", "text-blue-700");
      btn.classList.add("text-gray-700", "hover:bg-gray-100");
    }
  });

  activeTab = tabName;
};
console.log("✅ switchTab function defined");

window.showBidModal = function (deliveryId) {
  console.log("showBidModal called with deliveryId:", deliveryId); // Debug log

  // Find the delivery card and suggested price
  const deliveryCards = document.querySelectorAll(".bg-white");
  let suggestedPrice = "0";

  deliveryCards.forEach((card) => {
    const button = card.querySelector(`[onclick*="${deliveryId}"]`);
    if (button) {
      const priceElement = card.querySelector(".text-green-600");
      if (priceElement) {
        suggestedPrice = priceElement.textContent
          .replace("MAD", "")
          .replace(",", "")
          .trim();
        console.log("Found suggested price:", suggestedPrice); // Debug log
      }
    }
  });

  // Set modal values
  document.getElementById("bidDeliveryId").value = deliveryId;
  document.getElementById("suggestedPrice").textContent = suggestedPrice;
  document.getElementById("bidAmount").value = suggestedPrice;

  openModal("bidModal");
};
console.log("✅ showBidModal function defined");

window.openModal = function (modalId) {
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
};
console.log("✅ openModal function defined");

window.closeModal = function (modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add("hidden");
  }
};
console.log("✅ closeModal function defined");

window.updateDeliveryStatus = function (deliveryId, status) {
  console.log("updateDeliveryStatus called:", deliveryId, status); // Debug log

  const statusLabels = {
    picked_up: "récupérée",
    delivered: "livrée",
  };
  if (
    confirm(
      `Êtes-vous sûr de vouloir marquer cette livraison comme ${statusLabels[status]} ?`
    )
  ) {
    console.log("Sending request to update delivery status"); // Debug log

    fetch("./actions/update_delivery_status.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ delivery_id: deliveryId, status: status }),
    })
      .then((response) => {
        console.log("Response received:", response); // Debug log
        return response.json();
      })
      .then((data) => {
        console.log("Response data:", data); // Debug log
        if (data.success) {
          showNotification("Statut mis à jour avec succès!", "success");
          setTimeout(() => location.reload(), 1500);
        } else {
          showNotification("Erreur: " + data.message, "error");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showNotification("Une erreur est survenue", "error");
      });
  }
};
console.log("✅ updateDeliveryStatus function defined");

window.viewDeliveryDetails = function (deliveryId) {
  console.log("viewDeliveryDetails called with deliveryId:", deliveryId); // Debug log

  fetch(`./actions/get_delivery_details.php?id=${deliveryId}`)
    .then((response) => {
      console.log("Details response received:", response); // Debug log
      return response.json();
    })
    .then((data) => {
      console.log("Details response data:", data); // Debug log
      if (data.success) {
        const detailsContent = document.getElementById(
          "deliveryDetailsContent"
        );
        if (detailsContent) {
          detailsContent.innerHTML = data.html;
          openModal("deliveryDetailsModal");
        } else {
          console.error("deliveryDetailsContent element not found");
          showNotification("Erreur: Élément de contenu non trouvé", "error");
        }
      } else {
        showNotification("Erreur: " + data.message, "error");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showNotification("Une erreur est survenue", "error");
    });
};
console.log("✅ viewDeliveryDetails function defined");

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
console.log("🎉 All transporter functions loaded successfully!");
console.log("Available functions:", {
  switchTab: typeof window.switchTab,
  showBidModal: typeof window.showBidModal,
  openModal: typeof window.openModal,
  closeModal: typeof window.closeModal,
  updateDeliveryStatus: typeof window.updateDeliveryStatus,
  viewDeliveryDetails: typeof window.viewDeliveryDetails,
});

// DOM ready event listener
document.addEventListener("DOMContentLoaded", function () {
  // Add escape key handler
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      document
        .querySelectorAll(".fixed.inset-0:not(.hidden)")
        .forEach((modal) => {
          if (modal.id) {
            closeModal(modal.id);
          }
        });
    }
  });

  // Handle bid form submission
  const bidForm = document.getElementById("bidForm");
  if (bidForm) {
    bidForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);
      const bidData = {
        delivery_id: formData.get("delivery_id"),
        bid_amount: formData.get("bid_amount"),
        message: formData.get("message"),
      };

      fetch("./actions/submit_bid.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(bidData),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            showNotification("Offre envoyée avec succès !", "success");
            closeModal("bidModal");
            setTimeout(() => window.location.reload(), 1500);
          } else {
            showNotification(
              data.message || "Erreur lors de l'envoi de l'offre",
              "error"
            );
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          showNotification("Erreur lors de l'envoi de l'offre", "error");
        });
    });
  }
});
