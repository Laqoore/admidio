<?php
/**
 ***********************************************************************************************
 * Admin page for Member Map Plugin - Geocoding management
 *
 * This page allows administrators to manage geocoding of member addresses.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\UI\Presenter\FormPresenter;

try {
    $rootPath = dirname(__DIR__, 2);
    $pluginFolder = basename(__DIR__);

    require_once($rootPath . '/system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Include plugin configuration
    if (is_file(__DIR__ . '/config.php')) {
        require_once(__DIR__ . '/config.php');
    }

    // Include geocoding service
    require_once(__DIR__ . '/geocoding_service.php');

    // Only administrators can access this page
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // Set default configuration values
    if (!isset($plg_latitude_field) || $plg_latitude_field === '') {
        $plg_latitude_field = 'LATITUDE';
    }
    if (!isset($plg_longitude_field) || $plg_longitude_field === '') {
        $plg_longitude_field = 'LONGITUDE';
    }
    if (!isset($plg_address_fields) || !is_array($plg_address_fields)) {
        $plg_address_fields = array('STREET', 'POSTCODE', 'CITY', 'COUNTRY');
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

    $headline = $gL10n->get('PLG_MEMBERMAP_ADMIN');
    $gNavigation->addUrl(CURRENT_URL, $headline);

    $page = PagePresenter::withHtmlIDAndHeadline('admidio-membermap-admin', $headline);

    // Check if coordinate fields exist
    $latFieldId = $gProfileFields->getProperty($plg_latitude_field, 'usf_id');
    $lngFieldId = $gProfileFields->getProperty($plg_longitude_field, 'usf_id');

    $fieldsExist = ($latFieldId !== null && $lngFieldId !== null);

    // Get statistics
    $stats = [
        'total_members' => 0,
        'with_address' => 0,
        'with_coordinates' => 0,
        'without_coordinates' => 0
    ];

    if ($fieldsExist) {
        // Total active members
        $sql = 'SELECT COUNT(DISTINCT usr_id) AS cnt
                  FROM ' . TBL_USERS . '
            INNER JOIN ' . TBL_MEMBERS . ' ON mem_usr_id = usr_id
            INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
            INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                 WHERE usr_valid = true
                   AND rol_valid = true
                   AND mem_begin <= ?
                   AND mem_end >= ?
                   AND (cat_org_id = ? OR cat_org_id IS NULL)';
        $statement = $gDb->queryPrepared($sql, [DATE_NOW, DATE_NOW, $gCurrentOrgId]);
        $stats['total_members'] = (int) $statement->fetchColumn();

        // Get address field IDs
        $addressFieldIds = [];
        foreach ($plg_address_fields as $field) {
            $fieldId = $gProfileFields->getProperty($field, 'usf_id');
            if ($fieldId !== null) {
                $addressFieldIds[] = $fieldId;
            }
        }

        if (!empty($addressFieldIds)) {
            // Members with at least one address field filled
            $addressFieldPlaceholders = implode(',', array_fill(0, count($addressFieldIds), '?'));
            $sql = 'SELECT COUNT(DISTINCT usr_id) AS cnt
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
                       AND usd_value <> \'\'';
            $params = array_merge([DATE_NOW, DATE_NOW, $gCurrentOrgId], $addressFieldIds);
            $statement = $gDb->queryPrepared($sql, $params);
            $stats['with_address'] = (int) $statement->fetchColumn();
        }

        // Members with coordinates
        $sql = 'SELECT COUNT(DISTINCT usr_id) AS cnt
                  FROM ' . TBL_USERS . '
            INNER JOIN ' . TBL_MEMBERS . ' ON mem_usr_id = usr_id
            INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
            INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
            INNER JOIN ' . TBL_USER_DATA . ' AS lat ON lat.usd_usr_id = usr_id AND lat.usd_usf_id = ?
            INNER JOIN ' . TBL_USER_DATA . ' AS lng ON lng.usd_usr_id = usr_id AND lng.usd_usf_id = ?
                 WHERE usr_valid = true
                   AND rol_valid = true
                   AND mem_begin <= ?
                   AND mem_end >= ?
                   AND (cat_org_id = ? OR cat_org_id IS NULL)
                   AND lat.usd_value IS NOT NULL
                   AND lat.usd_value <> \'\'
                   AND lng.usd_value IS NOT NULL
                   AND lng.usd_value <> \'\'';
        $statement = $gDb->queryPrepared($sql, [$latFieldId, $lngFieldId, DATE_NOW, DATE_NOW, $gCurrentOrgId]);
        $stats['with_coordinates'] = (int) $statement->fetchColumn();

        $stats['without_coordinates'] = $stats['with_address'] - $stats['with_coordinates'];
        if ($stats['without_coordinates'] < 0) {
            $stats['without_coordinates'] = 0;
        }
    }

    // Get all active roles for selection
    $rolesData = [];
    $sql = 'SELECT rol_id, rol_uuid, rol_name, cat_name
              FROM ' . TBL_ROLES . '
        INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
             WHERE rol_valid = true
               AND (cat_org_id = ? OR cat_org_id IS NULL)
               AND cat_name_intern <> \'EVENTS\'
          ORDER BY cat_sequence, rol_name';
    $rolesStatement = $gDb->queryPrepared($sql, [$gCurrentOrgId]);
    while ($roleRow = $rolesStatement->fetch()) {
        $rolesData[] = [
            'id' => $roleRow['rol_id'],
            'uuid' => $roleRow['rol_uuid'],
            'name' => $roleRow['rol_name'],
            'category' => $roleRow['cat_name']
        ];
    }

    // Page content
    $page->addHtml('<div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-info-circle"></i> ' . $gL10n->get('PLG_MEMBERMAP_STATUS') . '
        </div>
        <div class="card-body">');

    if (!$fieldsExist) {
        $page->addHtml('
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>' . $gL10n->get('PLG_MEMBERMAP_FIELDS_MISSING') . '</strong><br>
                ' . $gL10n->get('PLG_MEMBERMAP_FIELDS_MISSING_DESC', array($plg_latitude_field, $plg_longitude_field)) . '
            </div>
            <p>' . $gL10n->get('PLG_MEMBERMAP_CREATE_FIELDS_HINT') . '</p>
            <a href="' . ADMIDIO_URL . FOLDER_MODULES . '/profile-fields/profile_fields.php" class="btn btn-primary">
                <i class="bi bi-gear"></i> ' . $gL10n->get('SYS_PROFILE_FIELDS') . '
            </a>
        ');
    } else {
        $page->addHtml('
            <div class="alert alert-success mb-3">
                <i class="bi bi-check-circle"></i>
                ' . $gL10n->get('PLG_MEMBERMAP_FIELDS_CONFIGURED', array($plg_latitude_field, $plg_longitude_field)) . '
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="card-title">' . $stats['total_members'] . '</h3>
                            <p class="card-text">' . $gL10n->get('SYS_MEMBERS') . '</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="card-title">' . $stats['with_address'] . '</h3>
                            <p class="card-text">' . $gL10n->get('PLG_MEMBERMAP_WITH_ADDRESS') . '</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center bg-success text-white">
                        <div class="card-body">
                            <h3 class="card-title">' . $stats['with_coordinates'] . '</h3>
                            <p class="card-text">' . $gL10n->get('PLG_MEMBERMAP_WITH_COORDINATES') . '</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center ' . ($stats['without_coordinates'] > 0 ? 'bg-warning' : 'bg-light') . '">
                        <div class="card-body">
                            <h3 class="card-title">' . $stats['without_coordinates'] . '</h3>
                            <p class="card-text">' . $gL10n->get('PLG_MEMBERMAP_WITHOUT_COORDINATES') . '</p>
                        </div>
                    </div>
                </div>
            </div>
        ');
    }

    $page->addHtml('
        </div>
    </div>');

    // Geocoding form (only show if fields exist)
    if ($fieldsExist) {
        $page->addHtml('
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-geo-alt"></i> ' . $gL10n->get('PLG_MEMBERMAP_BATCH_GEOCODING') . '
            </div>
            <div class="card-body">
                <p>' . $gL10n->get('PLG_MEMBERMAP_BATCH_GEOCODING_DESC') . '</p>
                <p><strong>' . $gL10n->get('PLG_MEMBERMAP_GEOCODING_SERVICE') . ':</strong> ' .
                    ($plg_geocoding_service === 'google' ? 'Google Maps API' : 'OpenStreetMap Nominatim') . '</p>

                <form id="geocodingForm" method="post" action="' . ADMIDIO_URL . FOLDER_PLUGINS . '/' . $pluginFolder . '/geocode_process.php">
                    <input type="hidden" name="admidio-csrf-token" value="' . $gCurrentSession->getCsrfToken() . '">

                    <div class="mb-3">
                        <label for="geocode_mode" class="form-label">' . $gL10n->get('PLG_MEMBERMAP_GEOCODE_MODE') . '</label>
                        <select id="geocode_mode" name="geocode_mode" class="form-select">
                            <option value="missing">' . $gL10n->get('PLG_MEMBERMAP_GEOCODE_MISSING_ONLY') . '</option>
                            <option value="all">' . $gL10n->get('PLG_MEMBERMAP_GEOCODE_ALL') . '</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="geocode_role" class="form-label">' . $gL10n->get('PLG_MEMBERMAP_FILTER_BY_ROLE') . '</label>
                        <select id="geocode_role" name="geocode_role" class="form-select">
                            <option value="">' . $gL10n->get('SYS_ALL_ROLES') . '</option>');

        $currentCategory = '';
        foreach ($rolesData as $role) {
            if ($currentCategory !== $role['category']) {
                if ($currentCategory !== '') {
                    $page->addHtml('</optgroup>');
                }
                $page->addHtml('<optgroup label="' . SecurityUtils::encodeHTML($role['category']) . '">');
                $currentCategory = $role['category'];
            }
            $page->addHtml('<option value="' . $role['id'] . '">' .
                SecurityUtils::encodeHTML($role['name']) . '</option>');
        }
        if ($currentCategory !== '') {
            $page->addHtml('</optgroup>');
        }

        $page->addHtml('
                        </select>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        ' . $gL10n->get('PLG_MEMBERMAP_GEOCODING_RATE_LIMIT', array($plg_geocoding_delay / 1000)) . '
                    </div>

                    <button type="submit" class="btn btn-primary" id="startGeocoding">
                        <i class="bi bi-play-fill"></i> ' . $gL10n->get('PLG_MEMBERMAP_START_GEOCODING') . '
                    </button>
                </form>

                <div id="geocodingProgress" class="mt-4" style="display: none;">
                    <h5>' . $gL10n->get('PLG_MEMBERMAP_PROGRESS') . '</h5>
                    <div class="progress mb-2">
                        <div class="progress-bar" id="progressBar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div id="progressText" class="mb-2"></div>
                    <div id="progressResults"></div>
                </div>
            </div>
        </div>');

        // Configuration info
        $page->addHtml('
        <div class="card">
            <div class="card-header">
                <i class="bi bi-gear"></i> ' . $gL10n->get('PLG_MEMBERMAP_CONFIGURATION') . '
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th>' . $gL10n->get('PLG_MEMBERMAP_LATITUDE_FIELD') . '</th>
                        <td><code>' . SecurityUtils::encodeHTML($plg_latitude_field) . '</code></td>
                    </tr>
                    <tr>
                        <th>' . $gL10n->get('PLG_MEMBERMAP_LONGITUDE_FIELD') . '</th>
                        <td><code>' . SecurityUtils::encodeHTML($plg_longitude_field) . '</code></td>
                    </tr>
                    <tr>
                        <th>' . $gL10n->get('PLG_MEMBERMAP_ADDRESS_FIELDS') . '</th>
                        <td><code>' . SecurityUtils::encodeHTML(implode(', ', $plg_address_fields)) . '</code></td>
                    </tr>
                    <tr>
                        <th>' . $gL10n->get('PLG_MEMBERMAP_GEOCODING_SERVICE') . '</th>
                        <td>' . ($plg_geocoding_service === 'google' ? 'Google Maps API' : 'OpenStreetMap Nominatim') . '</td>
                    </tr>
                </table>
                <p class="text-muted">' . $gL10n->get('PLG_MEMBERMAP_CONFIG_HINT') . '</p>
            </div>
        </div>');

        // Add JavaScript for form handling
        $page->addJavascript('
            $("#geocodingForm").on("submit", function(e) {
                e.preventDefault();

                var form = $(this);
                var submitBtn = $("#startGeocoding");
                var progressDiv = $("#geocodingProgress");
                var progressBar = $("#progressBar");
                var progressText = $("#progressText");
                var progressResults = $("#progressResults");

                submitBtn.prop("disabled", true).html(\'<span class="spinner-border spinner-border-sm"></span> ' . $gL10n->get('PLG_MEMBERMAP_PROCESSING') . '\');
                progressDiv.show();
                progressBar.css("width", "0%");
                progressText.text("' . $gL10n->get('PLG_MEMBERMAP_INITIALIZING') . '");
                progressResults.html("");

                $.ajax({
                    url: form.attr("action"),
                    method: "POST",
                    data: form.serialize(),
                    dataType: "json",
                    success: function(response) {
                        progressBar.css("width", "100%").addClass("bg-success");
                        progressText.text("' . $gL10n->get('PLG_MEMBERMAP_COMPLETED') . '");

                        var resultHtml = "<div class=\'alert alert-info\'>";
                        resultHtml += "<strong>' . $gL10n->get('PLG_MEMBERMAP_RESULTS') . ':</strong><br>";
                        resultHtml += "' . $gL10n->get('PLG_MEMBERMAP_SUCCESSFUL') . ': " + response.success + "<br>";
                        resultHtml += "' . $gL10n->get('PLG_MEMBERMAP_FAILED') . ': " + response.failed + "<br>";
                        resultHtml += "' . $gL10n->get('PLG_MEMBERMAP_SKIPPED') . ': " + response.skipped + "</div>";

                        if (response.errors && response.errors.length > 0) {
                            resultHtml += "<div class=\'alert alert-warning\'><strong>' . $gL10n->get('PLG_MEMBERMAP_ERRORS') . ':</strong><ul>";
                            response.errors.forEach(function(error) {
                                resultHtml += "<li>" + error + "</li>";
                            });
                            resultHtml += "</ul></div>";
                        }

                        progressResults.html(resultHtml);
                        submitBtn.prop("disabled", false).html(\'<i class="bi bi-play-fill"></i> ' . $gL10n->get('PLG_MEMBERMAP_START_GEOCODING') . '\');

                        // Reload page after 3 seconds to update stats
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    },
                    error: function(xhr, status, error) {
                        progressBar.css("width", "100%").addClass("bg-danger");
                        progressText.text("' . $gL10n->get('SYS_ERROR') . '");
                        progressResults.html("<div class=\'alert alert-danger\'>" + error + "</div>");
                        submitBtn.prop("disabled", false).html(\'<i class="bi bi-play-fill"></i> ' . $gL10n->get('PLG_MEMBERMAP_START_GEOCODING') . '\');
                    }
                });
            });
        ', true);
    }

    // Back button
    $page->addHtml('
        <div class="mt-4">
            <a href="' . ADMIDIO_URL . FOLDER_PLUGINS . '/' . $pluginFolder . '/index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> ' . $gL10n->get('PLG_MEMBERMAP_BACK_TO_MAP') . '
            </a>
        </div>
    ');

    $page->show();
} catch (Throwable $e) {
    if (isset($gMessage)) {
        $gMessage->show($e->getMessage());
    } else {
        echo $e->getMessage();
    }
}
