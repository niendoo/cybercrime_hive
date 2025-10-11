/**
 * CyberCrime Hive - Custom JavaScript
 * Author: CyberCrime Hive Team
 * Version: 1.0
 */

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl)
    })
    
    // Forcefully initialize all dropdowns with direct access to Bootstrap's objects
    try {
        // Make sure Bootstrap is loaded
        if (typeof bootstrap !== 'undefined') {
            console.log('Bootstrap loaded: ', bootstrap.version);
            var dropdownElementList = document.querySelectorAll('.dropdown-toggle');
            console.log('Found ' + dropdownElementList.length + ' dropdown toggles');
            
            // Initialize each dropdown individually
            dropdownElementList.forEach(function(dropdownToggleEl) {
                try {
                    var dropdown = new bootstrap.Dropdown(dropdownToggleEl);
                    console.log('Initialized dropdown: ', dropdownToggleEl.id || 'unnamed');
                    
                    // Add manual click handler as backup
                    dropdownToggleEl.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        dropdown.toggle();
                    });
                } catch(err) {
                    console.error('Error initializing dropdown:', err);
                }
            });
        } else {
            console.error('Bootstrap not loaded! Cannot initialize dropdowns.');
        }
    } catch(err) {
        console.error('Error in dropdown initialization:', err);
    };
    
    // Auto-dismiss alerts (only for temporary messages)
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert.alert-dismissible:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000); // Alerts will auto-dismiss after 5 seconds
    
    // Password strength meter
    const passwordField = document.getElementById('password');
    const passwordStrength = document.getElementById('password-strength');
    
    if (passwordField && passwordStrength) {
        passwordField.addEventListener('input', function() {
            const password = passwordField.value;
            let strength = 0;
            
            // Check password length
            if (password.length >= 8) strength += 1;
            if (password.length >= 12) strength += 1;
            
            // Check for mixed case
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
            
            // Check for numbers
            if (password.match(/\d/)) strength += 1;
            
            // Check for special characters
            if (password.match(/[^a-zA-Z\d]/)) strength += 1;
            
            // Update display
            let strengthText = '';
            let strengthClass = '';
            
            switch(strength) {
                case 0:
                case 1:
                    strengthText = 'Weak';
                    strengthClass = 'bg-danger';
                    break;
                case 2:
                case 3:
                    strengthText = 'Moderate';
                    strengthClass = 'bg-warning';
                    break;
                case 4:
                    strengthText = 'Strong';
                    strengthClass = 'bg-info';
                    break;
                case 5:
                    strengthText = 'Very Strong';
                    strengthClass = 'bg-success';
                    break;
            }
            
            passwordStrength.className = 'progress-bar ' + strengthClass;
            passwordStrength.style.width = (strength * 20) + '%';
            passwordStrength.textContent = strengthText;
        });
    }
    
    // Character counter for textarea
    const textareas = document.querySelectorAll('textarea[data-max-length]');
    textareas.forEach(function(textarea) {
        const maxLength = textarea.getAttribute('data-max-length');
        const counterElement = document.createElement('div');
        counterElement.className = 'form-text text-end';
        counterElement.innerHTML = `<span class="current-count">0</span>/${maxLength} characters`;
        textarea.parentNode.appendChild(counterElement);
        
        textarea.addEventListener('input', function() {
            const currentLength = textarea.value.length;
            const countElement = counterElement.querySelector('.current-count');
            countElement.textContent = currentLength;
            
            if (currentLength > maxLength) {
                countElement.classList.add('text-danger');
            } else {
                countElement.classList.remove('text-danger');
            }
        });
    });
    
    // File size validation for uploads
    const fileInputs = document.querySelectorAll('input[type="file"][data-max-size]');
    fileInputs.forEach(function(input) {
        const maxSize = parseInt(input.getAttribute('data-max-size')); // Size in bytes
        
        input.addEventListener('change', function() {
            const files = input.files;
            let totalSize = 0;
            let oversizedFiles = [];
            
            for (let i = 0; i < files.length; i++) {
                totalSize += files[i].size;
                if (files[i].size > maxSize) {
                    oversizedFiles.push(files[i].name);
                }
            }
            
            if (oversizedFiles.length > 0) {
                alert('The following files exceed the maximum size limit: ' + oversizedFiles.join(', '));
                input.value = ''; // Clear the input
            }
        });
    });
    
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
    
    // Toggle password visibility
    const togglePasswordButtons = document.querySelectorAll('.toggle-password');
    togglePasswordButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const passwordField = document.querySelector(button.getAttribute('data-target'));
            const icon = button.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // Autosave form drafts
    const autosaveForms = document.querySelectorAll('form[data-autosave="true"]');
    autosaveForms.forEach(function(form) {
        const formId = form.id;
        const formFields = form.querySelectorAll('input, textarea, select');
        const saveKey = 'formDraft_' + formId;
        
        // Load saved draft
        const savedData = localStorage.getItem(saveKey);
        if (savedData) {
            const data = JSON.parse(savedData);
            formFields.forEach(function(field) {
                if (field.type === 'file') return; // Skip file inputs
                if (data[field.name]) field.value = data[field.name];
            });
            
            // Add restore message if data exists
            const draftMessage = document.createElement('div');
            draftMessage.className = 'alert alert-info mt-2';
            draftMessage.innerHTML = 'A draft has been restored. <button type="button" class="btn btn-sm btn-link p-0 ms-2 clear-draft">Clear draft</button>';
            form.prepend(draftMessage);
            
            // Add clear draft button functionality
            const clearButton = draftMessage.querySelector('.clear-draft');
            clearButton.addEventListener('click', function() {
                localStorage.removeItem(saveKey);
                draftMessage.remove();
            });
        }
        
        // Save form data periodically
        let saveTimer;
        formFields.forEach(function(field) {
            field.addEventListener('input', function() {
                clearTimeout(saveTimer);
                saveTimer = setTimeout(function() {
                    const data = {};
                    formFields.forEach(function(f) {
                        if (f.type !== 'file' && f.type !== 'password') data[f.name] = f.value;
                    });
                    localStorage.setItem(saveKey, JSON.stringify(data));
                }, 1000); // Save after 1 second of inactivity
            });
        });
        
        // Clear draft on successful submission
        form.addEventListener('submit', function() {
            localStorage.removeItem(saveKey);
        });
    });
});

// Print report function
function printReport() {
    window.print();
    return false;
}

// Copy to clipboard function
function copyToClipboard(text) {
    const tempInput = document.createElement('input');
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    
    // Show tooltip
    const tooltip = document.createElement('div');
    tooltip.className = 'copied-tooltip';
    tooltip.textContent = 'Copied!';
    document.body.appendChild(tooltip);
    
    setTimeout(function() {
        tooltip.remove();
    }, 2000);
}

// Function to handle AJAX form submissions
function submitFormAjax(formId, successCallback, errorCallback) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const xhr = new XMLHttpRequest();
        
        xhr.open('POST', form.action, true);
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 400) {
                const response = JSON.parse(xhr.responseText);
                if (successCallback) successCallback(response);
            } else {
                if (errorCallback) errorCallback(xhr.responseText);
                else console.error('Server error:', xhr.responseText);
            }
        };
        
        xhr.onerror = function() {
            if (errorCallback) errorCallback('Connection error');
            else console.error('Connection error');
        };
        
        xhr.send(formData);
    });
    
    return true;
}
