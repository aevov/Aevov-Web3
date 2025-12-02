// 2. /assets/js/admin/pattern-manager.js
// Pattern management interface functionality

const BloomPatternManager = {
    patternList: null,
    patternFilter: null,
    currentPage: 1,
    itemsPerPage: 20,

    init: function() {
        this.patternList = document.getElementById('pattern-list');
        this.patternFilter = document.getElementById('pattern-filter');
        this.bindEvents();
        this.loadPatterns();
    },

    bindEvents: function() {
        // Pattern filtering
        this.patternFilter.addEventListener('change', () => {
            this.currentPage = 1;
            this.loadPatterns();
        });

        // Pagination
        document.querySelectorAll('.pagination-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.currentPage = parseInt(e.target.dataset.page);
                this.loadPatterns();
            });
        });

        // Pattern actions
        this.patternList.addEventListener('click', (e) => {
            if (e.target.classList.contains('pattern-action')) {
                this.handlePatternAction(e);
            }
        });
    },

    loadPatterns: function() {
        const params = new URLSearchParams({
            action: 'bloom_get_patterns',
            nonce: bloomAdmin.nonce,
            page: this.currentPage,
            per_page: this.itemsPerPage,
            filter: this.patternFilter.value
        });

        fetch(bloomAdmin.ajaxUrl + '?' + params)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.renderPatterns(data.data);
                }
            })
            .catch(error => {
                console.error('Error loading patterns:', error);
            });
    },

    renderPatterns: function(data) {
        this.patternList.innerHTML = data.patterns.map(pattern => `
            <tr>
                <td>${pattern.pattern_hash}</td>
                <td>${pattern.type}</td>
                <td>${pattern.confidence.toFixed(2)}</td>
                <td>${pattern.created_at}</td>
                <td>
                    <button class="pattern-action" data-action="view" data-id="${pattern.id}">View</button>
                    <button class="pattern-action" data-action="analyze" data-id="${pattern.id}">Analyze</button>
                </td>
            </tr>
        `).join('');

        this.updatePagination(data.total_pages);
    },

    handlePatternAction: function(e) {
        const action = e.target.dataset.action;
        const patternId = e.target.dataset.id;

        switch (action) {
            case 'view':
                this.viewPattern(patternId);
                break;
            case 'analyze':
                this.analyzePattern(patternId);
                break;
        }
    },

    viewPattern: function(patternId) {
        fetch(`${bloomAdmin.restUrl}/patterns/${patternId}`, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': bloomAdmin.nonce
            }
        })
        .then(response => response.json())
        .then(pattern => {
            const modalBody = document.getElementById('pattern-details-content');
            modalBody.innerHTML = `
                <p><strong>ID:</strong> ${pattern.id}</p>
                <p><strong>Hash:</strong> ${pattern.pattern_hash}</p>
                <p><strong>Type:</strong> ${pattern.pattern_type}</p>
                <p><strong>Confidence:</strong> ${pattern.confidence.toFixed(4)}</p>
                <p><strong>Created At:</strong> ${pattern.created_at}</p>
                <p><strong>Features:</strong> <pre>${JSON.stringify(pattern.features, null, 2)}</pre></p>
                <p><strong>Metadata:</strong> <pre>${JSON.stringify(pattern.metadata, null, 2)}</pre></p>
            `;

            const clusterDetails = document.getElementById('cluster-details');
            if (pattern.metadata && pattern.metadata.cluster_id) {
                document.getElementById('cluster-centroid').textContent = pattern.metadata.centroid.toFixed(2);
                document.getElementById('cluster-size').textContent = pattern.metadata.cluster_size;
                clusterDetails.style.display = 'block';
            } else {
                clusterDetails.style.display = 'none';
            }

            document.getElementById('pattern-modal').style.display = 'block';
        })
        .catch(error => {
            console.error('Error fetching pattern details:', error);
            alert('Error fetching pattern details.');
        });
    },
 
    analyzePattern: function(patternId) {
        // Placeholder for pattern analysis
        alert(`Analyzing pattern ${patternId}`);
    },
 
    updatePagination: function(totalPages) {
        // Update pagination UI
        // This part needs to be implemented based on your pagination structure
    }
};

// Initialize pattern manager when document is ready
document.addEventListener('DOMContentLoaded', () => {
    BloomPatternManager.init();

    // Close modal event
    document.querySelector('#pattern-modal .modal-close').addEventListener('click', () => {
        document.getElementById('pattern-modal').style.display = 'none';
    });

    // Close modal when clicking outside
    window.addEventListener('click', (event) => {
        const modal = document.getElementById('pattern-modal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
