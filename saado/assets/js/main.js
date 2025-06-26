// Main JavaScript file for the landing page

document.addEventListener("DOMContentLoaded", () => {
  // Mobile menu functionality
  const mobileMenuBtn = document.getElementById("mobile-menu-btn")
  const mobileMenu = document.getElementById("mobile-menu")

  if (mobileMenuBtn && mobileMenu) {
    mobileMenuBtn.addEventListener("click", () => {
      mobileMenu.classList.toggle("hidden")
      const icon = mobileMenuBtn.querySelector("i")
      if (mobileMenu.classList.contains("hidden")) {
        icon.className = "fas fa-bars"
      } else {
        icon.className = "fas fa-times"
      }
    })
  }

  // Testimonials functionality
  const testimonials = [
    {
      name: "Marie Dubois",
      role: "Client",
      content: "Service rapide et fiable. J'ai économisé 60% sur mes frais de livraison !",
      rating: 5,
    },
    {
      name: "Pierre Martin",
      role: "Transporteur",
      content: "Excellente plateforme pour optimiser mes trajets et gagner un revenu supplémentaire.",
      rating: 5,
    },
    {
      name: "Sophie Laurent",
      role: "Client",
      content: "Interface intuitive et suivi en temps réel. Je recommande vivement !",
      rating: 5,
    },
  ]

  let currentTestimonial = 0

  function updateTestimonial() {
    const testimonial = testimonials[currentTestimonial]

    // Update content
    document.getElementById("testimonial-content").textContent = `"${testimonial.content}"`
    document.getElementById("testimonial-name").textContent = testimonial.name
    document.getElementById("testimonial-role").textContent = testimonial.role

    // Update stars
    const starsContainer = document.getElementById("testimonial-stars")
    starsContainer.innerHTML = ""
    for (let i = 0; i < testimonial.rating; i++) {
      const star = document.createElement("i")
      star.className = "fas fa-star text-yellow-400"
      starsContainer.appendChild(star)
    }

    // Update dots
    const dotsContainer = document.getElementById("testimonial-dots")
    dotsContainer.innerHTML = ""
    testimonials.forEach((_, index) => {
      const dot = document.createElement("button")
      dot.className = `w-3 h-3 rounded-full transition-colors ${
        index === currentTestimonial ? "bg-blue-600" : "bg-gray-300"
      }`
      dot.addEventListener("click", () => {
        currentTestimonial = index
        updateTestimonial()
      })
      dotsContainer.appendChild(dot)
    })
  }

  // Initialize testimonials
  updateTestimonial()

  // Auto-rotate testimonials every 5 seconds
  setInterval(() => {
    currentTestimonial = (currentTestimonial + 1) % testimonials.length
    updateTestimonial()
  }, 5000)

  // Smooth scrolling for anchor links
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault()
      const target = document.querySelector(this.getAttribute("href"))
      if (target) {
        target.scrollIntoView({
          behavior: "smooth",
          block: "start",
        })
      }
    })
  })
})
