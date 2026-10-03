// Google Apps Script Web App URL to save data to Google Sheets & send email notification.
// Deploy your Apps Script, get the Web App URL, and paste it here.
const GOOGLE_SCRIPT_URL = 'https://script.google.com/macros/s/AKfycbyqOfAk9exKThIZ3Ffo-kMhNdlagfgn00MvBDd6bAMx9BlKqdfHuwvpi2WH-Kjyf2zlpg/exec';

document.addEventListener('DOMContentLoaded', () => {

    // 1. Sticky Header Scroll Effect (only for pages where header is transparent by default)
    const header = document.getElementById('header');

    const handleScroll = () => {
        if (!header) return;

        // If it is the home page, the header doesn't have Scrolled class by default. We toggle it.
        const isHomePage = window.location.pathname.endsWith('index.html') ||
            window.location.pathname.endsWith('index') ||
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
    const successModal = document.getElementById('successModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const leadForm = document.getElementById('leadForm');
    const popupQuoteForm = document.getElementById('popupQuoteForm');
    const quoteModal = document.getElementById('quoteModal');

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

    const setupFormValidation = (form, onSuccessCallback) => {
        if (!form) return;

        const consentCheckbox = form.querySelector('input[type="checkbox"]');

        form.addEventListener('submit', (e) => {
            e.preventDefault();

            let isValid = true;

            // Collect fields for verification
            const inputs = form.querySelectorAll('.form-control[required]');

            // Remove previous error states
            form.querySelectorAll('.validation-error').forEach(el => el.remove());
            inputs.forEach(input => {
                input.style.borderColor = '';
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

            // Consent checkbox validation (if present)
            if (consentCheckbox && !consentCheckbox.checked) {
                isValid = false;
                const label = consentCheckbox.nextElementSibling;
                if (label) {
                    label.style.color = '#ef4444'; // Red color
                }
                consentCheckbox.style.outline = '2px solid #ef4444';
            }

            if (isValid) {
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting...';

                // Collect form values
                const formData = new FormData(form);
                const data = {};
                formData.forEach((value, key) => {
                    data[key] = value;
                });

                // Prevent Google Sheets formula parse error if phone starts with '+'
                if (data['phone']) {
                    const cleanPhone = data['phone'].trim();
                    if (cleanPhone.startsWith('+')) {
                        data['phone'] = "'" + cleanPhone;
                    } else {
                        data['phone'] = cleanPhone;
                    }
                }

                // Add company metadata
                data['companyName'] = 'Tabeeb Contractor Pte Ltd.';
                data['submittedAt'] = new Date().toLocaleString();

                const showSuccess = () => {
                    if (successModal) {
                        successModal.classList.add('active');
                    }
                    form.reset();
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    if (onSuccessCallback) {
                        onSuccessCallback();
                    }
                };

                if (GOOGLE_SCRIPT_URL && GOOGLE_SCRIPT_URL !== 'YOUR_GOOGLE_APPS_SCRIPT_URL_HERE') {
                    // Post data to Google Apps Script Web App
                    fetch(GOOGLE_SCRIPT_URL, {
                        method: 'POST',
                        mode: 'no-cors',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    })
                        .then(() => {
                            showSuccess();
                        })
                        .catch((err) => {
                            console.error('Submission error:', err);
                            // Fallback to show success modal so user experience doesn't break
                            showSuccess();
                        });
                } else {
                    // Fallback to simulated submission if URL is not configured yet
                    setTimeout(() => {
                        showSuccess();
                    }, 1000);
                }
            }
        });

        // Reset custom styles if user types or checks
        form.addEventListener('input', (e) => {
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
                    const label = consentCheckbox.nextElementSibling;
                    if (label) {
                        label.style.color = '';
                    }
                }
            });
        }
    };

    // Initialize form validations
    setupFormValidation(leadForm);
    setupFormValidation(popupQuoteForm, () => {
        if (quoteModal) {
            quoteModal.classList.remove('active');
        }
    });

    // 7. Quote Popup Modal Opening/Closing
    const openModalBtns = document.querySelectorAll('.open-quote-modal-btn');
    const closeQuoteModalBtn = document.getElementById('closeQuoteModalBtn');

    if (openModalBtns.length > 0 && quoteModal) {
        openModalBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                quoteModal.classList.add('active');
            });
        });
    }

    if (closeQuoteModalBtn && quoteModal) {
        closeQuoteModalBtn.addEventListener('click', () => {
            quoteModal.classList.remove('active');
        });

        quoteModal.addEventListener('click', (e) => {
            if (e.target === quoteModal) {
                quoteModal.classList.remove('active');
            }
        });
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

    // 8. FAQ Accordion Toggle (For SEO Rich FAQ Sections)
    const faqButtons = document.querySelectorAll('.faq-question');
    if (faqButtons.length > 0) {
        faqButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const currentItem = btn.closest('.faq-item');
                const wasActive = currentItem.classList.contains('active');
                
                // Close other items in the same container
                const parentAccordion = currentItem.closest('.faq-accordion');
                if (parentAccordion) {
                    parentAccordion.querySelectorAll('.faq-item.active').forEach(item => {
                        if (item !== currentItem) item.classList.remove('active');
                    });
                }
                
                currentItem.classList.toggle('active', !wasActive);
            });
        });
    }

    // 9. Global Social Share Trigger Handler (For Services & Articles)
    const setupGlobalShare = () => {
        let modal = document.getElementById('globalShareModal');
        let toast = document.getElementById('globalCopyToast');

        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'globalCopyToast';
            toast.className = 'global-copy-toast';
            toast.innerHTML = `<svg width="20" height="20" fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg><span>Link copied to clipboard! Ready to share on WhatsApp or Facebook.</span>`;
            document.body.appendChild(toast);
        }

        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'globalShareModal';
            modal.className = 'global-share-modal';
            modal.innerHTML = `
                <div class="global-share-modal-box">
                    <button type="button" class="share-modal-close-btn" id="closeShareModalBtn">&times;</button>
                    <div class="share-modal-title" id="shareModalTitle">Share Service</div>
                    <div class="share-modal-subtitle">Send this dedicated link to WhatsApp or social media:</div>
                    <div class="share-modal-links-grid">
                        <a href="#" id="shareWaLink" target="_blank" rel="noopener noreferrer" class="share-channel-btn share-channel-wa">
                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                            WhatsApp
                        </a>
                        <a href="#" id="shareFbLink" target="_blank" rel="noopener noreferrer" class="share-channel-btn share-channel-fb">
                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            Facebook
                        </a>
                        <a href="#" id="shareLiLink" target="_blank" rel="noopener noreferrer" class="share-channel-btn share-channel-li">
                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                            LinkedIn
                        </a>
                        <button type="button" id="shareCopyChanBtn" class="share-channel-btn share-channel-copy">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            Copy Link
                        </button>
                    </div>
                    <div class="share-url-copy-box">
                        <input type="text" id="shareModalUrlInput" readonly>
                        <button type="button" id="shareModalCopyBtn">Copy</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            const closeBtn = document.getElementById('closeShareModalBtn');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => modal.classList.remove('active'));
            }
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.classList.remove('active');
            });
        }

        const showCopyToast = () => {
            if (toast) {
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 3500);
            }
        };

        const copyUrl = async (url) => {
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(url);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = url;
                    ta.style.position = 'fixed';
                    ta.style.left = '-999999px';
                    document.body.appendChild(ta);
                    ta.focus();
                    ta.select();
                    document.execCommand('copy');
                    ta.remove();
                }
                showCopyToast();
            } catch (e) {
                prompt('Copy link:', url);
            }
        };

        const copyInputBtn = document.getElementById('shareModalCopyBtn');
        const copyChanBtn = document.getElementById('shareCopyChanBtn');
        const urlInput = document.getElementById('shareModalUrlInput');

        if (copyInputBtn && urlInput) {
            copyInputBtn.addEventListener('click', () => copyUrl(urlInput.value));
        }
        if (copyChanBtn && urlInput) {
            copyChanBtn.addEventListener('click', () => copyUrl(urlInput.value));
        }

        // Attach click listener for all share buttons on page
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.service-card-share-btn, .share-trigger-btn');
            if (!btn) return;
            e.preventDefault();

            const title = btn.getAttribute('data-title') || document.title;
            const url = btn.getAttribute('data-url') || window.location.href;

            // Check if mobile device supports native share
            const isMobile = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            if (isMobile && navigator.share) {
                navigator.share({
                    title: title,
                    text: title,
                    url: url
                }).catch(() => {});
                return;
            }

            // Open custom modal
            const modalTitle = document.getElementById('shareModalTitle');
            const waLink = document.getElementById('shareWaLink');
            const fbLink = document.getElementById('shareFbLink');
            const liLink = document.getElementById('shareLiLink');

            if (modalTitle) modalTitle.textContent = title;
            if (urlInput) urlInput.value = url;

            if (waLink) waLink.href = 'https://wa.me/?text=' + encodeURIComponent(title + '\n' + url);
            if (fbLink) fbLink.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
            if (liLink) liLink.href = 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(url);

            modal.classList.add('active');
        });
    };

    setupGlobalShare();
});


