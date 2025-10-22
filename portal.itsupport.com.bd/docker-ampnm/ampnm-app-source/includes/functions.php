<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once __DIR__ . '/../config.php'; // Adjusted path to config.php

// Include modular function files
require_once __DIR__ . '/utils/db_helpers.php';
require_once __DIR__ . '/app_settings/app_settings.php';
require_once __DIR__ . '/license/license_management.php';
require_once __DIR__ . '/docker/docker_compose_generator.php';

// The remaining functions (authentication, redirects, support tickets, profile management, portal UI)
// are part of the *License Portal* application, not the AMPNM Docker application.
// Therefore, they should not be in this functions.php file.
// The AMPNM Docker application's functions.php should only contain functions relevant to AMPNM.

// The original functions.php also contained:
// - Customer Authentication Functions (authenticateCustomer, isCustomerLoggedIn, logoutCustomer, updateCustomerPassword)
// - Admin Authentication Functions (authenticateAdmin, isAdminLoggedIn, logoutAdmin, updateAdminPassword)
// - Common Redirects (redirectToLogin, redirectToAdminLogin, redirectToDashboard, redirectToAdminDashboard)
// - Support Ticket System Functions (createSupportTicket, getCustomerTickets, getTicketDetails, getTicketReplies, addTicketReply, updateTicketStatus, getAllTickets)
// - Profile Management Functions (getCustomerData, getProfileData, updateCustomerProfile)
// - Basic HTML Header/Footer for the portal (portal_header, portal_footer, admin_header, admin_footer)

// These functions are specific to the *License Portal* (portal.itsupport.com.bd) and should reside in its own includes/functions.php.
// They are not part of the AMPNM Docker application's backend logic.
// Therefore, they are intentionally removed from this file during refactoring.

// Function to get database connection (defined in config.php)
// This function is still needed here as it's used by app_settings.php and license_management.php
// It is defined in portal.itsupport.com.bd/docker-ampnm/ampnm-app-source/config.php
// No change needed here, as it's included via config.php.