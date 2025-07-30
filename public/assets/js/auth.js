// Auth JavaScript - public/assets/js/auth.js
// Handles login and register form functionality

document.addEventListener('DOMContentLoaded', function() {
    console.log('Auth page loaded');
    initializeAuthForms();
    addFormAnimations();
});

function initializeAuthForms() {
    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    }

    // Login form validation
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                showAlert('Please fill in all fields', 'warning');
            }
        });
    }

    // Register form validation
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const agreeTerms = document.getElementById('agreeTerms').checked;
            
            if (!username || !email || !password || !confirmPassword) {
                e.preventDefault();
                showAlert('Please fill in all fields', 'warning');
                return;
            }
            
            if (!validatePasswords()) {
                e.preventDefault();
                showAlert('Passwords do not match', 'danger');
                return;
            }
            
            if (!agreeTerms) {
                e.preventDefault();
                showAlert('Please agree to the Terms of Service and Privacy Policy', 'warning');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                showAlert('Password must be at least 6 characters long', 'danger');
                return;
            }
            
            if (username.length < 3) {
                e.preventDefault();
                showAlert('Username must be at least 3 characters long', 'danger');
                return;
            }
        });

        // Password strength indicator
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                updatePasswordStrength(this.value);
            });
        }

        // Password confirmation validation
        const confirmPasswordInput = document.getElementById('confirmPassword');
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', validatePasswords);
            if (passwordInput) {
                passwordInput.addEventListener('input', validatePasswords);
            }
        }

        // Username validation
        const usernameInput = document.getElementById('username');
        if (usernameInput) {
            usernameInput.addEventListener('input', function() {
                validateUsername(this.value);
            });
        }
    }
}

function updatePasswordStrength(password) {
    const strengthBar = document.getElementById('passwordStrength');
    if (!strengthBar) return;
    
    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.match(/[a-z]/)) strength++;
    if (password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    
    strengthBar.className = 'password-strength';
    
    if (strength === 0) {
        strengthBar.classList.add('strength-weak');
    } else if (strength <= 2) {
        strengthBar.classList.add('strength-weak');
    } else if (strength === 3) {
        strengthBar.classList.add('strength-fair');
    } else if (strength === 4) {
        strengthBar.classList.add('strength-good');
    } else {
        strengthBar.classList.add('strength-strong');
    }
}

function validatePasswords() {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirmPassword');
    const mismatchMsg = document.getElementById('passwordMismatch');
    
    if (!password || !confirmPassword || !mismatchMsg) return true;
    
    if (confirmPassword.value && password.value !== confirmPassword.value) {
        confirmPassword.classList.add('is-invalid');
        mismatchMsg.style.display = 'block';
        return false;
    } else {
        confirmPassword.classList.remove('is-invalid');
        mismatchMsg.style.display = 'none';
        return true;
    }
}

function validateUsername(username) {
    const usernameInput = document.getElementById('username');
    if (!usernameInput) return;
    
    const validPattern = /^[a-zA-Z0-9_]+$/;
    
    if (username && !validPattern.test(username)) {
        usernameInput.classList.add('is-invalid');
    } else {
        usernameInput.classList.remove('is-invalid');
    }
}

function showAlert(message, type = 'info') {
    // Create and show Bootstrap alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 1050; max-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Smooth form animations
function addFormAnimations() {
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            if (!this.value) {
                this.parentElement.classList.remove('focused');
            }
        });
    });
}

// Email validation helper
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Password strength checker
function getPasswordStrength(password) {
    let score = 0;
    const checks = {
        length: password.length >= 8,
        lowercase: /[a-z]/.test(password),
        uppercase: /[A-Z]/.test(password),
        numbers: /\d/.test(password),
        special: /[^a-zA-Z0-9]/.test(password)
    };
    
    Object.values(checks).forEach(check => {
        if (check) score++;
    });
    
    return {
        score: score,
        checks: checks,
        strength: score < 2 ? 'weak' : score < 4 ? 'fair' : score < 5 ? 'good' : 'strong'
    };
}

// Form field validation with visual feedback
function validateField(field, validationFn, errorMessage) {
    const isValid = validationFn(field.value);
    
    if (isValid) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
    } else {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
        
        // Show error message
        let feedback = field.parentNode.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            field.parentNode.appendChild(feedback);
        }
        feedback.textContent = errorMessage;
    }
    
    return isValid;
}

// Auto-save form data to prevent data loss
function enableAutoSave() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input[type="text"], input[type="email"], textarea');
        inputs.forEach(input => {
            // Load saved data
            const savedValue = localStorage.getItem(`form_${form.id}_${input.name}`);
            if (savedValue && !input.value) {
                input.value = savedValue;
            }
            
            // Save data on input
            input.addEventListener('input', function() {
                localStorage.setItem(`form_${form.id}_${input.name}`, this.value);
            });
        });
        
        // Clear saved data on successful submit
        form.addEventListener('submit', function() {
            inputs.forEach(input => {
                localStorage.removeItem(`form_${form.id}_${input.name}`);
            });
        });
    });
}

// Initialize auto-save if needed
if (localStorage) {
    enableAutoSave();
}