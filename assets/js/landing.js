// CyberCrime Hive Landing Page Animations and Interactions

// Wait for the document to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize counter animations when they come into view
    const statNumbers = document.querySelectorAll('.stat-number');
    const animateCounters = () => {
        statNumbers.forEach(counter => {
            // Only animate if not already animated
            if (counter.getAttribute('data-animated') !== 'true') {
                const target = parseInt(counter.getAttribute('data-target'));
                const duration = 2000; // animation duration in milliseconds
                const startTime = performance.now();
                const startValue = 0;

                const updateCounter = (currentTime) => {
                    const elapsedTime = currentTime - startTime;
                    const progress = Math.min(elapsedTime / duration, 1);
                    
                    // Easing function for smoother animation
                    const easedProgress = 1 - Math.pow(1 - progress, 3);
                    
                    const currentValue = Math.floor(startValue + (target - startValue) * easedProgress);
                    counter.innerText = currentValue;
                    
                    if (progress < 1) {
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.setAttribute('data-animated', 'true');
                    }
                };
                
                requestAnimationFrame(updateCounter);
            }
        });
    };

    // Use Intersection Observer to trigger counter animations when visible
    const observeCounters = () => {
        const options = {
            threshold: 0.1 // Trigger when 10% of the element is visible
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    observer.unobserve(entry.target); // Stop observing once triggered
                }
            });
        }, options);

        // Observe the statistics section
        const statsSection = document.querySelector('.statistics-section');
        if (statsSection) {
            observer.observe(statsSection);
        }
    };

    // Initialize the observer
    observeCounters();

    // Add hover effect for feature cards
    const featureCards = document.querySelectorAll('.feature-card');
    featureCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-10px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
