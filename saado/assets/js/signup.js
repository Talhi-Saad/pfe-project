// Signup page JavaScript

document.addEventListener("DOMContentLoaded", () => {
  const clientTab = document.getElementById("client-tab");
  const transporterTab = document.getElementById("transporter-tab");
  const transporterFields = document.getElementById("transporter-fields");
  const transporterNotice = document.getElementById("transporter-notice");
  const submitBtn = document.getElementById("submit-btn");
  const form = document.getElementById("signup-form");

  let userType = "client";

  clientTab.addEventListener("click", () => switchTab("client"));
  transporterTab.addEventListener("click", () => switchTab("transporter"));

  // Password visibility toggle
  document
    .getElementById("toggle-password")
    .addEventListener("click", function () {
      const passwordInput = document.getElementById("password");
      const icon = this.querySelector("i");

      if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      } else {
        passwordInput.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      }
    });

  // Confirm password visibility toggle
  document
    .getElementById("toggle-confirm-password")
    .addEventListener("click", function () {
      const confirmPasswordInput = document.getElementById("confirmPassword");
      const icon = this.querySelector("i");

      if (confirmPasswordInput.type === "password") {
        confirmPasswordInput.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      } else {
        confirmPasswordInput.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      }
    });

  // Update hidden userType field when tab changes
  function updateUserTypeField() {
    document.getElementById("userType").value = userType;
  }

  // Enhanced tab switching functionality
  function switchTab(selectedTab) {
    if (selectedTab === "client") {
      userType = "client";
      clientTab.classList.add("bg-white", "text-blue-600", "shadow-sm");
      transporterTab.classList.remove("bg-white", "text-blue-600", "shadow-sm");
      transporterFields.classList.add("hidden");
      transporterNotice.classList.add("hidden");
      submitBtn.innerHTML =
        '<i class="fas fa-user mr-2"></i>Créer mon compte client';

      // Remove required attribute and disable file inputs
      ["driver_license", "car_card", "car_photo"].forEach((id) => {
        const input = document.getElementById(id);
        input.removeAttribute("required");
        input.disabled = true;
      });

      // Remove other required attributes
      document.getElementById("vehicleType").removeAttribute("required");
      document.getElementById("vehicleCapacity").removeAttribute("required");
      document.getElementById("licenseNumber").removeAttribute("required");
    } else {
      userType = "transporter";
      transporterTab.classList.add("bg-white", "text-blue-600", "shadow-sm");
      clientTab.classList.remove("bg-white", "text-blue-600", "shadow-sm");
      transporterFields.classList.remove("hidden");
      transporterNotice.classList.remove("hidden");
      submitBtn.innerHTML =
        '<i class="fas fa-truck mr-2"></i>Créer mon compte transporteur';

      // Add required attribute and enable file inputs
      ["driver_license", "car_card", "car_photo"].forEach((id) => {
        const input = document.getElementById(id);
        input.setAttribute("required", "required");
        input.disabled = false;
      });

      // Add other required attributes
      document
        .getElementById("vehicleType")
        .setAttribute("required", "required");
      document
        .getElementById("vehicleCapacity")
        .setAttribute("required", "required");
      document
        .getElementById("licenseNumber")
        .setAttribute("required", "required");
    }
    updateUserTypeField();
  }

  // Client-side form validation
  function validateForm() {
    const firstName = document.getElementById("firstName").value.trim();
    const lastName = document.getElementById("lastName").value.trim();
    const email = document.getElementById("email").value.trim();
    const phone = document.getElementById("phone").value.trim();
    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirmPassword").value;
    const acceptTerms = document.getElementById("acceptTerms").checked;

    // Clear previous error messages
    clearErrorMessages();

    let isValid = true;

    // Basic field validation
    if (!firstName) {
      showFieldError("firstName", "Le prénom est requis");
      isValid = false;
    }

    if (!lastName) {
      showFieldError("lastName", "Le nom est requis");
      isValid = false;
    }

    if (!email) {
      showFieldError("email", "L'email est requis");
      isValid = false;
    } else if (!isValidEmail(email)) {
      showFieldError("email", "Format d'email invalide");
      isValid = false;
    }

    if (!phone) {
      showFieldError("phone", "Le téléphone est requis");
      isValid = false;
    } else if (!isValidPhone(phone)) {
      showFieldError("phone", "Format de téléphone invalide");
      isValid = false;
    }

    if (!password) {
      showFieldError("password", "Le mot de passe est requis");
      isValid = false;
    } else if (password.length < 8) {
      showFieldError(
        "password",
        "Le mot de passe doit contenir au moins 8 caractères"
      );
      isValid = false;
    }

    if (password !== confirmPassword) {
      showFieldError(
        "confirmPassword",
        "Les mots de passe ne correspondent pas"
      );
      isValid = false;
    }

    if (!acceptTerms) {
      showFieldError(
        "acceptTerms",
        "Vous devez accepter les conditions d'utilisation"
      );
      isValid = false;
    }

    // Transporter-specific validation
    if (userType === "transporter") {
      const vehicleType = document.getElementById("vehicleType").value;
      const vehicleCapacity = document.getElementById("vehicleCapacity").value;
      const licenseNumber = document
        .getElementById("licenseNumber")
        .value.trim();

      if (!vehicleType) {
        showFieldError("vehicleType", "Le type de véhicule est requis");
        isValid = false;
      }

      if (!vehicleCapacity) {
        showFieldError(
          "vehicleCapacity",
          "La capacité du véhicule est requise"
        );
        isValid = false;
      } else if (isNaN(vehicleCapacity) || vehicleCapacity <= 0) {
        showFieldError(
          "vehicleCapacity",
          "La capacité doit être un nombre positif"
        );
        isValid = false;
      }

      if (!licenseNumber) {
        showFieldError("licenseNumber", "Le numéro de permis est requis");
        isValid = false;
      } else if (licenseNumber.length < 5) {
        showFieldError(
          "licenseNumber",
          "Le numéro de permis doit contenir au moins 5 caractères"
        );
        isValid = false;
      }
    }

    return isValid;
  }

  // Helper functions for validation
  function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  function isValidPhone(phone) {
    const phoneRegex = /^[+]?[0-9\s\-\(\)]{10,}$/;
    return phoneRegex.test(phone);
  }

  function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const errorDiv = document.createElement("div");
    errorDiv.className = "text-red-500 text-sm mt-1 field-error";
    errorDiv.textContent = message;

    // Add error styling to field
    field.classList.add("border-red-500");

    // Insert error message after the field
    field.parentNode.insertBefore(errorDiv, field.nextSibling);
  }

  function clearErrorMessages() {
    // Remove all error messages
    const errorMessages = document.querySelectorAll(".field-error");
    errorMessages.forEach((error) => error.remove());

    // Remove error styling from fields
    const fields = document.querySelectorAll("input, select, textarea");
    fields.forEach((field) => field.classList.remove("border-red-500"));
  }

  // Form submission with validation
  form.addEventListener("submit", function (e) {
    if (!validateForm()) {
      e.preventDefault();
      return false;
    }

    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML =
      '<i class="fas fa-spinner fa-spin mr-2"></i>Création en cours...';

    // Form will submit normally to PHP
    return true;
  });
});
