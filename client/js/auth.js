// Check if user is authenticated
(function() {
    const userData = localStorage.getItem('userData');
    const userToken = localStorage.getItem('userToken');
    
    // If no user data, redirect to auth page
    if (!userData) {
        window.location.href = '/auth.html';
        return;
    }
    
    // Parse user data
    try {
        const user = JSON.parse(userData);
        
        // Update UI with user info
        const userProfilePic = document.getElementById('userProfilePic');
        const currentUsername = document.getElementById('currentUsername');
        
        if (userProfilePic && user.avatarUrl) {
            userProfilePic.src = user.avatarUrl;
        }
        
        if (currentUsername && user.username) {
            currentUsername.textContent = user.username;
        }
        
        // Store user data globally for other scripts
        window.currentUser = user;
        window.userToken = userToken;
        
        // Add logout functionality
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => {
                if (confirm('Are you sure you want to logout?')) {
                    localStorage.removeItem('userData');
                    localStorage.removeItem('userToken');
                    window.location.href = '/auth.html';
                }
            });
        }
        
    } catch (error) {
        console.error('Error parsing user data:', error);
        // Clear invalid data and redirect to auth
        localStorage.removeItem('userData');
        localStorage.removeItem('userToken');
        window.location.href = '/auth.html';
    }
})();