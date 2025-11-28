<?php
/**
 ***********************************************************************************************
 * Member Map Plugin - Shows members on an interactive map
 *
 * This plugin displays organization members on an interactive map using their
 * geocoded addresses. Coordinates are stored in custom profile fields.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * role_uuid : Filter members by role UUID (optional)
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\Overview;
use Admidio\Infrastructure\Utils\SecurityUtils;

try {
    $rootPath = dirname(__DIR__, 2);
    $pluginFolder = basename(__DIR__);

    require_once($rootPath . '/system/common.php');

    // Include plugin configuration
    if (is_file(__DIR__ . '/config.php')) {
        require_once(__DIR__ . '/config.php');
    }

    // Include geocoding service
    require_once(__DIR__ . '/geocoding_service.php');

    // Initialize and check the parameters
    $getRoleUuid = admFuncVariableIsValid($_GET, 'role_uuid', 'uuid', array('defaultValue' => ''));

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
    if (!isset($plg_map_zoom) || !is_numeric($plg_map_zoom)) {
        $plg_map_zoom = 6;
    }
    if (!isset($plg_map_center_lat) || !is_numeric($plg_map_center_lat)) {
        $plg_map_center_lat = 51.1657; // Germany center
    }
    if (!isset($plg_map_center_lng) || !is_numeric($plg_map_center_lng)) {
        $plg_map_center_lng = 10.4515;
    }
    if (!isset($plg_map_height) || $plg_map_height === '') {
        $plg_map_height = '500px';
    }
    if (!isset($plg_show_popup_info) || !is_bool($plg_show_popup_info)) {
        $plg_show_popup_info = true;
    }
    if (!isset($plg_popup_fields) || !is_array($plg_popup_fields)) {
        $plg_popup_fields = array('FIRST_NAME', 'LAST_NAME', 'CITY');
    }
    if (!isset($plg_roles_view_plugin) || !is_array($plg_roles_view_plugin)) {
        $plg_roles_view_plugin = array(); // Empty = all roles can view
    }
    if (!isset($plg_cluster_markers) || !is_bool($plg_cluster_markers)) {
        $plg_cluster_markers = true;
    }
    if (!isset($plg_visitors_can_view) || !is_bool($plg_visitors_can_view)) {
        $plg_visitors_can_view = false;
    }

    // Check access rights
    $hasAccess = false;
    if ($gValidLogin) {
        if (count($plg_roles_view_plugin) === 0) {
            $hasAccess = true;
        } else {
            $hasAccess = count(array_intersect($plg_roles_view_plugin, $gCurrentUser->getRoleMemberships())) > 0;
        }
    } elseif ($plg_visitors_can_view) {
        $hasAccess = true;
    }

    if (!$hasAccess) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // Get profile field IDs for latitude and longitude
    $latFieldId = $gProfileFields->getProperty($plg_latitude_field, 'usf_id');
    $lngFieldId = $gProfileFields->getProperty($plg_longitude_field, 'usf_id');

    if ($latFieldId === null || $lngFieldId === null) {
        throw new Exception('PLG_MEMBERMAP_COORDINATE_FIELDS_MISSING');
    }

    // Build role filter condition
    $roleCondition = '';
    $roleParams = array();

    if ($getRoleUuid !== '') {
        // Filter by specific role
        $sql = 'SELECT rol_id FROM ' . TBL_ROLES . ' WHERE rol_uuid = ?';
        $roleStatement = $gDb->queryPrepared($sql, array($getRoleUuid));
        $roleRow = $roleStatement->fetch();
        if ($roleRow) {
            $roleCondition = ' AND mem_rol_id = ? ';
            $roleParams[] = $roleRow['rol_id'];
        }
    }

    // Query members with coordinates
    $sql = 'SELECT DISTINCT usr_id, usr_uuid,
                   lat.usd_value AS latitude,
                   lng.usd_value AS longitude
              FROM ' . TBL_USERS . '
        INNER JOIN ' . TBL_MEMBERS . '
                ON mem_usr_id = usr_id
        INNER JOIN ' . TBL_ROLES . '
                ON rol_id = mem_rol_id
        INNER JOIN ' . TBL_CATEGORIES . '
                ON cat_id = rol_cat_id
        INNER JOIN ' . TBL_USER_DATA . ' AS lat
                ON lat.usd_usr_id = usr_id
               AND lat.usd_usf_id = ?
        INNER JOIN ' . TBL_USER_DATA . ' AS lng
                ON lng.usd_usr_id = usr_id
               AND lng.usd_usf_id = ?
             WHERE usr_valid = true
               AND rol_valid = true
               AND mem_begin <= ?
               AND mem_end   >= ?
               AND (cat_org_id = ? OR cat_org_id IS NULL)
               AND lat.usd_value IS NOT NULL
               AND lat.usd_value <> \'\'
               AND lng.usd_value IS NOT NULL
               AND lng.usd_value <> \'\'' . $roleCondition . '
          ORDER BY usr_id';

    $queryParams = array_merge(
        array($latFieldId, $lngFieldId, DATE_NOW, DATE_NOW, $gCurrentOrgId),
        $roleParams
    );

    $statement = $gDb->queryPrepared($sql, $queryParams);

    // Collect members data
    $membersData = array();
    $processedUsers = array();

    while ($row = $statement->fetch()) {
        // Skip duplicates (user might be in multiple roles)
        if (isset($processedUsers[$row['usr_id']])) {
            continue;
        }
        $processedUsers[$row['usr_id']] = true;

        // Load user object to get popup info
        $user = new \Admidio\Users\Entity\User($gDb, $gProfileFields);
        $user->readDataById($row['usr_id']);

        // Build popup content
        $popupInfo = array();
        if ($plg_show_popup_info) {
            foreach ($plg_popup_fields as $fieldName) {
                $value = $user->getValue($fieldName, 'html');
                if ($value !== '') {
                    $popupInfo[] = array(
                        'label' => $gProfileFields->getProperty($fieldName, 'usf_name'),
                        'value' => $value
                    );
                }
            }
        }

        $memberData = array(
            'uuid' => $row['usr_uuid'],
            'lat' => (float) $row['latitude'],
            'lng' => (float) $row['longitude'],
            'popup' => $popupInfo
        );

        // Add profile link if user is logged in and has rights
        if ($gValidLogin && $gCurrentUser->hasRightViewProfile($user)) {
            $memberData['profileUrl'] = SecurityUtils::encodeUrl(
                ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php',
                array('user_uuid' => $row['usr_uuid'])
            );
        }

        $membersData[] = $memberData;
    }

    // Get all active roles for filter dropdown
    $rolesData = array();
    $sql = 'SELECT rol_uuid, rol_name, cat_name
              FROM ' . TBL_ROLES . '
        INNER JOIN ' . TBL_CATEGORIES . '
                ON cat_id = rol_cat_id
             WHERE rol_valid = true
               AND (cat_org_id = ? OR cat_org_id IS NULL)
               AND cat_name_intern <> \'EVENTS\'
          ORDER BY cat_sequence, rol_name';
    $rolesStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

    while ($roleRow = $rolesStatement->fetch()) {
        $rolesData[] = array(
            'uuid' => $roleRow['rol_uuid'],
            'name' => $roleRow['rol_name'],
            'category' => $roleRow['cat_name']
        );
    }

    // Create plugin object for standalone page or embedded view
    if (isset($page)) {
        // Plugin is embedded in another page
        $myPlugin = new Overview($pluginFolder);
        $myPlugin->assignTemplateVariable('membersData', json_encode($membersData));
        $myPlugin->assignTemplateVariable('rolesData', $rolesData);
        $myPlugin->assignTemplateVariable('selectedRole', $getRoleUuid);
        $myPlugin->assignTemplateVariable('mapZoom', $plg_map_zoom);
        $myPlugin->assignTemplateVariable('mapCenterLat', $plg_map_center_lat);
        $myPlugin->assignTemplateVariable('mapCenterLng', $plg_map_center_lng);
        $myPlugin->assignTemplateVariable('mapHeight', $plg_map_height);
        $myPlugin->assignTemplateVariable('clusterMarkers', $plg_cluster_markers);
        $myPlugin->assignTemplateVariable('showPopupInfo', $plg_show_popup_info);
        $myPlugin->assignTemplateVariable('memberCount', count($membersData));
        echo $myPlugin->html('plugin.member-map.tpl');
    } else {
        // Standalone page
        $headline = $gL10n->get('PLG_MEMBERMAP_HEADLINE');
        $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-geo-alt-fill');

        $page = new \Admidio\UI\Presenter\PagePresenter($headline);
        $page->setTitle($headline);

        // Add CSS for Leaflet
        $page->addCssFile('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
        if ($plg_cluster_markers) {
            $page->addCssFile('https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css');
            $page->addCssFile('https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css');
        }

        // Add JavaScript for Leaflet
        $page->addJavascriptFile('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js');
        if ($plg_cluster_markers) {
            $page->addJavascriptFile('https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js');
        }

        // Add admin link if user is administrator
        if ($gCurrentUser->isAdministrator()) {
            $page->addPageFunctionsMenuItem(
                'menu_item_membermap_admin',
                $gL10n->get('PLG_MEMBERMAP_ADMIN'),
                ADMIDIO_URL . FOLDER_PLUGINS . '/' . $pluginFolder . '/admin.php',
                'bi-gear-fill'
            );
        }

        // Generate map JavaScript
        $mapJs = '
            var map = L.map("memberMap").setView([' . $plg_map_center_lat . ', ' . $plg_map_center_lng . '], ' . $plg_map_zoom . ');

            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                attribution: \'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors\',
                maxZoom: 19
            }).addTo(map);

            var membersData = ' . json_encode($membersData) . ';
            var markers = [];
            ';

        if ($plg_cluster_markers) {
            $mapJs .= '
            var markerCluster = L.markerClusterGroup();
            ';
        }

        $mapJs .= '
            membersData.forEach(function(member) {
                var marker = L.marker([member.lat, member.lng]);

                if (member.popup && member.popup.length > 0) {
                    var popupContent = "<div class=\"member-popup\">";
                    member.popup.forEach(function(field) {
                        popupContent += "<div><strong>" + field.label + ":</strong> " + field.value + "</div>";
                    });
                    if (member.profileUrl) {
                        popupContent += "<div class=\"mt-2\"><a href=\"" + member.profileUrl + "\" class=\"btn btn-primary btn-sm\">' . $gL10n->get('SYS_SHOW_PROFILE') . '</a></div>";
                    }
                    popupContent += "</div>";
                    marker.bindPopup(popupContent);
                }

                markers.push(marker);
                ';

        if ($plg_cluster_markers) {
            $mapJs .= 'markerCluster.addLayer(marker);';
        } else {
            $mapJs .= 'marker.addTo(map);';
        }

        $mapJs .= '
            });
            ';

        if ($plg_cluster_markers) {
            $mapJs .= '
            map.addLayer(markerCluster);
            ';
        }

        $mapJs .= '
            // Fit map to show all markers
            if (markers.length > 0) {
                var group = L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.1));
            }

            // Role filter
            $("#roleFilter").on("change", function() {
                var roleUuid = $(this).val();
                var url = "' . ADMIDIO_URL . FOLDER_PLUGINS . '/' . $pluginFolder . '/index.php";
                if (roleUuid !== "") {
                    url += "?role_uuid=" + roleUuid;
                }
                window.location.href = url;
            });
        ';

        $page->addJavascript($mapJs, true);

        // Add custom CSS
        $page->addHtml('<style>
            #memberMap { height: ' . $plg_map_height . '; width: 100%; border-radius: 8px; }
            .member-popup { min-width: 150px; }
            .member-popup div { margin-bottom: 3px; }
            .role-filter-container { margin-bottom: 15px; }
        </style>');

        // Build page content
        $content = '<div class="card">
            <div class="card-header">
                <i class="bi bi-geo-alt-fill"></i> ' . $headline . '
                <span class="badge bg-primary float-end">' . count($membersData) . ' ' . $gL10n->get('SYS_MEMBERS') . '</span>
            </div>
            <div class="card-body">
                <div class="role-filter-container">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="roleFilter" class="form-label">' . $gL10n->get('SYS_ROLE') . ':</label>
                            <select id="roleFilter" class="form-select">
                                <option value="">' . $gL10n->get('SYS_ALL') . '</option>';

        $currentCategory = '';
        foreach ($rolesData as $role) {
            if ($currentCategory !== $role['category']) {
                if ($currentCategory !== '') {
                    $content .= '</optgroup>';
                }
                $content .= '<optgroup label="' . SecurityUtils::encodeHTML($role['category']) . '">';
                $currentCategory = $role['category'];
            }
            $selected = ($getRoleUuid === $role['uuid']) ? ' selected' : '';
            $content .= '<option value="' . $role['uuid'] . '"' . $selected . '>' .
                SecurityUtils::encodeHTML($role['name']) . '</option>';
        }
        if ($currentCategory !== '') {
            $content .= '</optgroup>';
        }

        $content .= '
                            </select>
                        </div>
                    </div>
                </div>
                <div id="memberMap"></div>
            </div>
        </div>';

        $page->addHtml($content);
        $page->show();
    }
} catch (Throwable $e) {
    if (isset($page) && $page instanceof \Admidio\UI\Presenter\PagePresenter) {
        $page->addHtml('<div class="alert alert-danger">' . $e->getMessage() . '</div>');
        $page->show();
    } else {
        echo '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
    }
}
