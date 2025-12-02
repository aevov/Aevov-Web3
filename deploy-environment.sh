#!/bin/bash

echo "--- Setting up Aevov WordPress Development Environment ---"

# Check for Docker installation
if ! command -v docker &> /dev/null
then
    echo "Error: Docker is not installed. Please install Docker and Docker Compose to proceed."
    echo "Refer to https://docs.docker.com/get-docker/ for installation instructions."
    exit 1
fi

# Check for Docker Compose installation (might be docker compose or docker-compose)
if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null
then
    echo "Error: Docker Compose is not installed. Please install Docker Compose to proceed."
    echo "Refer to https://docs.docker.com/compose/install/ for installation instructions."
    exit 1
fi

echo "Docker and Docker Compose found. Proceeding with environment setup."

# Navigate to the aevov-testing-framework directory
if [ -d "aevov-testing-framework" ]; then
    cd aevov-testing-framework/
else
    echo "Error: 'aevov-testing-framework' directory not found. Please ensure you are running this script from the root of the AevovplusAlgorithmPress project."
    exit 1
fi

echo "Starting Docker services (WordPress, MySQL, PHP-CLI)... This may take a few minutes on first run."
docker compose up -d

if [ $? -eq 0 ]; then
    echo "--------------------------------------------------------"
    echo "WordPress environment started successfully!"
    echo "Access WordPress at: http://localhost:8081/"
    echo "To stop the environment, run 'docker-compose down' in the 'aevov-testing-framework' directory."
    echo "--------------------------------------------------------"
else
    echo "Error: Failed to start Docker services. Please check the output above for details."
    echo "Ensure Docker is running and you have sufficient permissions."
fi