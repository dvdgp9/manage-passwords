document.addEventListener('DOMContentLoaded', function() {
    // Handle delete buttons
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const passwordId = this.getAttribute('data-id');

            // Confirm deletion
            if (confirm('¿Estás seguro de que deseas eliminar esta contraseña?')) {
                // Send a request to delete the password
                fetch('delete-password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${passwordId}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data === 'success') {
                        // Reload the page to reflect the changes
                        location.reload();
                    } else {
                        alert('Error al eliminar la contraseña.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        });
    });

    // Handle clear search button
    const clearSearchButton = document.getElementById('clear-search');
    if (clearSearchButton) {
        clearSearchButton.addEventListener('click', function() {
            window.location.href = 'ver-passwords.php';
        });
    }
});
