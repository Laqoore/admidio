<?php
/**
 ***********************************************************************************************
 * Geocode a single user for Member Map Plugin
 *
 * This can be called via AJAX when a user's address is updated,
 * or manually triggered by an admin.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * user_uuid : UUID of the user to geocode
 * force     : Force geocoding even if coordinates exist (1 or 0)
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Users\Entity\User;

try {
    $rootPath = dirname(__DIR__, 2);

    require_once($rootPath . '/system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Include plugin configuration
    if (is_file(__DIR__ . '/config.php')) {
        require_once(__DIR__ . '/config.php');
    }

    // Include geocoding service
    require_once(__DIR__ . '/geocoding_service.php');

    // Set content type to JSON
    header('Content-Type: application/json');

    // Get parameters
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid', array('requireValue' => true));
    $getForce = admFuncVariableIsValid($_GET, 'force', 'bool', array('defaultValue' => false));

    // Set default configuration values
    if (!isset($plg_latitude_field) || $plg_latitude_field === '') {
        $plg_latitude_field = 'LATITUDE';
    }
    if (!isset($plg_longitude_field) || $plg_longitude_field === '') {
        $plg_longitude_field = 'LONGITUDE';
    }
    if (!isset($plg_address_mode) || $plg_address_mode === '') {
        $plg_address_mode = 'multiple';
    }
    if (!isset($plg_address_fields) || !is_array($plg_address_fields)) {
        $plg_address_fields = array('STREET', 'POSTCODE', 'CITY', 'COUNTRY');
    }
    if (!isset($plg_single_address_field) || $plg_single_address_field === '') {
        $plg_single_address_field = 'ADDRESS';
    }
    if (!isset($plg_geocoding_service) || $plg_geocoding_service === '') {
        $plg_geocoding_service = 'nominatim';
    }
    if (!isset($plg_google_api_key)) {
        $plg_google_api_key = '';
    }
    if (!isset($plg_geocoding_delay) || !is_numeric($plg_geocoding_delay)) {
        $plg_geocoding_delay = 1000;
    }

    // Load user
    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);

    // Check if user has permission to edit this profile
    if (!$gCurrentUser->hasRightEditProfile($user)) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // Check if we need to geocode
    if (!$getForce && !GeocodingService::addressNeedsGeocoding(
        $user,
        $plg_address_fields,
        $plg_latitude_field,
        $plg_longitude_field,
        $plg_address_mode,
        $plg_single_address_field
    )) {
        echo json_encode([
            'success' => true,
            'message' => 'Coordinates already exist',
            'skipped' => true
        ]);
        exit;
    }

    // Build address based on mode
    $address = GeocodingService::buildAddress(
        $user,
        $plg_address_mode,
        $plg_address_fields,
        $plg_single_address_field
    );

    if (empty($address)) {
        echo json_encode([
            'success' => false,
            'message' => 'No address data available'
        ]);
        exit;
    }

    // Initialize geocoding service
    $geocodingService = new GeocodingService(
        $plg_geocoding_service,
        $plg_google_api_key,
        $plg_geocoding_delay
    );

    // Geocode
    $coordinates = $geocodingService->geocode($address);

    if ($coordinates === null) {
        echo json_encode([
            'success' => false,
            'message' => 'Could not geocode address: ' . $address
        ]);
        exit;
    }

    // Save coordinates
    $user->setValue($plg_latitude_field, (string) $coordinates['lat']);
    $user->setValue($plg_longitude_field, (string) $coordinates['lng']);
    $user->save();

    echo json_encode([
        'success' => true,
        'message' => 'Coordinates saved successfully',
        'lat' => $coordinates['lat'],
        'lng' => $coordinates['lng'],
        'address' => $address
    ]);
} catch (Throwable $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
