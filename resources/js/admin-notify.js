// Admin notification handler
document.addEventListener('DOMContentLoaded', function() {
    if (window.Echo) {
        console.log('Setting up admin notifications...');
        
        window.Echo.private('role.1.notifications')
            .listen('.UserCreatedRecently', (e) => {
                console.log('User created event received:', e);
                // e contains: id, name, email, created_at, message
                // Show however you want; example:
                alert(e.message + " (created at: " + e.created_at + ")");
            })
            .error((error) => {
                console.error('WebSocket connection error:', error);
            });
    } else {
        console.error('Echo is not available. Make sure WebSocket connection is properly configured.');
    }
});