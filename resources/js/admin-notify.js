import Echo from "laravel-echo";
window.Echo.private('role.1.notifications')
  .listen('.UserCreatedRecently', (e) => {
    // e contains: id, name, email, created_at, message
    // Show however you want; example:
    alert(e.message + " (created at: " + e.created_at + ")");
  });