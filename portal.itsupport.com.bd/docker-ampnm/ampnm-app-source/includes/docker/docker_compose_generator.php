<?php
/**
 * Recursively adds a folder and its contents to a ZipArchive.
 *
 * @param ZipArchive $zip The ZipArchive object.
 * @param string $folderPath The full path to the folder to add.
 * @param string $zipPath The path inside the zip file (e.g., 'my-project/subfolder').
 */
function addFolderToZip(ZipArchive $zip, string $folderPath, string $zipPath) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        // Get real path for current file
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($folderPath) + 1);

        // Add current file to zip
        $zip->addFile($filePath, $zipPath . '/' . $relativePath);
    }
}


// --- Functions to generate Docker setup file contents ---
function getDockerfileContent() {
    $dockerfile_lines = [
        "FROM php:8.2-apache",
        "",
        "# Install system dependencies",
        "RUN apt-get update && apt-get install -y \\",
        "    git \\",
        "    unzip \\",
        "    libzip-dev \\",
        "    libpng-dev \\",
        "    libjpeg-dev \\",
        "    libfreetype6-dev \\",
        "    libicu-dev \\",
        "    libonig-dev \\",
        "    libxml2-dev \\",
        "    nmap \\",
        "    mysql-client \\", # Added mysql-client for mysqldump/mysql commands
        "    && rm -rf /var/lib/apt/lists/*",
        "",
        "# Install PHP extensions",
        "RUN docker-php-ext-configure gd --with-freetype --with-jpeg \\",
        "    && docker-php-ext-install -j\$(nproc) gd pdo_mysql zip intl opcache bcmath exif",
        "",
        "# Enable Apache modules",
        "RUN a2enmod rewrite",
        "",
        "# Copy application files from the ampnm-app-source directory",
        "COPY ampnm-app-source/ /var/www/html/",
        "",
        "# Copy the entrypoint script from the build context root",
        "COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh",
        "RUN chmod +x /usr/local/bin/docker-entrypoint.sh",
        "",
        "# Set permissions",
        "RUN chown -R www-data:www-data /var/www/html \\",
        "    && chmod -R 755 /var/www/html",
        "",
        "# Expose port 2266 (or whatever port your app runs on)",
        "EXPOSE 2266",
        "",
        "# Update Apache configuration to listen on 2266",
        "RUN echo \"Listen 2266\" >> /etc/apache2/ports.conf \\",
        "    && sed -i -e 's/VirtualHost \\*:80/VirtualHost \\*:2266/g' /etc/apache2/sites-available/000-default.conf \\",
        "    && sed -i -e 's/VirtualHost \\*:80/VirtualHost \\*:2266/g' /etc/apache2/sites-enabled/000-default.conf",
        "",
        "# Ensure the uploads directory exists and has correct permissions",
        "RUN mkdir -p /var/www/html/uploads/icons \\",
        "    mkdir -p /var/www/html/uploads/map_backgrounds \\",
        "    mkdir -p /var/www/html/uploads/backups \\", # Added backups directory
        "    && chown -R www-data:www-data /var/www/html/uploads \\",
        "    && chmod -R 775 /var/www/html/uploads",
        "",
        "# Use the copied entrypoint script",
        "ENTRYPOINT [\"/usr/local/bin/docker-entrypoint.sh\"]"
    ];
    return implode("\n", $dockerfile_lines);
}

function getDockerComposeContent($license_key) {
    // Define the LICENSE_API_URL for the AMPNM app
    // This should point to the verify_license.php endpoint on your portal
    $license_api_url = 'https://portal.itsupport.com.bd/verify_license.php'; // Ensure this matches your deployment

    $docker_compose_content = <<<EOT
version: '3.8'

services:
  app:
    build:
      context: . # Build context is the docker-ampnm folder
      dockerfile: Dockerfile
    # The entrypoint is now handled by the Dockerfile itself
    volumes:
      - ./ampnm-app-source/:/var/www/html/ # Mount the application source into the container
    depends_on:
      db:
        condition: service_healthy
    environment:
      - DB_HOST=db # Changed from 127.0.0.1 to 'db' (the service name)
      - DB_NAME=network_monitor
      - DB_USER=user
      - DB_PASSWORD=password
      - MYSQL_ROOT_PASSWORD=rootpassword
      - ADMIN_PASSWORD=password
      - LICENSE_API_URL={$license_api_url}
      # APP_LICENSE_KEY is no longer set here. It is configured via the web UI after initial setup.
    ports:
      - "2266:2266" # Main app will now run on port 2266
    restart: unless-stopped

  db:
    image: mysql:8.0
    command: --default-authentication-plugin=mysql_native_password
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: network_monitor
      MYSQL_USER: user
      MYSQL_PASSWORD: password
    volumes:
      - db_data:/var/lib/mysql
    ports:
      - "3306:3306"
    restart: unless-stopped
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h localhost -u root -p\$\$MYSQL_ROOT_PASSWORD"]
      interval: 10s
      timeout: 5s
      retries: 10

volumes:
  db_data:
EOT;
    return $docker_compose_content;
}