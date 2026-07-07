/* ==========================================================================
   CLIENT INTERACTION & DYNAMIC BEHAVIORS - KURIKHAI CONSTRUCTION PTE LTD
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Sticky Header Scroll Effect (only for pages where header is transparent by default)
    const header = document.getElementById('header');
    
    const handleScroll = () => {
        if (!header) return;
        
        // If it is the home page, the header doesn't have Scrolled class by default. We toggle it.
        const isHomePage = window.location.pathname.endsWith('index.html') || 
                           window.location.pathname.endsWith('/') || 
                           window.location.pathname === '';
                           
        if (isHomePage) {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        } else {
            // Keep scrolled class on subpages permanently
            header.classList.add('scrolled');
        }
    };
    
    // Initial run
    handleScroll();
    window.addEventListener('scroll', handleScroll);


    // 2. Mobile Navbar Hamburger Toggle
    const burgerMenu = document.getElementById('burger-menu');
    const navList = document.getElementById('nav-list');
    
    if (burgerMenu && navList) {
        burgerMenu.addEventListener('click', (e) => {
            e.stopPropagation();
            burgerMenu.classList.toggle('active');
            navList.classList.toggle('active');
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!navList.contains(e.target) && !burgerMenu.contains(e.target)) {
                burgerMenu.classList.remove('active');
                navList.classList.remove('active');
            }
        });
        
        // Close menu when clicking links
        const navLinks = navList.querySelectorAll('.nav-link, .btn');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                burgerMenu.classList.remove('active');
                navList.classList.remove('active');
            });
        });
    }


    // 3. Scroll Animations (Intersection Observer)
    const fadeElements = document.querySelectorAll('.fade-in-up');
    
    if ('IntersectionObserver' in window) {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const appearanceObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('appear');
                    // Stop observing once animated
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        fadeElements.forEach(element => {
            appearanceObserver.observe(element);
        });
    } else {
        // Fallback for older browsers
        fadeElements.forEach(element => {
            element.classList.add('appear');
        });
    }


    // 4. Services Categorization Filters (services.html)
    const filterButtons = document.querySelectorAll('.filter-btn');
    const serviceCards = document.querySelectorAll('.services-grid .service-card');
    
    if (filterButtons.length > 0 && serviceCards.length > 0) {
        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Toggle active button class
                filterButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                
                const filterValue = button.getAttribute('data-filter');
                
                serviceCards.forEach(card => {
                    const cardCategory = card.getAttribute('data-category');
                    
                    if (filterValue === 'all' || cardCategory === filterValue) {
                        // Smoothly show card
                        card.style.display = 'flex';
                        setTimeout(() => {
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        }, 50);
                    } else {
                        // Smoothly hide card
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(15px)';
                        setTimeout(() => {
                            card.style.display = 'none';
                        }, 300);
                    }
                });
            });
        });
    }


    // 5. URL Query Parameter Auto-select for service (contact.html)
    const getQueryParam = (param) => {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    };

    const serviceSelect = document.getElementById('service');
    if (serviceSelect) {
        const serviceParam = getQueryParam('service');
        if (serviceParam) {
            // Find option that matches param or includes it
            for (let option of serviceSelect.options) {
                if (option.value.toLowerCase() === serviceParam.toLowerCase() || 
                    option.text.toLowerCase().includes(serviceParam.toLowerCase())) {
                    option.selected = true;
                    break;
                }
            }
        }
    }


    // 6. Contact & Lead Form Validation with Success Modal
    const leadForm = document.getElementById('leadForm');
    const successModal = document.getElementById('successModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    
    if (leadForm && successModal) {
        leadForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            let isValid = true;
            
            // Collect fields for verification
            const inputs = leadForm.querySelectorAll('.form-control[required]');
            const consentCheckbox = document.getElementById('consent');
            
            // Remove previous error states
            leadForm.querySelectorAll('.validation-error').forEach(el => el.remove());
            inputs.forEach(input => {
                input.style.borderColor = '#e2e8f0';
            });
            if (consentCheckbox) {
                consentCheckbox.style.outline = 'none';
            }
            
            // Verify empty or invalid fields
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    markInvalid(input, 'This field is required');
                } else if (input.type === 'email' && !validateEmail(input.value)) {
                    isValid = false;
                    markInvalid(input, 'Please enter a valid email address');
                }
            });
            
            // Consent checkbox validation on contact page
            if (consentCheckbox && !consentCheckbox.checked) {
                isValid = false;
                const label = consentCheckbox.nextElementSibling;
                label.style.color = '#ef4444'; // Red color
                consentCheckbox.style.outline = '2px solid #ef4444';
            }
            
            if (isValid) {
                // Simulate form submission
                const submitBtn = leadForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting Quote...';
                
                setTimeout(() => {
                    // Show custom success modal
                    successModal.classList.add('active');
                    
                    // Reset form fields
                    leadForm.reset();
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }, 1000);
            }
        });

        // Helper to mark field as invalid
        const markInvalid = (element, message) => {
            element.style.borderColor = '#ef4444'; // border red
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'validation-error';
            errorDiv.style.color = '#ef4444';
            errorDiv.style.fontSize = '12px';
            errorDiv.style.marginTop = '4px';
            errorDiv.style.fontFamily = 'Inter, sans-serif';
            errorDiv.textContent = message;
            
            element.parentNode.appendChild(errorDiv);
        };

        // Helper to validate email format
        const validateEmail = (email) => {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(String(email).toLowerCase());
        };
        
        // Reset custom styles if user types or checks
        leadForm.addEventListener('input', (e) => {
            if (e.target.classList.contains('form-control')) {
                e.target.style.borderColor = '';
                const err = e.target.parentNode.querySelector('.validation-error');
                if (err) err.remove();
            }
        });
        
        if (consentCheckbox) {
            consentCheckbox.addEventListener('change', () => {
                if (consentCheckbox.checked) {
                    consentCheckbox.style.outline = 'none';
                    consentCheckbox.nextElementSibling.style.color = '';
                }
            });
        }
    }
    
    // Close success modal actions
    if (closeModalBtn && successModal) {
        closeModalBtn.addEventListener('click', () => {
            successModal.classList.remove('active');
        });
        
        // Close modal when clicking on the overlay backdrop
        successModal.addEventListener('click', (e) => {
            if (e.target === successModal) {
                successModal.classList.remove('active');
            }
        });
    }
});
