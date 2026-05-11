#!/bin/bash

# Autoconfigure config.php for local SQLite server
if [ ! -f "config.php" ]; then
    echo "Autoconfiguring config.php for local SQLite server..."
    if [ -f "config.php.new" ]; then
        cp config.php.new config.php
        echo "Created config.php from template."
    else
        echo "Error: config.php.new not found!"
        exit 1
    fi
fi

# Ensure data directory exists for SQLite database
if [ ! -d "data" ]; then
    mkdir -p data
    chmod -R 700 data
    echo "Created data directory for SQLite database."
fi

# Ensure users.json exists for authentication
if [ ! -f "data/users.json" ] && [ -f "data/users.example.json" ]; then
    cp data/users.example.json data/users.json
    echo "Set up default admin user."
fi

# Ensure uploads directory exists
if [ ! -d "uploads" ]; then
    mkdir -p uploads
    chmod -R 700 uploads
fi

echo "Starting JobPulse local server on http://localhost:5000..."
php -S localhost:5000
