Neighborhood Help - Quick Run
1. Put folder in htdocs (e.g., C:\xampp\htdocs\neighborhood_help).
2. Import sql/neighborhood_help.sql into phpMyAdmin.
3. Ensure inc/config.php DB credentials match your MySQL.
4. Make assets/uploads writable.
5. Visit http://localhost/neighborhood_help/register.php
6. Create a user; to create admin, change role in phpMyAdmin to 'admin'.
7. Google Maps is integrated for post location picking, post detail maps, and the community map. The API key is configured in inc/config.php as $google_maps_api_key. You can override it with NH_GOOGLE_MAPS_API_KEY.
8. To enable Google registration, set environment variable NH_GOOGLE_CLIENT_ID with your Google OAuth Web client ID.
9. In Google Cloud Console, add your local origin (for example http://localhost) and the site URL where register.php runs to the OAuth allowed JavaScript origins.
10. In Google Cloud Console, authorize the Maps API key for the exact site URLs you use, such as http://localhost/*, http://127.0.0.1/*, and your deployed domain.
11. To enable Apple registration, set NH_APPLE_CLIENT_ID (Service ID) and NH_APPLE_REDIRECT_URI (must match Apple developer config).
