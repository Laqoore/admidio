<?php
/**
 ***********************************************************************************************
 * Process geocoding requests for Member Map Plugin
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;

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

    // Only administrators can access this
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // Validate CSRF token
    $gCurrentSession->validateCsrfToken($_POST['admidio-csrf-token'] ?? '');

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

    // Get parameters
    $geocodeMode = $_POST['geocode_mode'] ?? 'missing';
    $geocodeRole = (int) ($_POST['geocode_role'] ?? 0);
    $forceUpdate = ($geocodeMode === 'all');

    // Get profile field IDs
    $latFieldId = $gProfileFields->getProperty($plg_latitude_field, 'usf_id');
    $lngFieldId = $gProfileFields->getProperty($plg_longitude_field, 'usf_id');

    if ($latFieldId === null || $lngFieldId === null) {
        throw new Exception('PLG_MEMBERMAP_COORDINATE_FIELDS_MISSING');
    }

    // Build query to get users to geocode
    $roleCondition = '';
    $roleParams = [];

    if ($geocodeRole > 0) {
        $roleCondition = ' AND mem_rol_id = ? ';
        $roleParams[] = $geocodeRole;
    }

    // Get address field IDs based on address mode
    $addressFieldIds = [];
    if ($plg_address_mode === 'single') {
        // Single address field mode
        $fieldId = $gProfileFields->getProperty($plg_single_address_field, 'usf_id');
        if ($fieldId !== null) {
            $addressFieldIds[] = $fieldId;
        }
    } else {
        // Multiple address fields mode
        foreach ($plg_address_fields as $field) {
            $fieldId = $gProfileFields->getProperty($field, 'usf_id');
            if ($fieldId !== null) {
                $addressFieldIds[] = $fieldId;
            }
        }
    }

    if (empty($addressFieldIds)) {
        echo json_encode([
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => ['No address fields configured or fields not found']
        ]);
        exit;
    }

    // Get users who have at least one address field filled
    $addressFieldPlaceholders = implode(',', array_fill(0, count($addressFieldIds), '?'));

    if ($forceUpdate) {
        // Get all users with address data
        $sql = 'SELECT DISTINCT usr_id
                  FROM ' . TBL_USERS . '
            INNER JOIN ' . TBL_MEMBERS . ' ON mem_usr_id = usr_id
            INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
            INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
            INNER JOIN ' . TBL_USER_DATA . ' ON usd_usr_id = usr_id
                 WHERE usr_valid = true
                   AND rol_valid = true
                   AND mem_begin <= ?
                   AND mem_end >= ?
                   AND (cat_org_id = ? OR cat_org_id IS NULL)
                   AND usd_usf_id IN (' . $addressFieldPlaceholders . ')
                   AND usd_value IS NOT NULL
                   AND usd_value <> \'\'' . $roleCondition;
        $queryParams = array_merge([DATE_NOW, DATE_NOW, $gCurrentOrgId], $addressFieldIds, $roleParams);
    } else {
        // Get users with address but without coordinates
        $sql = 'SELECT DISTINCT usr_id
                  FROM ' . TBL_USERS . '
            INNER JOIN ' . TBL_MEMBERS . ' ON mem_usr_id = usr_id
            INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
            INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
            INNER JOIN ' . TBL_USER_DATA . ' AS addr ON addr.usd_usr_id = usr_id
             LEFT JOIN ' . TBL_USER_DATA . ' AS lat ON lat.usd_usr_id = usr_id AND lat.usd_usf_id = ?
             LEFT JOIN ' . TBL_USER_DATA . ' AS lng ON lng.usd_usr_id = usr_id AND lng.usd_usf_id = ?
                 WHERE usr_valid = true
                   AND rol_valid = true
                   AND mem_begin <= ?
                   AND mem_end >= ?
                   AND (cat_org_id = ? OR cat_org_id IS NULL)
                   AND addr.usd_usf_id IN (' . $addressFieldPlaceholders . ')
                   AND addr.usd_value IS NOT NULL
                   AND addr.usd_value <> \'\'
                   AND (lat.usd_value IS NULL OR lat.usd_value = \'\' OR lng.usd_value IS NULL OR lng.usd_value = \'\')' . $roleCondition;
        $queryParams = array_merge([$latFieldId, $lngFieldId, DATE_NOW, DATE_NOW, $gCurrentOrgId], $addressFieldIds, $roleParams);
    }

    $statement = $gDb->queryPrepared($sql, $queryParams);
    $userIds = [];
    while ($row = $statement->fetch()) {
        $userIds[] = (int) $row['usr_id'];
    }

    // Initialize geocoding service
    $geocodingService = new GeocodingService(
        $plg_geocoding_service,
        $plg_google_api_key,
        $plg_geocoding_delay
    );

    // Initialize batch processor with address mode configuration
    $batchProcessor = new GeocodingBatchProcessor(
        $geocodingService,
        $gDb,
        $gProfileFields,
        $plg_address_fields,
        $plg_latitude_field,
        $plg_longitude_field,
        $plg_address_mode,
        $plg_single_address_field
    );

    // Process all users
    $batchProcessor->processUsers($userIds, $forceUpdate);

    // Get results
    $results = $batchProcessor->getResults();

    echo json_encode($results);
} catch (Throwable $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => 0,
        'failed' => 0,
        'skipped' => 0,
        'errors' => [$e->getMessage()]
    ]);
}
