#!/bin/bash

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
until php /var/www/html/includes/db_check.php; do
  echo "MySQL is unavailable - sleeping"
  sleep 2
done
echo "MySQL is up - executing command"

# Run the database setup script
# This script will create tables and the admin user if they don't exist
php /var/www/html/database_setup.php

# Start Apache in the foreground
exec apache2-foreground