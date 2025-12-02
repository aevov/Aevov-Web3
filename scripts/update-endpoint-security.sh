#!/bin/bash

# Aevov Security Update Script
# Systematically updates all REST API endpoints to use SecurityHelper

echo "=== Aevov REST API Security Update ==="
echo ""

# Array of endpoint files to update
ENDPOINT_FILES=(
    "aevov-physics-engine/includes/API/PhysicsEndpoint.php"
    "aevov-neuro-architect/includes/api/class-neuro-architect-endpoint.php"
    "aevov-simulation-engine/includes/api/class-simulation-endpoint.php"
    "aevov-cognitive-engine/includes/api/class-cognitive-endpoint.php"
    "aevov-language-engine/includes/api/class-language-endpoint.php"
    "aevov-language-engine-v2/includes/api/class-language-endpoint.php"
    "aevov-image-engine/includes/api/class-image-endpoint.php"
    "aevov-music-forge/includes/api/class-music-endpoint.php"
    "aevov-embedding-engine/includes/api/class-embedding-endpoint.php"
    "aevov-transcription-engine/includes/api/class-transcription-endpoint.php"
    "aevov-reasoning-engine/includes/api/class-reasoning-endpoint.php"
    "aevov-playground/includes/api/class-playground-endpoint.php"
    "aevov-stream/includes/api/class-stream-endpoint.php"
    "aevov-application-forge/includes/api/class-application-endpoint.php"
    "aevov-super-app-forge/includes/api/class-application-endpoint.php"
    "aevov-super-app-forge/includes/api/class-simulation-endpoint.php"
    "aps-tools/includes/api/class-chat-endpoint.php"
)

# Backup directory
BACKUP_DIR="/home/user/Aevov1/.security-backups-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"

echo "Backing up files to: $BACKUP_DIR"
echo ""

# Process each endpoint file
for file in "${ENDPOINT_FILES[@]}"; do
    FULL_PATH="/home/user/Aevov1/$file"

    if [ -f "$FULL_PATH" ]; then
        echo "Processing: $file"

        # Backup original
        cp "$FULL_PATH" "$BACKUP_DIR/"

        # Update permission callbacks
        # Replace '__return_true' with SecurityHelper permission check
        sed -i "s/'permission_callback' => '__return_true'/'permission_callback' => [\\\\Aevov\\\\Security\\\\SecurityHelper::class, 'can_edit_aevov']/g" "$FULL_PATH"

        # Also handle double quotes
        sed -i 's/"permission_callback" => "__return_true"/"permission_callback" => [\\Aevov\\Security\\SecurityHelper::class, "can_edit_aevov"]/g' "$FULL_PATH"

        # Check if SecurityHelper use statement exists
        if ! grep -q "use Aevov\\\\Security\\\\SecurityHelper" "$FULL_PATH"; then
            # Add SecurityHelper use statement after namespace
            sed -i '/^namespace /a\\nuse Aevov\\Security\\SecurityHelper;' "$FULL_PATH"
            echo "  ✓ Added SecurityHelper use statement"
        fi

        echo "  ✓ Updated permission callbacks"
    else
        echo "  ⚠ File not found: $file"
    fi
    echo ""
done

echo "=== Security Update Complete ==="
echo "Backups saved to: $BACKUP_DIR"
echo ""
echo "Next steps:"
echo "1. Review changes with: git diff"
echo "2. Test endpoints with authenticated/unauthenticated users"
echo "3. Add input sanitization to each endpoint handler"
echo "4. Add CSRF protection where needed"
