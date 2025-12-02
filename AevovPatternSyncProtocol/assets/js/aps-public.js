// assets/js/aps-public.js

class APSPublic {
    constructor() {
        this.init();
        this.bindEvents();
        this.initComparisons();
    }

    init() {
        this.comparisonForm = document.querySelector('.aps-comparison-form');
        this.itemsContainer = document.querySelector('.aps-items-container');
        this.resultContainer = document.querySelector('.aps-comparison-results');
        this.loading = false;
    }

    bindEvents() {
        // Comparison form handling
        if (this.comparisonForm) {
            this.comparisonForm.addEventListener('submit', (e) => this.handleComparisonSubmit(e));
        }

        // Dynamic item addition/removal
        document.querySelectorAll('.aps-add-item').forEach(button => {
            button.addEventListener('click', () => this.addItemField());
        });

        document.addEventListener('click', (e) => {
            if (e.target.matches('.aps-remove-item')) {
                this.removeItemField(e.target);
            }
        });

        // Real-time threshold display
        const thresholdInput = document.getElementById('aps_threshold');
        if (thresholdInput) {
            thresholdInput.addEventListener('input', (e) => {
                document.querySelector('.aps-threshold-value').textContent = `${e.target.value}%`;
            });
        }
    }

    async handleComparisonSubmit(e) {
        e.preventDefault();
        if (this.loading) return;

        this.loading = true;
        this.showLoading();

        const formData = new FormData(e.target);
        
        try {
            const response = await fetch(apsPublic.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': apsPublic.nonce
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.displayResults(data.data);
            } else {
                throw new Error(data.data.message || 'Comparison failed');
            }
        } catch (error) {
            this.showError(error.message);
        } finally {
            this.loading = false;
            this.hideLoading();
        }
    }

    addItemField() {
        const container = document.querySelector('.aps-item-fields');
        const maxItems = parseInt(this.itemsContainer.dataset.maxItems);
        
        if (container.children.length >= maxItems) {
            alert(apsPublic.i18n.maxItemsReached);
            return;
        }

        const template = `
            <div class="aps-item-field">
                <input type="text" 
                       name="aps_items[]" 
                       class="aps-item-input" 
                       placeholder="${apsPublic.i18n.enterItem}"
                       required>
                <button type="button" class="aps-remove-item" title="${apsPublic.i18n.removeItem}">×</button>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', template);
    }

    removeItemField(button) {
        const field = button.closest('.aps-item-field');
        const container = field.parentElement;
        
        if (container.children.length > 1) {
            field.remove();
        }
    }

    initComparisons() {
        document.querySelectorAll('[data-comparison-id]').forEach(comparison => {
            this.loadComparisonData(comparison.dataset.comparisonId);
        });
    }

    async loadComparisonData(comparisonId) {
        try {
            const response = await fetch(`${apsPublic.restUrl}/comparison/${comparisonId}`);
            const data = await response.json();
            this.updateComparisonDisplay(comparisonId, data);
        } catch (error) {
            console.error('Error loading comparison:', error);
        }
    }

    updateComparisonDisplay(comparisonId, data) {
        const container = document.querySelector(`[data-comparison-id="${comparisonId}"]`);
        if (!container) return;

        // Update comparison visualization
        this.renderComparisonVisualization(container, data);
        
        // Update metrics
        this.updateComparisonMetrics(container, data);
    }

    renderComparisonVisualization(container, data) {
        const canvas = container.querySelector('.aps-visualization');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        
        // Clear existing visualization
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Draw new visualization based on comparison data
        this.drawComparison(ctx, data);
    }

    drawComparison(ctx, data) {
        // Implementation of comparison visualization drawing
    }

    showLoading() {
        const loader = document.createElement('div');
        loader.className = 'aps-loader';
        loader.innerHTML = `<div class="aps-spinner"></div><p>${apsPublic.i18n.loading}</p>`;
        this.comparisonForm.appendChild(loader);
    }

    hideLoading() {
        const loader = document.querySelector('.aps-loader');
        if (loader) {
            loader.remove();
        }
    }

    showError(message) {
        const error = document.createElement('div');
        error.className = 'aps-error-message';
        error.textContent = message;
        this.resultContainer.appendChild(error);

        setTimeout(() => error.remove(), 5000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.apsPublic = new APSPublic();
});