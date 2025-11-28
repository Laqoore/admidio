<?php
/**
 ***********************************************************************************************
 * Configuration file for the Member Map Plugin
 *
 * Rename this file to "config.php" and adjust the settings to your needs.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Profile field name for storing latitude (must be created in Admidio profile fields)
 * Profilfeldname für Breitengrad (muss in Admidio-Profilfeldern angelegt werden)
 */
$plg_latitude_field = 'LATITUDE';

/**
 * Profile field name for storing longitude (must be created in Admidio profile fields)
 * Profilfeldname für Längengrad (muss in Admidio-Profilfeldern angelegt werden)
 */
$plg_longitude_field = 'LONGITUDE';

/**
 * Address mode: 'multiple' or 'single'
 * - 'multiple': Address is split across multiple fields (STREET, POSTCODE, CITY, COUNTRY)
 * - 'single': Complete address is stored in a single field
 *
 * Adressmodus: 'multiple' oder 'single'
 * - 'multiple': Adresse ist auf mehrere Felder aufgeteilt (STREET, POSTCODE, CITY, COUNTRY)
 * - 'single': Vollständige Adresse ist in einem einzelnen Feld gespeichert
 */
$plg_address_mode = 'multiple';

/**
 * Address fields used for geocoding (combined to form the full address)
 * Only used when $plg_address_mode = 'multiple'
 *
 * Adressfelder für Geocoding (werden zur vollständigen Adresse kombiniert)
 * Nur verwendet wenn $plg_address_mode = 'multiple'
 */
$plg_address_fields = array('STREET', 'POSTCODE', 'CITY', 'COUNTRY');

/**
 * Single address field name (when address is stored in one field)
 * Only used when $plg_address_mode = 'single'
 * Example: 'ADDRESS' or 'FULL_ADDRESS' or any custom field name
 *
 * Name des einzelnen Adressfelds (wenn Adresse in einem Feld gespeichert ist)
 * Nur verwendet wenn $plg_address_mode = 'single'
 * Beispiel: 'ADDRESS' oder 'FULL_ADDRESS' oder ein benutzerdefinierter Feldname
 */
$plg_single_address_field = 'ADDRESS';

/**
 * Geocoding service to use:
 * - 'nominatim' = OpenStreetMap Nominatim (free, no API key required)
 * - 'google' = Google Maps Geocoding API (requires API key)
 *
 * Geocoding-Dienst:
 * - 'nominatim' = OpenStreetMap Nominatim (kostenlos, kein API-Schlüssel erforderlich)
 * - 'google' = Google Maps Geocoding API (API-Schlüssel erforderlich)
 */
$plg_geocoding_service = 'nominatim';

/**
 * Google Maps API Key (only required if using Google geocoding)
 * Google Maps API-Schlüssel (nur erforderlich bei Google-Geocoding)
 */
$plg_google_api_key = '';

/**
 * Default map zoom level (1-18, higher = more zoomed in)
 * Standard-Zoomstufe der Karte (1-18, höher = mehr vergrößert)
 */
$plg_map_zoom = 6;

/**
 * Default map center latitude
 * Standard-Kartenzentrums-Breitengrad
 */
$plg_map_center_lat = 51.1657;

/**
 * Default map center longitude
 * Standard-Kartenzentrums-Längengrad
 */
$plg_map_center_lng = 10.4515;

/**
 * Map height (CSS value)
 * Kartenhöhe (CSS-Wert)
 */
$plg_map_height = '500px';

/**
 * Show popup information when clicking on a marker
 * Popup-Information beim Klicken auf einen Marker anzeigen
 */
$plg_show_popup_info = true;

/**
 * Profile fields to show in popup (in order)
 * Profilfelder, die im Popup angezeigt werden (in Reihenfolge)
 */
$plg_popup_fields = array('FIRST_NAME', 'LAST_NAME', 'CITY');

/**
 * Enable marker clustering (groups nearby markers)
 * Marker-Clustering aktivieren (gruppiert nahe beieinander liegende Marker)
 */
$plg_cluster_markers = true;

/**
 * Roles that are allowed to view the map plugin.
 * Leave empty array() to allow all logged-in users.
 * Enter role IDs to restrict access.
 *
 * Rollen, die das Karten-Plugin sehen dürfen.
 * Leeres Array array() erlaubt allen angemeldeten Benutzern den Zugriff.
 * Rollen-IDs eintragen, um den Zugriff einzuschränken.
 */
$plg_roles_view_plugin = array();

/**
 * Allow visitors (not logged in) to view the map
 * Besuchern (nicht angemeldet) erlauben, die Karte zu sehen
 */
$plg_visitors_can_view = false;

/**
 * Roles whose members should be shown on the map.
 * Leave empty array() to show all members.
 * Enter role IDs to restrict which members are shown.
 *
 * Rollen, deren Mitglieder auf der Karte angezeigt werden sollen.
 * Leeres Array array() zeigt alle Mitglieder an.
 * Rollen-IDs eintragen, um einzuschränken, welche Mitglieder angezeigt werden.
 */
$plg_roles_to_show = array();

/**
 * Enable automatic geocoding when address is updated
 * Automatisches Geocoding bei Adressänderung aktivieren
 */
$plg_auto_geocode_on_save = true;

/**
 * Delay between geocoding requests in milliseconds (to respect API rate limits)
 * For Nominatim: minimum 1000ms (1 request per second)
 *
 * Verzögerung zwischen Geocoding-Anfragen in Millisekunden (API-Limits beachten)
 * Für Nominatim: mindestens 1000ms (1 Anfrage pro Sekunde)
 */
$plg_geocoding_delay = 1000;

/**
 * Map tile provider URL template
 * Standard is OpenStreetMap. You can use other providers here.
 *
 * URL-Vorlage des Karten-Tile-Anbieters
 * Standard ist OpenStreetMap. Hier können andere Anbieter verwendet werden.
 */
$plg_map_tile_url = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

/**
 * Map tile attribution text
 * Urheberrechtshinweis für Karten-Tiles
 */
$plg_map_tile_attribution = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
