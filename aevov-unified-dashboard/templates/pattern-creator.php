<?php
/**
 * Pattern Creator Template
 *
 * @package AevovUnifiedDashboard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap aud-dashboard-wrap">
    <div class="aud-header">
        <div class="aud-header-content">
            <div>
                <h1 class="aud-header-title">
                    <span class="aud-header-title-icon">✨</span>
                    Pattern Creator
                </h1>
                <p class="aud-header-subtitle">
                    Create and synchronize patterns across the Aevov network
                </p>
            </div>
        </div>
    </div>

    <div class="aud-grid aud-grid-2">
        <div class="aud-card">
            <div class="aud-card-header">
                <h3 class="aud-card-title">
                    <span class="aud-card-icon">✨</span>
                    Create New Pattern
                </h3>
            </div>
            <div class="aud-card-body">
                <form id="aud-pattern-form">
                    <div class="aud-form-group">
                        <label class="aud-form-label" for="aud-pattern-name">Pattern Name *</label>
                        <input
                            type="text"
                            id="aud-pattern-name"
                            class="aud-form-input"
                            placeholder="e.g., User Behavior Pattern"
                            required
                        >
                    </div>

                    <div class="aud-form-group">
                        <label class="aud-form-label" for="aud-pattern-description">Description</label>
                        <textarea
                            id="aud-pattern-description"
                            class="aud-form-textarea"
                            placeholder="Describe what this pattern represents..."
                        ></textarea>
                        <p class="aud-form-help">Optional but recommended for better documentation</p>
                    </div>

                    <div class="aud-form-group">
                        <label class="aud-form-label" for="aud-pattern-data">Pattern Data (JSON) *</label>
                        <textarea
                            id="aud-pattern-data"
                            class="aud-form-textarea"
                            style="min-height: 200px; font-family: var(--aud-font-mono);"
                            placeholder='{
  "type": "behavioral",
  "metrics": {
    "engagement": 0.85,
    "retention": 0.92
  },
  "tags": ["user", "analytics"]
}'
                            required
                        ></textarea>
                        <p class="aud-form-help">Enter valid JSON data. This will be validated before creation.</p>
                    </div>

                    <button type="submit" class="aud-button aud-button-primary aud-button-large aud-create-pattern-btn" style="width: 100%;">
                        ✨ Create Pattern
                    </button>
                </form>
            </div>
        </div>

        <div>
            <div class="aud-card" style="margin-bottom: 24px;">
                <div class="aud-card-header">
                    <h3 class="aud-card-title">
                        <span class="aud-card-icon">📚</span>
                        What are Patterns?
                    </h3>
                </div>
                <div class="aud-card-body">
                    <p style="margin-bottom: 16px; line-height: 1.7;">
                        Patterns are the fundamental building blocks of the Aevov ecosystem. They are:
                    </p>
                    <ul style="line-height: 1.8; color: var(--aud-gray-700); margin-bottom: 16px;">
                        <li><strong>Synchronized:</strong> Distributed across the APS network</li>
                        <li><strong>Recognized:</strong> Analyzed by Bloom AI for insights</li>
                        <li><strong>Immutable:</strong> Stored on the distributed ledger</li>
                        <li><strong>Rewarded:</strong> Contributors earn through Proof of Contribution</li>
                        <li><strong>Interoperable:</strong> Used by all 29 ecosystem plugins</li>
                    </ul>
                </div>
            </div>

            <div class="aud-card">
                <div class="aud-card-header">
                    <h3 class="aud-card-title">
                        <span class="aud-card-icon">💡</span>
                        Example Patterns
                    </h3>
                </div>
                <div class="aud-card-body">
                    <h4 style="font-weight: 600; margin-bottom: 8px;">Simple Pattern:</h4>
                    <pre style="background: var(--aud-gray-100); padding: 12px; border-radius: 8px; overflow-x: auto; font-size: 0.875rem;">{
  "type": "greeting",
  "message": "Hello, Aevov!"
}</pre>

                    <h4 style="font-weight: 600; margin: 16px 0 8px 0;">Complex Pattern:</h4>
                    <pre style="background: var(--aud-gray-100); padding: 12px; border-radius: 8px; overflow-x: auto; font-size: 0.875rem;">{
  "type": "ml_model",
  "architecture": "transformer",
  "parameters": {
    "layers": 12,
    "attention_heads": 8
  },
  "metadata": {
    "created": "2024-11-19",
    "version": "1.0.0"
  }
}</pre>
                </div>
            </div>
        </div>
    </div>
</div>
