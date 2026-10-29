// BarberBook — Main JS

// Auto-dismiss flash messages
document.addEventListener('DOMContentLoaded', function() {
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(function(alert) {
    setTimeout(function() {
      alert.style.transition = 'opacity 0.5s ease';
      alert.style.opacity = '0';
      setTimeout(function() { alert.remove(); }, 500);
    }, 5000);
  });
});

// Mark appointment as complete (admin action)
function completeAppointment(id) {
  if (confirm('Mark this appointment as completed?')) {
    window.location.href = '?action=complete&id=' + id;
  }
}
