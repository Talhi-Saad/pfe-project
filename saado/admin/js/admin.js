// Admin dashboard JavaScript

document.addEventListener("DOMContentLoaded", () => {
  let activeTab = "overview";

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

  // Modal functions
  window.openModal = (modalId) => {
    const modal = document.getElementById(modalId);
    modal.classList.remove("hidden");

    // Add click outside to close
    modal.addEventListener("click", function (e) {
      if (e.target === modal) {
        closeModal(modalId);
      }
    });
  };

  window.closeModal = (modalId) => {
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

  // Global functions for actions
  window.viewUser = (userId) => {
    fetch(`./actions/get_user_details.php?id=${userId}`)
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          document.getElementById("userModalContent").innerHTML = data.html;
          openModal("userModal");
        } else {
          alert("Erreur: " + data.message);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("Une erreur est survenue lors du chargement des détails");
      });
  };

  window.suspendUser = (userId) => {
    if (confirm(`Voulez-vous suspendre/réactiver cet utilisateur ?`)) {
      fetch("./actions/suspend_user.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ user_id: userId }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            location.reload(); // Refresh page to show updated data
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

  window.viewTransporter = (transporterId) => {
    fetch(`./actions/get_transporter_details.php?id=${transporterId}`)
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          document.getElementById("transporterModalContent").innerHTML =
            data.html;
          openModal("transporterModal");
        } else {
          alert("Erreur: " + data.message);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("Une erreur est survenue lors du chargement des détails");
      });
  };

  window.verifyTransporter = (transporterId) => {
    if (confirm(`Vérifier ce transporteur ?`)) {
      fetch("./actions/verify_transporter.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ transporter_id: transporterId }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            location.reload(); // Refresh page to show updated data
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

  window.suspendTransporter = (transporterId) => {
    if (confirm(`Voulez-vous suspendre/réactiver ce transporteur ?`)) {
      fetch("./actions/suspend_transporter.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ transporter_id: transporterId }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            location.reload(); // Refresh page to show updated data
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

  window.viewDelivery = (deliveryId) => {
    fetch(`./actions/get_delivery_details.php?id=${deliveryId}`)
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          document.getElementById("deliveryModalContent").innerHTML = data.html;
          openModal("deliveryModal");
        } else {
          alert("Erreur: " + data.message);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("Une erreur est survenue lors du chargement des détails");
      });
  };

  window.cancelDelivery = (deliveryId) => {
    if (confirm(`Voulez-vous annuler cette livraison ?`)) {
      fetch("./actions/cancel_delivery.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ delivery_id: deliveryId }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            location.reload(); // Refresh page to show updated data
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

  // Event listeners
  document.querySelectorAll(".tab-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      switchTab(btn.dataset.tab);
    });
  });

  // Initialize - no need to render data as it's now handled by PHP
});
