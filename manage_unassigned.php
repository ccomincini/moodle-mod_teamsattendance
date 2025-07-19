                    }, 2000);
                    
                    showSuccess(`Bulk assignment completed: ${result.successful} successful, ${result.failed} failed`);
                } else {
                    showError(response.error);
                }
            },
            error: function() {
                showError('Connection error during bulk assignment');
            },
            complete: function() {
                $('#bulk-assign-btn').prop('disabled', false);
            }
        });
    }

    // Utility functions
    function formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return hours + 'h ' + minutes + 'm';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showSuccess(message) {
        // Simple toast notification
        const toast = $('<div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">' +
            '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
            message + '</div>');
        $('body').append(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    function showError(message) {
        const toast = $('<div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">' +
            '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
            message + '</div>');
        $('body').append(toast);
        setTimeout(() => toast.remove(), 8000);
    }
});
</script>

<?php
echo $OUTPUT->footer();
?>
