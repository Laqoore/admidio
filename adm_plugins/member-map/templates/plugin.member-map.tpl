<div id="plugin-{$name}" class="admidio-plugin-content">
    <h3>{$l10n->get('PLG_MEMBERMAP_HEADLINE')}</h3>

    {if $memberCount > 0}
        <div class="mb-2">
            <span class="badge bg-primary">{$memberCount} {$l10n->get('SYS_MEMBERS')}</span>
        </div>

        {if count($rolesData) > 0}
            <div class="mb-2">
                <select id="roleFilterPlugin_{$name}" class="form-select form-select-sm" style="max-width: 200px;">
                    <option value="">{$l10n->get('SYS_ALL')}</option>
                    {assign var="currentCat" value=""}
                    {foreach $rolesData as $role}
                        {if $currentCat != $role.category}
                            {if $currentCat != ""}
                                </optgroup>
                            {/if}
                            <optgroup label="{$role.category|escape:'html'}">
                            {assign var="currentCat" value=$role.category}
                        {/if}
                        <option value="{$role.uuid}"{if $selectedRole == $role.uuid} selected{/if}>{$role.name|escape:'html'}</option>
                    {/foreach}
                    {if $currentCat != ""}
                        </optgroup>
                    {/if}
                </select>
            </div>
        {/if}

        <div id="memberMapPlugin_{$name}" style="height: {$mapHeight}; width: 100%; border-radius: 4px;"></div>

        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        {if $clusterMarkers}
            <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
            <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
        {/if}
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        {if $clusterMarkers}
            <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
        {/if}

        <script>
            (function() {
                var mapId = 'memberMapPlugin_{$name}';
                var map = L.map(mapId).setView([{$mapCenterLat}, {$mapCenterLng}], {$mapZoom});

                L.tileLayer('https://{literal}{s}{/literal}.tile.openstreetmap.org/{literal}{z}{/literal}/{literal}{x}{/literal}/{literal}{y}{/literal}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 19
                }).addTo(map);

                var membersData = {$membersData};
                var markers = [];

                {if $clusterMarkers}
                var markerCluster = L.markerClusterGroup();
                {/if}

                membersData.forEach(function(member) {
                    var marker = L.marker([member.lat, member.lng]);

                    {if $showPopupInfo}
                    if (member.popup && member.popup.length > 0) {
                        var popupContent = '<div class="member-popup">';
                        member.popup.forEach(function(field) {
                            popupContent += '<div><strong>' + field.label + ':</strong> ' + field.value + '</div>';
                        });
                        if (member.profileUrl) {
                            popupContent += '<div class="mt-2"><a href="' + member.profileUrl + '" class="btn btn-primary btn-sm">{$l10n->get('SYS_SHOW_PROFILE')}</a></div>';
                        }
                        popupContent += '</div>';
                        marker.bindPopup(popupContent);
                    }
                    {/if}

                    markers.push(marker);
                    {if $clusterMarkers}
                    markerCluster.addLayer(marker);
                    {else}
                    marker.addTo(map);
                    {/if}
                });

                {if $clusterMarkers}
                map.addLayer(markerCluster);
                {/if}

                if (markers.length > 0) {
                    var group = L.featureGroup(markers);
                    map.fitBounds(group.getBounds().pad(0.1));
                }

                // Role filter
                $('#roleFilterPlugin_{$name}').on('change', function() {
                    var roleUuid = $(this).val();
                    var url = '{$urlAdmidio}{$GLOBALS.FOLDER_PLUGINS}/{$name}/index.php';
                    if (roleUuid !== '') {
                        url += '?role_uuid=' + roleUuid;
                    }
                    window.location.href = url;
                });
            })();
        </script>
    {else}
        <div class="alert alert-info">
            {$l10n->get('PLG_MEMBERMAP_NO_MEMBERS_ON_MAP')}
        </div>
    {/if}
</div>
