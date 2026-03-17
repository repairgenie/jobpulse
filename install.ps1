Write-Host "JobPulse AI Installation Script"
Write-Host "-------------------------------"

# Install composer dependencies
Write-Host "Installing Composer dependencies..."
composer install

# Copy config if not exists
if (!(Test-Path "config.php")) {
    Write-Host "Creating config.php from template..."
    Copy-Item config.php.new config.php
} else {
    Write-Host "config.php already exists, skipping."
}

# Copy users if not exists
if (!(Test-Path "data/users.json")) {
    Write-Host "Setting up default admin user..."
    if (!(Test-Path "data")) {
        New-Item -ItemType Directory -Force -Path "data"
    }
    Copy-Item data/users.example.json data/users.json
} else {
    Write-Host "data/users.json already exists, skipping."
}

Write-Host "Installation complete!"
Write-Host "Please remember to add your Gemini API Key directly to config.php."
