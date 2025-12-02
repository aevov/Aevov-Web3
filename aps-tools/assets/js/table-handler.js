// Initialize table handler
const ApsTableHandler = {
    tables: {},
    
    init: function(elementId, config = {}) {
        const container = document.getElementById(elementId);
        if (!container) return;

        const defaultConfig = {
            data: [],
            rowHeaders: true,
            colHeaders: true,
            contextMenu: true,
            minSpareRows: 1,
            width: '100%',
            height: 400,
            licenseKey: 'non-commercial-and-evaluation',
            columns: [
                { data: 'id', type: 'numeric', readOnly: true },
                { data: 'sku', type: 'text' },
                { data: 'type', type: 'dropdown', 
                  source: ['tensor', 'pattern', 'hybrid'] },
                { data: 'status', type: 'text', readOnly: true },
                { data: 'progress', type: 'numeric', readOnly: true }
            ],
            colHeaders: ['ID', 'SKU', 'Type', 'Status', 'Progress'],
            checkboxColumn: true,
            afterChange: (changes, source) => {
                if (source === 'loadData') return;
                this.saveTableData(elementId);
            }
        };

        const mergedConfig = { ...defaultConfig, ...config };
        this.tables[elementId] = new Handsontable(container, mergedConfig);

        // Load initial data
        this.loadTableData(elementId);
        
        // Bind events
        this.bindEvents(elementId);
    },

    loadTableData: function(elementId) {
        jQuery.ajax({
            url: apsTable.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aps_get_table_data',
                nonce: apsTable.nonce,
                table_id: elementId
            },
            success: (response) => {
                if (response.success && this.tables[elementId]) {
                    this.tables[elementId].loadData(response.data);
                }
            }
        });
    },

    saveTableData: function(elementId) {
        if (!this.tables[elementId]) return;

        const data = this.tables[elementId].getData();
        jQuery.ajax({
            url: apsTable.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aps_save_table_data',
                nonce: apsTable.nonce,
                table_id: elementId,
                data: JSON.stringify(data)
            }
        });
    },

    bindEvents: function(elementId) {
        // Save button handler
        jQuery(`.save-table[data-table="${elementId}"]`).on('click', () => {
            this.saveTableData(elementId);
        });

        // Process selected handler
        jQuery(`.process-selected[data-table="${elementId}"]`).on('click', () => {
            const selectedRows = this.getSelectedRows(elementId);
            if (selectedRows.length === 0) {
                alert('Please select rows to process');
                return;
            }
            this.processRows(elementId, selectedRows);
        });
    },

    getSelectedRows: function(elementId) {
        if (!this.tables[elementId]) return [];
        
        const meta = this.tables[elementId].getCellsMeta();
        return meta.filter(cell => cell && cell.checked)
            .map(cell => cell.row);
    },

    processRows: function(elementId, rows) {
        // Show processing dialog
        const dialog = this.createProcessingDialog();
        
        // Process rows sequentially
        let processed = 0;
        const processNext = () => {
            if (processed >= rows.length) {
                this.updateProcessingDialog(dialog, 100, 'Complete');
                setTimeout(() => dialog.remove(), 2000);
                return;
            }

            const rowIdx = rows[processed];
            const rowData = this.tables[elementId].getDataAtRow(rowIdx);
            
            // Update processing status
            this.updateProcessingDialog(
                dialog, 
                (processed / rows.length) * 100,
                `Processing ${rowData[1]}...` // SKU
            );

            // Simulate processing (replace with actual processing)
            setTimeout(() => {
                processed++;
                // Update row status
                this.tables[elementId].setDataAtCell(rowIdx, 3, 'Processed');
                this.tables[elementId].setDataAtCell(rowIdx, 4, 100);
                processNext();
            }, 1000);
        };

        processNext();
    },

    createProcessingDialog: function() {
        const dialog = document.createElement('div');
        dialog.className = 'aps-processing-dialog';
        dialog.innerHTML = `
            <div class="processing-content">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="status-text"></div>
            </div>
        `;
        document.body.appendChild(dialog);
        return dialog;
    },

    updateProcessingDialog: function(dialog, progress, status) {
        const fill = dialog.querySelector('.progress-fill');
        const text = dialog.querySelector('.status-text');
        
        fill.style.width = `${progress}%`;
        text.textContent = status;
    }
};

// Initialize when document is ready
jQuery(document).ready(function() {
    // Initialize tables
    const tables = document.querySelectorAll('.hot-table');
    tables.forEach(table => {
        ApsTableHandler.init(table.id);
    });
});