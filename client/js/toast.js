class ToastManager {
    constructor() {
        this.container = document.getElementById('toastContainer');
        this.toasts = [];
        this.toastId = 0;
    }

    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} type - Type of toast: 'success', 'error', 'info'
     * @param {number} duration - Duration in milliseconds (default: 4000)
     * @param {string} title - Optional title for the toast
     */
    show(message, type = 'info', duration = 4000, title = null) {
        const id = this.toastId++;
        
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.dataset.toastId = id;
        
        // Determine icon based on type
        let icon = '✓';
        if (type === 'error') icon = '✕';
        if (type === 'info') icon = 'i';
        
        // Build toast HTML
        toast.innerHTML = `
            <div class="toast-icon">${icon}</div>
            <div class="toast-content">
                ${title ? `<div class="toast-title">${title}</div>` : ''}
                <div class="toast-message">${message}</div>
            </div>
            <div class="toast-close" onclick="toastManager.dismiss(${id})">×</div>
        `;
        
        // Add to container
        this.container.appendChild(toast);
        this.toasts.push({ id, element: toast });
        
        // Auto dismiss after duration
        if (duration > 0) {
            setTimeout(() => {
                this.dismiss(id);
            }, duration);
        }
        
        return id;
    }

    /**
     * Show success toast
     */
    success(message, title = null, duration = 4000) {
        return this.show(message, 'success', duration, title);
    }

    /**
     * Show error toast
     */
    error(message, title = null, duration = 4000) {
        return this.show(message, 'error', duration, title);
    }

    /**
     * Show info toast
     */
    info(message, title = null, duration = 4000) {
        return this.show(message, 'info', duration, title);
    }

    /**
     * Dismiss a specific toast
     */
    dismiss(id) {
        const toastData = this.toasts.find(t => t.id === id);
        if (!toastData) return;
        
        const { element } = toastData;
        element.classList.add('hiding');
        
        setTimeout(() => {
            element.remove();
            this.toasts = this.toasts.filter(t => t.id !== id);
        }, 300);
    }

    /**
     * Clear all toasts
     */
    clearAll() {
        this.toasts.forEach(({ element }) => {
            element.classList.add('hiding');
            setTimeout(() => element.remove(), 300);
        });
        this.toasts = [];
    }
}
