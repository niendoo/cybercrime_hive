// Fix layout issues with a more direct approach
document.addEventListener('DOMContentLoaded', function() {
    // PRIORITY FIX: Force display the Announcements section
    setTimeout(function() {
        console.log('Fixing announcements visibility...');
        // Target every possible selector that might be causing the announcements to be hidden
        
        // Find announcements by icon
        const allIcons = document.querySelectorAll('i.fa-bullhorn, i.fas.fa-bullhorn');
        allIcons.forEach(function(icon) {
            let parent = icon.parentNode;
            while (parent && !parent.classList.contains('col-md-6')) {
                parent = parent.parentNode;
            }
            if (parent) {
                console.log('Found announcements container via icon, displaying it');
                parent.style.display = 'block !important';
                Object.assign(parent.style, {
                    display: 'block',
                    visibility: 'visible',
                    opacity: '1'
                });
            }
        });
        
        // Find by content keyword 'Announcements'
        document.querySelectorAll('header, h2, h3, h4, div.card-header').forEach(function(element) {
            if (element.textContent.includes('Announcements')) {
                let parent = element.parentNode;
                while (parent && !parent.classList.contains('col-md-6')) {
                    parent = parent.parentNode;
                }
                if (parent) {
                    console.log('Found announcements container via text, displaying it');
                    Object.assign(parent.style, {
                        display: 'block',
                        visibility: 'visible',
                        opacity: '1'
                    });
                }
            }
        });
        
        // Force display of all alert items which are likely in the announcements
        document.querySelectorAll('.alert').forEach(function(alert) {
            Object.assign(alert.style, {
                display: 'block',
                visibility: 'visible',
                opacity: '1'
            });
        });
    }, 100); // Short delay to ensure DOM is fully loaded

    // 1. Fix footer whitespace with !important styles
    const footer = document.querySelector('footer');
    if (footer) {
        // Apply inline styles with !important to override any other styles
        footer.setAttribute('style', 'margin: 0 !important; padding: 2rem 0 0 0 !important; display: block !important; width: 100% !important;');
        // Remove any extra elements or whitespace nodes that might be causing issues
        if (footer.nextSibling) {
            if (footer.nextSibling.nodeType === 3) { // Text node (whitespace)
                footer.parentNode.removeChild(footer.nextSibling);
            }
        }
    }

    // 2. Fix announcements section with stronger approach
    const cards = document.querySelectorAll('.card');
    cards.forEach(function(card) {
        card.setAttribute('style', 'display: block !important; visibility: visible !important; opacity: 1 !important;');
    });

    // Target announcements specifically with a more compatible approach
    const cardHeaders = document.querySelectorAll('.card-header');
    cardHeaders.forEach(function(header) {
        if (header.innerHTML.includes('fa-bullhorn')) {
            // Found the announcements header
            let parent = header;
            // Navigate up to find the col-md-6 container
            while (parent && !parent.classList.contains('col-md-6')) {
                parent = parent.parentNode;
            }
            if (parent) {
                parent.setAttribute('style', 'display: block !important; visibility: visible !important; opacity: 1 !important;');
                // Make sure the card inside is visible too
                const cards = parent.querySelectorAll('.card');
                cards.forEach(function(card) {
                    card.setAttribute('style', 'display: block !important; visibility: visible !important; opacity: 1 !important;');
                });
            }
        }
    });

    // 3. Fix overall layout structure
    document.documentElement.style.margin = '0';
    document.documentElement.style.padding = '0';
    document.documentElement.style.height = '100%';
    
    document.body.setAttribute('style', 'margin: 0 !important; padding: 0 !important; min-height: 100vh !important; display: flex !important; flex-direction: column !important;');

    // 4. Make content section grow to fill available space
    const contentSections = document.querySelectorAll('.content-section, section');
    contentSections.forEach(function(section) {
        section.setAttribute('style', 'flex: 1 0 auto !important; display: block !important;');
    });

    // 5. Alternative approach for fixing content containers
    const containers = document.querySelectorAll('.container');
    containers.forEach(function(container) {
        container.style.overflowX = 'hidden';
    });
});
