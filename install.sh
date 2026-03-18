#!/bin/bash

echo "JobPulse AI Installation Script"
echo "-------------------------------"

# Install composer dependencies
echo "Installing Composer dependencies..."
composer install

# Copy config if not exists
if [ ! -f "config.php" ]; then
    echo "Creating config.php from template..."
    cp config.php.new config.php
else
    echo "config.php already exists, skipping."
fi

# Copy users if not exists
if [ ! -f "data/users.json" ]; then
    echo "Setting up default admin user..."
    if [ ! -d "data" ]; then
        mkdir -p data
    fi
    cp data/users.example.json data/users.json
else
    echo "data/users.json already exists, skipping."
fi

# Set permissions for writeable directories
echo "Setting permissions for data and uploads directories..."
if [ ! -d "uploads" ]; then
    mkdir -p uploads
fi
chmod -R 700 data
chmod -R 700 uploads

echo "Installation complete!"
echo "Please remember to add your Gemini API Key directly to config.php."
