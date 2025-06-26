// Send package page JavaScript

document.addEventListener("DOMContentLoaded", () => {
  let currentStep = 1;
  const totalSteps = 4;

  const form = document.getElementById("send-form");
  const prevBtn = document.getElementById("prev-btn");
  const nextBtn = document.getElementById("next-btn");
  const submitBtn = document.getElementById("submit-btn");

  function updateStepUI() {
    // Hide all steps
    for (let i = 1; i <= totalSteps; i++) {
      document.getElementById(`step-${i}`).classList.add("hidden");

      // Update step icons
      const icon = document.getElementById(`step-${i}-icon`);
      if (i <= currentStep) {
        icon.className =
          "flex items-center justify-center w-12 h-12 rounded-full border-2 bg-blue-600 border-blue-600 text-white";
      } else {
        icon.className =
          "flex items-center justify-center w-12 h-12 rounded-full border-2 border-gray-300 text-gray-500";
      }
    }

    // Show current step
    document.getElementById(`step-${currentStep}`).classList.remove("hidden");

    // Update buttons
    if (currentStep === 1) {
      prevBtn.disabled = true;
      prevBtn.className =
        "px-6 py-3 rounded-lg font-medium bg-gray-100 text-gray-400 cursor-not-allowed";
    } else {
      prevBtn.disabled = false;
      prevBtn.className =
        "px-6 py-3 rounded-lg font-medium bg-gray-200 text-gray-700 hover:bg-gray-300";
    }

    if (currentStep === totalSteps) {
      nextBtn.classList.add("hidden");
      submitBtn.classList.remove("hidden");
      submitBtn.classList.add("flex");
      updateSummary();
    } else {
      nextBtn.classList.remove("hidden");
      submitBtn.classList.add("hidden");
      submitBtn.classList.remove("flex");
    }
  }

  function updateSummary() {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    const summary = document.getElementById("summary");
    summary.innerHTML = `
            <div class="flex justify-between">
                <span class="text-gray-600">Trajet:</span>
                <span class="font-medium">${
                  data.fromAddress || "Non spécifié"
                } → ${data.toAddress || "Non spécifié"}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Description:</span>
                <span class="font-medium">${
                  data.description || "Non spécifiée"
                }</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Expéditeur:</span>
                <span class="font-medium">${
                  data.senderName || "Non spécifié"
                }</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Destinataire:</span>
                <span class="font-medium">${
                  data.recipientName || "Non spécifié"
                }</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Prix suggéré:</span>
                <span class="font-medium text-green-600">${
                  data.suggestedPrice || "0"
                }MAD</span>
            </div>
        `;
  }

  function validateStep(step) {
    const stepElement = document.getElementById(`step-${step}`);
    const requiredFields = stepElement.querySelectorAll(
      "input[required], textarea[required]"
    );

    for (const field of requiredFields) {
      if (!field.value.trim()) {
        // Only focus if the step is currently visible
        if (step === currentStep) {
          field.focus();
          alert("Veuillez remplir tous les champs obligatoires");
        }
        return false;
      }
    }

    // Additional validation for specific steps
    if (step === 2) {
      const email = stepElement.querySelector('input[type="email"]');
      if (email && email.value && !isValidEmail(email.value)) {
        email.focus();
        alert("Veuillez entrer une adresse email valide");
        return false;
      }
    }

    return true;
  }

  function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  // Event listeners
  nextBtn.addEventListener("click", () => {
    if (validateStep(currentStep)) {
      if (currentStep < totalSteps) {
        currentStep++;
        updateStepUI();
      }
    }
  });

  prevBtn.addEventListener("click", () => {
    if (currentStep > 1) {
      currentStep--;
      updateStepUI();
    }
  });

  form.addEventListener("submit", (e) => {
    // Validate all steps before submission
    let allValid = true;

    for (let step = 1; step <= totalSteps; step++) {
      if (!validateStep(step)) {
        allValid = false;
        break;
      }
    }

    if (!allValid) {
      e.preventDefault();
      alert("Veuillez remplir tous les champs obligatoires.");
      return false;
    }

    // Allow form to submit normally to PHP
    submitBtn.disabled = true;
    submitBtn.innerHTML =
      '<i class="fas fa-spinner fa-spin mr-2"></i>Publication en cours...';
  });

  // Initialize
  updateStepUI();
});
